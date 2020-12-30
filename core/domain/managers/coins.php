<?php
/**
 * Created by PhpStorm.
 * User: christophercarter
 * Date: 6/3/19
 * Time: 2:19 PM
 */

Entities::uses('coins');

class CoinsAwardsTypesManager extends BaseEntityManager
{
    protected $pk = DBField::COIN_AWARD_TYPE_ID;
    protected $entityClass = ActivationTypeEntity::class;
    protected $table = Table::CoinAwardType;
    protected $table_alias = TableAlias::CoinAwardType;

    const GNS_KEY_PREFIX = GNS_ROOT.'.coins.types';

    /** @var CoinAwardTypeEntity[] */
    protected $coinAwardTypes = [];

    public static $fields = [
        DBField::COIN_AWARD_TYPE_ID,
        DBField::DISPLAY_NAME,
        DBField::IS_ACTIVE,
        DBField::CREATED_BY,
        DBField::MODIFIED_BY,
        DBField::DELETED_BY
    ];

    /**
     * @param CoinAwardTypeEntity $data
     * @param Request $request
     */
    public function processVFields(DBManagerEntity $data, Request $request)
    {

    }

    /**
     * @return string
     */
    public function generatelAllCacheKey()
    {
        return self::GNS_KEY_PREFIX.'.all';
    }

    /**
     * @param Request $request
     * @return CoinAwardTypeEntity[]
     */
    public function getAllCoinAwardTypes(Request $request)
    {
        if (!$this->coinAwardTypes) {
            $activationTypes = $this->query($request->db)
                ->cache($this->generatelAllCacheKey(), ONE_WEEK)
                ->get_entities($request);
            $this->coinAwardTypes = $this->index($activationTypes);
        }

        return $this->coinAwardTypes;
    }

    /**
     * @param Request $request
     * @param $coinAwardTypeId
     * @return CoinAwardTypeEntity|array
     */
    public function getCoinAwardTypeById(Request $request, $coinAwardTypeId)
    {
        return $this->getAllCoinAwardTypes($request)[$coinAwardTypeId] ?? [];
    }

}


class UserCoinsManager extends BaseEntityManager
{
    protected $pk = DBField::USER_COIN_ID;
    protected $entityClass = UserCoinEntity::class;
    protected $table = Table::UserCoin;
    protected $table_alias = TableAlias::UserCoin;

    const GNS_KEY_PREFIX = GNS_ROOT . '.coins.users';

    public static $fields = [
        DBField::USER_COIN_ID,
        DBField::GAME_BUILD_ID,
        DBField::COIN_AWARD_TYPE_ID,
        DBField::HOST_ID,
        DBField::USER_ID,
        DBField::VALUE,
        DBField::CREATE_TIME,
        DBField::CONTEXT_ENTITY_TYPE_ID,
        DBField::CONTEXT_ENTITY_ID,
        DBField::NAME,
        DBField::GAME_INSTANCE_ROUND_ID,
        DBField::GAME_INSTANCE_ROUND_PLAYER_ID,
        DBField::GUEST_COIN_ID,
        DBField::IS_ACTIVE,
        DBField::CREATED_BY,
        DBField::MODIFIED_BY,
        DBField::DELETED_BY
    ];

    /**
     * @param UserCoinEntity $data
     * @param Request $request
     * @return UserCoinEntity|void
     */
    public function processVFields(DBManagerEntity $data, Request $request)
    {

    }

    /**
     * @param $userId
     * @return string
     */
    public function generateTotalUserCoinsCacheKey($userId, $hostId = null)
    {
        $hostString = $hostId ? ".{$hostId}" : "";

        return self::GNS_KEY_PREFIX.".{$userId}{$hostString}.total";
    }

    /**
     * @param Request $request
     * @param $coinAwardTypeId
     * @param $gameBuildId
     * @param $userId
     * @param $value
     * @param null $hostId
     * @param null $contextEntityTypeId
     * @param null $contextEntityId
     * @param null $name
     * @param null $gameInstanceRoundPlayerId
     * @param null $guestCoinId
     * @return int
     */
    public function insertNewUserCoin(Request $request, $coinAwardTypeId, $userId, $value, $gameBuildId = null,
                                      $hostId = null, $contextEntityTypeId = null, $contextEntityId = null, $name = null,
                                      $gameInstanceRoundId = null, $gameInstanceRoundPlayerId = null, $guestCoinId = null)
    {
        $data = [
            DBField::GAME_BUILD_ID => $gameBuildId,
            DBField::COIN_AWARD_TYPE_ID => $coinAwardTypeId,
            DBField::HOST_ID => $hostId,
            DBField::USER_ID => $userId,
            DBField::VALUE => $value,
            DBField::CONTEXT_ENTITY_TYPE_ID => $contextEntityTypeId,
            DBField::CONTEXT_ENTITY_ID => $contextEntityId,
            DBField::NAME => $name,
            DBField::GAME_INSTANCE_ROUND_ID => $gameInstanceRoundId,
            DBField::GAME_INSTANCE_ROUND_PLAYER_ID => $gameInstanceRoundPlayerId,
            DBField::CREATE_TIME => $request->getCurrentSqlTime(),
            DBField::GUEST_COIN_ID => $guestCoinId,
            DBField::IS_ACTIVE => 1,
            DBField::CREATED_BY => $request->requestId
        ];

        return $this->query($request->db)->add($data);
    }

    /**
     * @param Request $request
     * @param $userCoinIds
     * @return UserCoinEntity[]
     */
    public function getUserCoinsByIds(Request $request, $userCoinIds)
    {
        return $this->getEntitiesByPks($request, $userCoinIds);
    }

    /**
     * @param Request $request
     * @param $userId
     * @return int
     */
    public function getTotalUserCoins(Request $request, $userId, $hostId = null)
    {
        return $this->query($request->db)
            //->cache($this->generateTotalUserCoinsCacheKey($userId, $hostId), FIFTEEN_MINUTES)
            ->filter($this->filters->byHostId($hostId))
            ->filter($this->filters->byUserId($userId))
            ->sum(DBField::VALUE);
    }

    /**
     * @param Request $request
     * @param MySQLBackend $dbConnection
     * @param $guestId
     * @param $userId
     */
    public function migrateUnclaimedGuestCoins(Request $request, DBBackend $dbConnection, $guestId, $userId)
    {
        $guestCoinsManager = $request->managers->guestCoins();

        /** @var GuestCoinEntity[] $guestCoins */
        $guestCoins = $guestCoinsManager->query($request->db)
            ->filter($guestCoinsManager->filters->isActive())
            ->filter($guestCoinsManager->filters->byGuestId($guestId))
            ->left_join($this, $this->filters->byGuestCoinId($guestCoinsManager->createPkField()))
            ->filter($this->filters->IsNull($this->createPkField()))
            ->get_entities($request);

        $guestCoinIds = [];

        $userCoinsData = [];

        foreach ($guestCoins as $guestCoin) {
            $guestCoinIds[] = $guestCoin->getPk();

            $userCoinsData[] = [
                DBField::GAME_BUILD_ID => $guestCoin->getGameBuildId(),
                DBField::COIN_AWARD_TYPE_ID => $guestCoin->getCoinAwardTypeId(),
                DBField::HOST_ID  => $guestCoin->getHostId(),
                DBField::VALUE => $guestCoin->getValue(),
                DBField::CONTEXT_ENTITY_TYPE_ID => $guestCoin->getContextEntityTypeId(),
                DBField::CONTEXT_ENTITY_ID => $guestCoin->getContextEntityId(),
                DBField::NAME => $guestCoin->getName(),
                DBField::GAME_INSTANCE_ROUND_ID => $guestCoin->getGameInstanceRoundId(),
                DBField::GAME_INSTANCE_ROUND_PLAYER_ID => $guestCoin->getGameInstanceRoundPlayerId(),
                DBField::CREATE_TIME => $guestCoin->getCreateTime(),
                DBField::GUEST_COIN_ID => $guestCoin->getPk(),
                DBField::IS_ACTIVE => 1,
                DBField::CREATED_BY => $request->requestId,
                DBField::USER_ID => $userId
            ];
        }

        if ($userCoinsData) {
            $this->query($request->db)->add_multiple($userCoinsData);

            $guestCoinsManager->query($request->db)
                ->set_connection($dbConnection)
                ->filter($guestCoinsManager->filters->byPk($guestCoinIds))
                ->update([
                    DBField::IS_ACTIVE => 0,
                    DBField::MODIFIED_BY => $request->requestId
                ]);
        }

        Cache::get_cache()->delete($guestCoinsManager->generateTotalGuestCoinsCacheKey($guestId));
        Cache::get_cache()->delete($this->generateTotalUserCoinsCacheKey($userId));
    }

    /**
     * @param Request $request
     * @param $hostId
     * @param int $count
     * @param string $timeFrame
     * @return array
     */
    public function getLeaderboardForHost(Request $request, $hostId, $count = 20, $timeFrame = '-1 day')
    {
        $dt = new DateTime();
        $dt->modify($timeFrame);

        $fields = [
            $this->field(DBField::HOST_ID),
            new MaxDBField(DBField::USER_COIN_ID, DBField::USER_COIN_ID),
            new SumDBField(DBField::VALUE, DBField::VALUE)
        ];

        $results = $this->query($request)
            ->fields($fields)
            ->filter($this->filters->byHostId($hostId))
            ->filter($this->filters->Gte(DBField::CREATE_TIME, $timeFrame))
            ->group_by(DBField::USER_ID)
            ->sort_desc(DBField::VALUE)
            ->limit($count)
            ->get_list();

        if ($results) {
            $userCoinIds = array_extract(DBField::USER_COIN_ID, $results);
            $names = $this->query($request)
                ->filter($this->filters->byPk($userCoinIds))
                ->get_list(DBField::USER_COIN_ID, DBField::NAME);

            $names = $this->index($names);

            $position = 0;
            foreach ($results as $key => $result) {
                $position++;
                $userCoinId = $result[DBField::USER_COIN_ID];
                $results[$key][DBField::NAME] = $names[$userCoinId][DBField::NAME];
                $results[$key][VField::POSITION] = $position;
                unset($results[$key][DBField::USER_COIN_ID]);
            }
        }

        return $results;
    }
}

class GuestCoinsManager extends BaseEntityManager
{
    protected $pk = DBField::GUEST_COIN_ID;
    protected $entityClass = GuestCoinEntity::class;
    protected $table = Table::GuestCoin;
    protected $table_alias = TableAlias::GuestCoin;

    const GNS_KEY_PREFIX = GNS_ROOT . '.coins.guests';

    public static $fields = [
        DBField::GUEST_COIN_ID,
        DBField::GAME_BUILD_ID,
        DBField::COIN_AWARD_TYPE_ID,
        DBField::HOST_ID,
        DBField::GUEST_ID,
        DBField::VALUE,
        DBField::CREATE_TIME,
        DBField::CONTEXT_ENTITY_TYPE_ID,
        DBField::CONTEXT_ENTITY_ID,
        DBField::NAME,
        DBField::GAME_INSTANCE_ROUND_ID,
        DBField::GAME_INSTANCE_ROUND_PLAYER_ID,
        DBField::IS_ACTIVE,
        DBField::CREATED_BY,
        DBField::MODIFIED_BY,
        DBField::DELETED_BY
    ];

    /**
     * @param GuestCoinEntity $data
     * @param Request $request
     * @return GuestCoinEntity|void
     */
    public function processVFields(DBManagerEntity $data, Request $request)
    {

    }

    /**
     * @param $guestId
     * @return string
     */
    public function generateTotalGuestCoinsCacheKey($guestId, $hostId = null)
    {
        $hostString = $hostId ? ".{$hostId}" : "";

        return self::GNS_KEY_PREFIX.".{$guestId}{$hostString}.total";
    }


    /**
     * @param Request $request
     * @param $coinAwardTypeId
     * @param $guestId
     * @param $value
     * @param null $gameBuildId
     * @param null $hostId
     * @param null $contextEntityTypeId
     * @param null $contextEntityId
     * @param null $name
     * @param null $gameInstanceRoundPlayerId
     * @return int
     */
    public function insertNewGuestCoin(Request $request, $coinAwardTypeId, $guestId, $value, $gameBuildId = null,
                                       $hostId = null, $contextEntityTypeId = null, $contextEntityId = null, $name = null,
                                       $gameInstanceRoundId = null, $gameInstanceRoundPlayerId = null)
    {
        $data = [
            DBField::GAME_BUILD_ID => $gameBuildId,
            DBField::COIN_AWARD_TYPE_ID => $coinAwardTypeId,
            DBField::HOST_ID => $hostId,
            DBField::GUEST_ID => $guestId,
            DBField::VALUE => $value,
            DBField::CONTEXT_ENTITY_TYPE_ID => $contextEntityTypeId,
            DBField::CONTEXT_ENTITY_ID => $contextEntityId,
            DBField::NAME => $name,
            DBField::GAME_INSTANCE_ROUND_ID => $gameInstanceRoundId,
            DBField::GAME_INSTANCE_ROUND_PLAYER_ID => $gameInstanceRoundPlayerId,
            DBField::CREATE_TIME => $request->getCurrentSqlTime(),
            DBField::IS_ACTIVE => 1,
            DBField::CREATED_BY => $request->requestId
        ];

        return $this->query($request->db)->add($data);
    }

    /**
     * @param Request $request
     * @param $guestId
     * @return int
     */
    public function getTotalGuestCoins(Request $request, $guestId, $hostId = null)
    {
        return $this->query($request->db)
            //->cache($this->generateTotalGuestCoinsCacheKey($guestId, $hostId), FIFTEEN_MINUTES)
            ->filter($this->filters->byHostId($hostId))
            ->filter($this->filters->isActive())
            ->filter($this->filters->byGuestId($guestId))
            ->sum(DBField::VALUE);
    }

    /**
     * @param Request $request
     * @param $guestCoinIds
     * @return GuestCoinEntity[]
     */
    public function getGuestCoinsByIds(Request $request, $guestCoinIds)
    {
        return $this->getEntitiesByPks($request, $guestCoinIds);
    }


    /**
     * @param Request $request
     * @param $guestId
     * @return bool
     */
    public function checkGuestHasUnclaimedCoins(Request $request, $guestId)
    {
        return $this->query($request->db)
            ->filter($this->filters->byGuestId($guestId))
            ->filter($this->filters->isActive())
            ->sort_desc($this->createPkField())
            ->limit(1)
            ->exists();
    }

    /**
     * @param Request $request
     * @param $gameInstanceRoundId
     * @param int $count
     * @return array
     */
    public function getGameInstanceRoundLeaderboard(Request $request, $gameInstanceRoundId, $count = 20)
    {
        $usersCoinsManager = $request->managers->userCoins();

        if (!is_int($count))
            $count = 20;

        $conn = $request->db->get_connection(SQLN_SLAVE);

        $sql = "
            (
                SELECT 
                    {$conn->quote_field(DBField::GAME_INSTANCE_ROUND_ID)}
                    , SUM({$conn->quote_field(DBField::VALUE)}) as value
                    , ( 
                        SELECT 
                            {$conn->quote_field(DBField::NAME)}
                        FROM {$conn->quote_field($this->getTable())} {$conn->quote_field("{$this->getTableAlias()}2")}
                        WHERE
                            {$conn->quote_field("{$this->getTableAlias()}2")}.{$conn->quote_field(DBField::GAME_INSTANCE_ROUND_ID)} = {$conn->quote_value($gameInstanceRoundId)}
                            AND {$conn->quote_field("{$this->getTableAlias()}2")}.{$conn->quote_field(DBField::GUEST_ID)} = {$conn->quote_field("{$this->getTableAlias()}")}.{$conn->quote_field(DBField::GUEST_ID)}
                        ORDER BY {$conn->quote_field(DBField::CREATE_TIME)} DESC
                        LIMIT 1
                        
                    ) as name
                FROM {$conn->quote_field($this->getTable())} {$conn->quote_field($this->getTableAlias())}
                WHERE 
                    {$conn->quote_field(DBField::GAME_INSTANCE_ROUND_ID)} = {$conn->quote_value($gameInstanceRoundId)}
                    AND {$conn->quote_field(DBField::IS_ACTIVE)} = 1
                GROUP BY {$conn->quote_field(DBField::GUEST_ID)}
                ORDER BY {$conn->quote_field(DBField::VALUE)} DESC
                LIMIT {$count}
            )

            UNION ALL
            
            (
                SELECT 
                    {$conn->quote_field(DBField::GAME_INSTANCE_ROUND_ID)}
                    , SUM({$conn->quote_field(DBField::VALUE)}) as value
                    , ( 
                        SELECT 
                            {$conn->quote_field(DBField::NAME)}
                        FROM {$conn->quote_field($usersCoinsManager->getTable())} {$conn->quote_field("{$usersCoinsManager->getTableAlias()}2")}
                        WHERE
                            {$conn->quote_field("{$usersCoinsManager->getTableAlias()}2")}.{$conn->quote_field(DBField::GAME_INSTANCE_ROUND_ID)} = {$conn->quote_value($gameInstanceRoundId)}
                            AND {$conn->quote_field("{$usersCoinsManager->getTableAlias()}2")}.{$conn->quote_field(DBField::USER_ID)} = {$conn->quote_field("{$usersCoinsManager->getTableAlias()}")}.{$conn->quote_field(DBField::USER_ID)}
                        ORDER BY {$conn->quote_field(DBField::CREATE_TIME)} DESC
                        LIMIT 1
                        
                    ) as name
                    
                FROM {$conn->quote_field($usersCoinsManager->getTable())} {$conn->quote_field($usersCoinsManager->getTableAlias())}
                WHERE 
                    {$conn->quote_field(DBField::GAME_INSTANCE_ROUND_ID)} = {$conn->quote_value($gameInstanceRoundId)}
                    AND {$conn->quote_field(DBField::IS_ACTIVE)} = 1
                GROUP BY {$conn->quote_field(DBField::USER_ID)}
                ORDER BY {$conn->quote_field(DBField::VALUE)} DESC
                LIMIT {$count}
            )

            ORDER BY {$conn->quote_field(DBField::VALUE)} DESC
            LIMIT {$count};
        ";

        $results = $this->query($request->db)->sql($sql);

        $pos = 0;
        foreach ($results as $key => $result) {
            $pos++;
            $results[$key]['position'] = $pos;

        }

        return $results;
    }
}
<?php
/**
 * Created by PhpStorm.
 * User: christophercarter
 * Date: 3/4/19
 * Time: 2:28 AM
 */

Entities::uses('games-players');

class GamesPlayersStatsManager extends BaseEntityManager
{
    protected $entityClass = GamePlayerStatEntity::class;
    protected $table = Table::GamePlayerStat;
    protected $table_alias = TableAlias::GamePlayerStat;
    protected $pk = DBField::GAME_PLAYER_STAT_ID;

    const GNS_KEY_PREFIX = GNS_ROOT.'.game-player-stats';

    public $foreign_managers = [
        GamesManager::class => DBField::GAME_ID
    ];

    public static $fields = [
        DBField::GAME_PLAYER_STAT_ID,
        DBField::GAME_ID,
        DBField::GAME_BUILD_ID,
        DBField::GAME_PLAYER_STAT_TYPE_ID,
        DBField::HOST_ID,
        DBField::GUEST_ID,
        DBField::USER_ID,
        DBField::CREATE_TIME,
        DBField::NAME,
        DBField::VALUE,
        DBField::GAME_INSTANCE_ROUND_PLAYER_ID,
        DBField::CONTEXT_X_GAME_ASSET_ID,
        DBField::IS_ACTIVE,
        DBField::CREATED_BY,
        DBField::MODIFIED_BY,
        DBField::DELETED_BY
    ];

    /**
     * @param Request $request
     * @param $gameId
     * @param $gamePlayerStatTypeId
     * @param $hostId
     * @param $guestId
     * @param $userId
     * @param $name
     * @param $value
     * @param null $contextXGameAssetId
     * @param null $gameInstanceRoundPlayerId
     * @param null $createTime
     * @return GamePlayerStatEntity
     */
    public function createNewGamePlayerStat(Request $request, $gameId, $gameBuildId, $gameControllerId, $gamePlayerStatTypeId, $hostId, $guestId, $userId,
                                            $name, $value, $contextXGameAssetId = null, $gameInstanceRoundPlayerId = null,
                                            $createTime = null)
    {
        if (!$createTime)
            $createTime = $request->getCurrentSqlTime();

        $data = [
            DBField::GAME_ID => $gameId,
            DBField::GAME_BUILD_ID => $gameBuildId,
            DBField::GAME_CONTROLLER_ID => $gameControllerId,
            DBField::GAME_PLAYER_STAT_TYPE_ID => $gamePlayerStatTypeId,
            DBField::HOST_ID => $hostId,
            DBField::GUEST_ID => $guestId,
            DBField::USER_ID => $userId,
            DBField::CREATE_TIME => $createTime,
            DBField::NAME => $name,
            DBField::VALUE => floatval($value),
            DBField::GAME_INSTANCE_ROUND_PLAYER_ID => $gameInstanceRoundPlayerId,
            DBField::CONTEXT_X_GAME_ASSET_ID => $contextXGameAssetId
        ];

        /** @var GamePlayerStatEntity $gamePlayerStat */
        $gamePlayerStat = $this->query($request->db)->createNewEntity($request, $data);

        return $gamePlayerStat;
    }

    /**
     * @param Request $request
     * @param $hostId
     * @param $gameId
     * @param $createTime
     * @return array
     */
    public function getLeaderboardForGameHost(Request $request, $hostId, $gameId, $gamePlayerStatTypeId, $createTime)
    {
        $gamesAssetsManager = $request->managers->gamesAssets();

        $results = $this->query($request->db)
            ->filter($this->filters->byHostId($hostId))
            ->filter($this->filters->byGameId($gameId))
            ->filter($this->filters->byGamePlayerStatTypeId($gamePlayerStatTypeId))
            ->filter($this->filters->Gte(DBField::CREATE_TIME, $createTime))
            ->sort_desc($this->field(DBField::VALUE))
            ->limit(100)
            ->get_list(DBField::NAME, DBField::VALUE, DBField::GUEST_ID, DBField::GAME_ID, DBField::GAME_BUILD_ID, DBField::CONTEXT_X_GAME_ASSET_ID);

        $indexedData = [];

        if ($results) {
            $customGameAssetIds = unique_array_extract(DBField::CONTEXT_X_GAME_ASSET_ID, $results);
            $gameIds = unique_array_extract(DBField::GAME_ID, $results);
            $customGameAssets = [];

            if ($customGameAssetIds) {
                $customGameAssets = $gamesAssetsManager->getCustomGameAssetsByCustomGameAssetIds($request, $customGameAssetIds, $gameIds);
            }

            foreach ($results as $key => $result) {
                $gameId = $result[DBField::GAME_ID];
                $gameBuildId = $result[DBField::GAME_BUILD_ID];
                $customGameAssetId = $result[DBField::CONTEXT_X_GAME_ASSET_ID];
                $publicUrl = null;

                foreach ($customGameAssets as $customGameAsset) {
                    if ($gameBuildId  && $customGameAsset->getCustomGameAssetId() == $customGameAssetId) {
                        $publicUrl = $gamesAssetsManager->generatePublicCustomAssetUrl(
                            $request,
                            $gameId,
                            $customGameAsset->getUpdateChannel(),
                            $customGameAsset->getSlug(),
                            $customGameAsset->getCustomGameAssetId(),
                            $customGameAsset->getFileName(),
                            $gameBuildId
                        );
                    }
                }
                $indexedData[] = [
                    'position' => $key+1,
                    DBField::NAME => $result[DBField::NAME],
                    DBField::VALUE => $result[DBField::VALUE],
                    DBField::GUEST_ID => $result[DBField::GUEST_ID],
                    VField::PUBLIC_URL => $publicUrl
                ];
            }
        }

        return $indexedData;
    }
}

class GamesPlayersStatsTypesManager extends BaseEntityManager
{
    protected $entityClass = GamePlayerStatTypeEntity::class;
    protected $table = Table::GamePlayerStatType;
    protected $table_alias = TableAlias::GamePlayerStatType;
    protected $pk = DBField::GAME_PLAYER_STAT_TYPE_ID;

    const GNS_KEY_PREFIX = GNS_ROOT.'.game-player-stats-types';

    const TYPE_HIGH_SCORE = 1;

    public $foreign_managers = [
    ];

    public static $fields = [
        DBField::GAME_PLAYER_STAT_TYPE_ID,
        DBField::DISPLAY_NAME,
        DBField::IS_ACTIVE,
        DBField::CREATED_BY,
        DBField::MODIFIED_BY,
        DBField::DELETED_BY
    ];
}
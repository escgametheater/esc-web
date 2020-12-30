<?php
/**
 * Created by PhpStorm.
 * User: christophercarter
 * Date: 10/18/18
 * Time: 11:04 AM
 */


class GamesAssetsManager extends BaseEntityManager
{
    protected $entityClass = GameAssetEntity::class;
    protected $table = Table::GameAsset;
    protected $table_alias = TableAlias::GameAsset;
    protected $pk = DBField::GAME_ASSET_ID;

    const GNS_KEY_PREFIX = GNS_ROOT.'.game-assets';

    public $foreign_managers = [
        GamesManager::class => DBField::GAME_ID
    ];

    public static $fields = [
        DBField::GAME_ASSET_ID,
        DBField::GAME_ID,
        DBField::MD5,
        DBField::MIME_TYPE,
        DBField::BUCKET,
        DBField::BUCKET_PATH,
        DBField::FILE_SIZE,
        DBField::COMPUTED_FILENAME,
        DBField::IS_ACTIVE,
        DBField::CREATED_BY,
        DBField::MODIFIED_BY,
        DBField::DELETED_BY
    ];

    public $removed_json_fields = [
        DBField::BUCKET_PATH,
        DBField::BUCKET,
    ];

    /**
     * @param $gameBuildId
     * @return string
     */
    public static function generateGameBuildIdCacheKey($gameBuildId)
    {
        return self::GNS_KEY_PREFIX.".game-build.{$gameBuildId}";
    }

    /**
     * @param $gameControllerId
     * @return string
     */
    public static function generateGameControllerIdCacheKey($gameControllerId)
    {
        return self::GNS_KEY_PREFIX.".game-controller.{$gameControllerId}";
    }

    /**
     * @return Closure
     */
    public function getGameBuildIdCacheKeyGenerator()
    {
        return function ($gameBuildId) {
            return GamesAssetsManager::generateGameBuildIdCacheKey($gameBuildId);
        };
    }

    /**
     * @return Closure
     */
    public function getGameControllerIdCacheKeyGenerator()
    {
        return function($gameControllerId) {
            return GamesAssetsManager::generateGameControllerIdCacheKey($gameControllerId);
        };
    }

    /**
     * @param $gameId
     * @return string
     */
    public function buildGameAssetBucketPath($gameId)
    {
        return "{$gameId}/";
    }

    /**
     * @param GameAssetEntity|GameBuildAssetEntity|GameControllerAssetEntity|CustomGameAssetEntity|GameInstanceLogAssetEntity $gameAsset
     * @param Request $request
     * @return GameAssetEntity
     */
    public function processVFields(DBManagerEntity $gameAsset, Request $request)
    {
        if ($gameAsset instanceof GameBuildAssetEntity) {

            $gameAsset[VField::URL] = $request->getApiUrl("/v1/games-builds/download-game-build-asset/{$gameAsset->getUpdateChannel()}/{$gameAsset->getGameBuildAssetId()}/");

        } elseif ($gameAsset instanceof GameControllerAssetEntity) {

            $gameAsset[VField::URL] = $request->getPlayUrl("/game-controller/{$gameAsset->getGameControllerId()}/{$gameAsset->getFolderPath()}{$gameAsset->getFileName()}");

        } elseif ($gameAsset instanceof CustomGameAssetEntity) {

            $gameAsset->setCanMod(false);

            $gameAsset[VField::URL] = $request->getApiUrl("/v1/games/download-custom-game-asset/{$gameAsset->getUpdateChannel()}/{$gameAsset->getCustomGameAssetId()}/");

            // For Custom Game Assets that are instantiated with public props, we should generate a public URL for them to be accessed.
            if ($gameAsset->is_public())
                $gameAsset[VField::PUBLIC_URL] = $this->generatePublicCustomAssetUrl(
                    $request,
                    $gameAsset->getGameId(),
                    $gameAsset->getUpdateChannel(),
                    $gameAsset->getSlug(),
                    $gameAsset->getCustomGameAssetId(),
                    $gameAsset->getFileName()
                );

        } elseif ($gameAsset instanceof CustomGameModBuildAssetEntity) {

            $gameAsset[VField::URL] = $request->getApiUrl("/v1/games/download-custom-game-mod-asset/{$gameAsset->getUpdateChannel()}/{$gameAsset->getCustomGameAssetId()}/");

            // For Custom Game Assets that are instantiated with public props, we should generate a public URL for them to be accessed.
            if ($gameAsset->is_public())
                $gameAsset[VField::PUBLIC_URL] = $this->generatePublicCustomModAssetUrl(
                    $request,
                    $gameAsset->getGameId(),
                    $gameAsset->getUpdateChannel(),
                    $gameAsset->getSlug(),
                    $gameAsset->getCustomGameAssetId(),
                    $gameAsset->getFileName(),
                    $gameAsset->getGameModBuildId()
                );
        } elseif ($gameAsset instanceof GameInstanceLogAssetEntity) {

            $gameAsset[VField::URL] = $request->getWwwUrl("/admin/host-app/game-log/{$gameAsset->getGameInstanceLogAssetId()}");

        } elseif ($gameAsset instanceof GameControllerAssetEntity) {

            $url = "{$gameAsset->getFolderPath()}/{$gameAsset->getFileName()}";

            $gameAsset->updateField(VField::URL, $url);

        } else {
            $gameAsset[VField::URL] = '';
        }

        return $gameAsset;
    }

    /**
     * @param Request $request
     * @param $gameId
     * @param $updateChannel
     * @param $slug
     * @param $customGameAssetId
     * @param null $gameBuildId
     * @return string
     */
    public function generatePublicCustomAssetUrl(Request $request, $gameId, $updateChannel, $slug, $customGameAssetId, $fileName, $gameBuildId = null)
    {
        $playHost = $request->config['hosts']['play'];

        if (!$gameBuildId)
            return "{$request->scheme}://{$playHost}/cga/{$gameId}/{$updateChannel}/{$slug}/{$customGameAssetId}/{$fileName}";
        else
            return "{$request->scheme}://{$playHost}/cga/{$gameId}/{$updateChannel}/{$slug}/{$customGameAssetId}/{$gameBuildId}/{$fileName}";
    }

    /**
     * @param Request $request
     * @param $gameId
     * @param $updateChannel
     * @param $slug
     * @param $customGameAssetId
     * @param $fileName
     * @param $gameModBuildId
     * @return string
     */
    public function generatePublicCustomModAssetUrl(Request $request, $gameId, $updateChannel, $slug, $customGameAssetId, $fileName, $gameModBuildId)
    {
        $playHost = $request->config['hosts']['play'];

         return "{$request->scheme}://{$playHost}/cgma/{$gameId}/{$updateChannel}/{$slug}/{$customGameAssetId}/{$gameModBuildId}/{$fileName}";
    }

    /**
     * @param Request $request
     * @param $contextEntityTypeId
     * @param $contextEntityId
     * @param null $contextXGameAssetId
     * @return SqlQuery
     */
    protected function contextQueryJoin(Request $request, $contextEntityTypeId, $updateChannel = null)
    {
        $contextXGamesAssetsManager = $request->managers->contextXGamesAssets();

        // Shared QueryBuilder Settings
        $queryBuilder = $contextXGamesAssetsManager->query($request->db)
            ->fields($this->createDBFields())
            ->inner_join($this)
            ->filter($contextXGamesAssetsManager->filters->byContextEntityTypeId($contextEntityTypeId))
            ->filter($contextXGamesAssetsManager->filters->isActive())
            ->filter($this->filters->isActive())
            ->mapper($this);

        if ($updateChannel)
            $queryBuilder->filter($contextXGamesAssetsManager->filters->byUpdateChannel($updateChannel));

        switch ($contextEntityTypeId) {

            // Game Build Assets
            case EntityType::GAME_BUILD:

                $extraFields = [
                    $contextXGamesAssetsManager->createAliasedPkField(VField::GAME_BUILD_ASSET_ID),
                    $contextXGamesAssetsManager->aliasField(DBField::CONTEXT_ENTITY_ID, DBField::GAME_BUILD_ID),
                    $contextXGamesAssetsManager->field(DBField::FILENAME),
                    $contextXGamesAssetsManager->field(DBField::UPDATE_CHANNEL),
                    $contextXGamesAssetsManager->field(DBField::USER_ID),
                    $contextXGamesAssetsManager->field(DBField::FOLDER_PATH),
                    $contextXGamesAssetsManager->field(DBField::EXTENSION),
                    $contextXGamesAssetsManager->field(DBField::IS_ACTIVE),
                    $contextXGamesAssetsManager->field(DBField::CREATE_TIME)
                ];

                $queryBuilder->entity(GameBuildAssetEntity::class)->fields($extraFields);
                break;


            // Game Controller Assets
            case EntityType::GAME_CONTROLLER:

                $extraFields = [
                    $contextXGamesAssetsManager->createAliasedPkField(VField::GAME_CONTROLLER_ASSET_ID),
                    $contextXGamesAssetsManager->aliasField(DBField::CONTEXT_ENTITY_ID, DBField::GAME_CONTROLLER_ID),
                    $contextXGamesAssetsManager->field(DBField::FILENAME),
                    $contextXGamesAssetsManager->field(DBField::UPDATE_CHANNEL),
                    $contextXGamesAssetsManager->field(DBField::USER_ID),
                    $contextXGamesAssetsManager->field(DBField::FOLDER_PATH),
                    $contextXGamesAssetsManager->field(DBField::EXTENSION),
                    $contextXGamesAssetsManager->field(DBField::IS_ACTIVE),
                    $contextXGamesAssetsManager->field(DBField::CREATE_TIME)
                ];

                $queryBuilder->entity(GameControllerAssetEntity::class)->fields($extraFields);
                break;


            // Custom Game Assets
            case EntityType::GAME:

                $extraFields = [
                    $contextXGamesAssetsManager->createAliasedPkField(VField::CUSTOM_GAME_ASSET_ID),
                    $contextXGamesAssetsManager->aliasField(DBField::CONTEXT_ENTITY_ID, DBField::GAME_ID),
                    $contextXGamesAssetsManager->field(DBField::FILENAME),
                    $contextXGamesAssetsManager->field(DBField::UPDATE_CHANNEL),
                    $contextXGamesAssetsManager->field(DBField::USER_ID),
                    $contextXGamesAssetsManager->field(DBField::FOLDER_PATH),
                    $contextXGamesAssetsManager->field(DBField::EXTENSION),
                    $contextXGamesAssetsManager->field(DBField::SLUG),
                    $contextXGamesAssetsManager->field(DBField::IS_ACTIVE),
                    $contextXGamesAssetsManager->field(DBField::CREATE_TIME),
                ];

                $queryBuilder->entity(CustomGameAssetEntity::class)->fields($extraFields);

                break;

            // Custom Game Assets
            case EntityType::GAME_MOD_BUILD:

                $extraFields = [
                    $contextXGamesAssetsManager->createAliasedPkField(VField::CUSTOM_GAME_ASSET_ID),
                    $contextXGamesAssetsManager->aliasField(DBField::CONTEXT_ENTITY_ID, DBField::GAME_MOD_BUILD_ID),
                    $contextXGamesAssetsManager->field(DBField::FILENAME),
                    $contextXGamesAssetsManager->field(DBField::UPDATE_CHANNEL),
                    $contextXGamesAssetsManager->field(DBField::USER_ID),
                    $contextXGamesAssetsManager->field(DBField::FOLDER_PATH),
                    $contextXGamesAssetsManager->field(DBField::EXTENSION),
                    $contextXGamesAssetsManager->field(DBField::SLUG),
                    $contextXGamesAssetsManager->field(DBField::IS_ACTIVE),
                    $contextXGamesAssetsManager->field(DBField::CREATE_TIME),
                ];

                $queryBuilder->entity(CustomGameModBuildAssetEntity::class)->fields($extraFields);

                break;

            case EntityType::GAME_INSTANCE_LOG:

                $extraFields = [
                    $contextXGamesAssetsManager->createAliasedPkField(VField::GAME_INSTANCE_LOG_ASSET_ID),
                    $contextXGamesAssetsManager->aliasField(DBField::CONTEXT_ENTITY_ID, DBField::GAME_INSTANCE_LOG_ID),
                    $contextXGamesAssetsManager->field(DBField::FILENAME),
                    $contextXGamesAssetsManager->field(DBField::UPDATE_CHANNEL),
                    $contextXGamesAssetsManager->field(DBField::USER_ID),
                    $contextXGamesAssetsManager->field(DBField::FOLDER_PATH),
                    $contextXGamesAssetsManager->field(DBField::EXTENSION),
                    $contextXGamesAssetsManager->field(DBField::SLUG),
                    $contextXGamesAssetsManager->field(DBField::IS_ACTIVE),
                    $contextXGamesAssetsManager->field(DBField::CREATE_TIME),
                ];

                $queryBuilder->entity(GameInstanceLogAssetEntity::class)->fields($extraFields);

                break;
        }


        return $queryBuilder;
    }

    /**
     * @param Request $request
     * @param $gameControllerId
     * @param $gameControllerAssetId
     * @return GameControllerAssetEntity|array
     */
    public function getGameControllerAssetByGameControllerAssetId(Request $request, $gameControllerAssetId)
    {
        $contextXGamesAssetsManager = $request->managers->contextXGamesAssets();

        return $this->contextQueryJoin($request, EntityType::GAME_CONTROLLER)
            ->filter($contextXGamesAssetsManager->filters->isActive())
            ->filter($contextXGamesAssetsManager->filters->byPk($gameControllerAssetId))
            ->get_entity($request);
    }

    /**
     * @param Request $request
     * @param $gameBuildId
     * @param $gameBuildAssetId
     * @return GameBuildAssetEntity|array
     */
    public function getGameBuildAssetByGameBuildAssetId(Request $request, $gameBuildAssetId)
    {
        $contextXGamesAssetsManager = $request->managers->contextXGamesAssets();

        return $this->contextQueryJoin($request, EntityType::GAME_BUILD)
            ->filter($contextXGamesAssetsManager->filters->isActive())
            ->filter($contextXGamesAssetsManager->filters->byPk($gameBuildAssetId))
            ->get_entity($request);
    }

    /**
     * @param Request $request
     * @param $customGameAssetId
     * @return array|CustomGameAssetEntity
     */
    public function getCustomGameAssetByCustomGameAssetId(Request $request, $gameId, $customGameAssetId, $updateChannel)
    {
        $contextXGamesAssetsManager = $request->managers->contextXGamesAssets();

        return $this->contextQueryJoin($request, EntityType::GAME, $updateChannel)
            ->filter($contextXGamesAssetsManager->filters->isActive())
            ->filter($contextXGamesAssetsManager->filters->byContextEntityId($gameId))
            ->filter($contextXGamesAssetsManager->filters->byPk($customGameAssetId))
            ->get_entity($request);
    }

    /**
     * @param Request $request
     * @param $gameModBuildId
     * @param $customGameModAssetId
     * @return array|CustomGameModBuildAssetEntity
     */
    public function getCustomGameModBuildAssetByCustomGameAssetId(Request $request, $gameModBuildId, $customGameModAssetId)
    {
        $contextXGamesAssetsManager = $request->managers->contextXGamesAssets();

        return $this->contextQueryJoin($request, EntityType::GAME_MOD_BUILD)
            ->filter($contextXGamesAssetsManager->filters->isActive())
            ->filter($contextXGamesAssetsManager->filters->byContextEntityId($gameModBuildId))
            ->filter($contextXGamesAssetsManager->filters->byPk($customGameModAssetId))
            ->get_entity($request);
    }

    /**
     * @param Request $request
     * @param $gameInstanceLogId
     * @param $gameInstanceLogAssetId
     * @param $updateChannel
     * @return array|GameInstanceLogAssetEntity
     */
    public function getGameInstanceLogAssetByGameInstanceLogAssetIdAndContext(Request $request, $gameInstanceLogId, $gameInstanceLogAssetId, $updateChannel)
    {
        $contextXGamesAssetsManager = $request->managers->contextXGamesAssets();

        return $this->contextQueryJoin($request, EntityType::GAME_INSTANCE_LOG, $updateChannel)
            ->filter($contextXGamesAssetsManager->filters->isActive())
            ->filter($contextXGamesAssetsManager->filters->byContextEntityId($gameInstanceLogId))
            ->filter($contextXGamesAssetsManager->filters->byPk($gameInstanceLogAssetId))
            ->get_entity($request);
    }

    /**
     * @param Request $request
     * @param $gameInstanceLogAssetId
     * @return array|GameInstanceLogAssetEntity
     */
    public function getGameInstanceLogAssetByGameInstanceLogAssetId(Request $request, $gameInstanceLogAssetId)
    {
        $contextXGamesAssetsManager = $request->managers->contextXGamesAssets();

        return $this->contextQueryJoin($request, EntityType::GAME_INSTANCE_LOG)
            ->filter($contextXGamesAssetsManager->filters->byPk($gameInstanceLogAssetId))
            ->get_entity($request);
    }


    /**
     * @param Request $request
     * @param $gameInstanceLogIds
     * @return array|GameInstanceLogAssetEntity[]
     */
    public function getGameInstanceLogsAssetsByGameInstanceLogIds(Request $request, $gameInstanceLogIds)
    {
        $contextXGamesAssetsManager = $request->managers->contextXGamesAssets();

        return $this->contextQueryJoin($request, EntityType::GAME_INSTANCE_LOG)
            ->filter($contextXGamesAssetsManager->filters->isActive())
            ->filter($contextXGamesAssetsManager->filters->byContextEntityId($gameInstanceLogIds))
            ->get_entities($request);
    }

    /**
     * @param Request $request
     * @param $customGameAssetIds
     * @param $gameId
     * @param $updateChannel
     * @return CustomGameAssetEntity[]
     */
    public function getCustomGameAssetsByCustomGameAssetIds(Request $request, $customGameAssetIds, $gameId = null, $updateChannel = null)
    {
        $contextXGamesAssetsManager = $request->managers->contextXGamesAssets();

        return $this->contextQueryJoin($request, EntityType::GAME, $updateChannel)
            ->filter($contextXGamesAssetsManager->filters->isActive())
            ->filter($contextXGamesAssetsManager->filters->byContextEntityId($gameId))
            ->filter($contextXGamesAssetsManager->filters->byPk($customGameAssetIds))
            ->get_entities($request);
    }

    /**
     * @param Request $request
     * @param $gameModBuildId
     * @param $customGameModAssetIds
     * @param $updateChannel
     * @return CustomGameModBuildAssetEntity[]
     */
    public function getCustomGameModBuildAssetsByCustomGameAssetIds(Request $request, $gameModBuildId, $customGameModAssetIds)
    {
        $contextXGamesAssetsManager = $request->managers->contextXGamesAssets();

        return $this->contextQueryJoin($request, EntityType::GAME_MOD_BUILD)
            ->filter($contextXGamesAssetsManager->filters->isActive())
            ->filter($contextXGamesAssetsManager->filters->byContextEntityId($gameModBuildId))
            ->filter($contextXGamesAssetsManager->filters->byPk($customGameModAssetIds))
            ->get_entities($request);
    }

    /**
     * @param Request $request
     * @param $gameBuildId
     * @param $gameBuildAssetId
     * @return GameBuildAssetEntity|array
     */
    public function getSecureGameBuildAssetByGameBuildAssetId(Request $request, $gameBuildAssetId, $userId,
                                                              $updateChannel = GamesManager::UPDATE_CHANNEL_LIVE)
    {
        return $this->getSecureGameAssetByContextEntityId($request, EntityType::GAME_BUILD, $gameBuildAssetId, $userId, $updateChannel);
    }

    /**
     * @param Request $request
     * @param $customGameAssetId
     * @param $userId
     * @return array|GameAssetEntity|GameBuildAssetEntity|CustomGameAssetEntity
     */
    public function getSecureCustomGameAssetByCustomGameAssetId(Request $request, $customGameAssetId, $userId,
                                                                $updateChannel = GamesManager::UPDATE_CHANNEL_LIVE)
    {
        return $this->getSecureGameAssetByContextEntityId($request, EntityType::GAME, $customGameAssetId, $userId, $updateChannel);
    }

    /**
     * @param Request $request
     * @param $customGameAssetId
     * @param $userId
     * @param string $updateChannel
     * @return array|CustomGameModBuildAssetEntity
     */
    public function getSecureCustomGameModBuildAssetByCustomGameAssetId(Request $request, $customGameAssetId, $userId,
                                                                $updateChannel = GamesManager::UPDATE_CHANNEL_LIVE)
    {
        return $this->getSecureGameModBuildAssetByContextEntityId($request, EntityType::GAME_MOD_BUILD, $customGameAssetId, $userId, $updateChannel);
    }


    /**
     * @param Request $request
     * @param $contextEntityTypeId
     * @param $contextEntityId
     * @param $userId
     * @return array|GameAssetEntity|GameBuildAssetEntity|CustomGameAssetEntity
     */
    protected function getSecureGameAssetByContextEntityId(Request $request, $contextEntityTypeId, $contextEntityId, $userId,
                                                           $updateChannel = GamesManager::UPDATE_CHANNEL_LIVE)
    {
        $gamesManager = $request->managers->games();
        $gameLicensesManager = $request->managers->gameLicenses();
        $contextXGamesAssetsManager = $request->managers->contextXGamesAssets();

        // Filter for joining OrganizationUsers
        $organizationsUsersManager = $request->managers->organizationsUsers();
        $joinOrganizationsUsersFilter = $this->filters->And_(
            $organizationsUsersManager->filters->isActive(),
            $organizationsUsersManager->filters->byUserId($userId)
        );

        // Filter for joining Organizations
        $organizationsManager = $request->managers->organizations();
        $joinOrganizationsFilter = $this->filters->And_(
            $organizationsManager->filters->byPk($organizationsUsersManager->field(DBField::ORGANIZATION_ID)),
            $organizationsManager->filters->isActive()
        );

        // Filter on how to join the organizations rights table
        $organizationsRightsManager = $request->managers->organizationsRights();
        $joinOrganizationsRightsFilter = $this->filters->And_(
            $organizationsRightsManager->filters->byOrganizationId($organizationsManager->createPkField())
        );

        // Filter for joining the rights. We want to join against dev and live channel permissions depending on the active build.
        $organizationsBaseRightsManager = $request->managers->organizationsBaseRights();
        $joinOrganizationsBaseRightsFilter = $this->filters->And_(
            $organizationsRightsManager->filters->byOrganizationBaseRightId($organizationsBaseRightsManager->createPkField()),
            $this->filters->Or_(
                $this->filters->And_(
                    $organizationsBaseRightsManager->filters->byName(OrganizationsBaseRightsManager::RIGHT_ORG_GAMES_CHANNELS_DEV),
                    $contextXGamesAssetsManager->filters->byUpdateChannel(GamesManager::UPDATE_CHANNEL_DEV)
                ),
                $this->filters->And_(
                    $organizationsBaseRightsManager->filters->byName(OrganizationsBaseRightsManager::RIGHT_ORG_GAMES_CHANNELS_LIVE),
                    $contextXGamesAssetsManager->filters->byUpdateChannel(GamesManager::UPDATE_CHANNEL_LIVE)
                )
            )
        );

        // Filter on how to join the organizations permissions table
        $organizationsPermissionsManager = $request->managers->organizationsPermissions();
        $joinOrganizationsPermissionsFilter = $this->filters->And_(
            $organizationsBaseRightsManager->filters->IsNotNull($organizationsBaseRightsManager->createPkField()),
            $organizationsPermissionsManager->filters->byOrganizationId($organizationsUsersManager->field(DBField::ORGANIZATION_ID)),
            $organizationsPermissionsManager->filters->byOrganizationRightId($organizationsRightsManager->createPkField()),
            $organizationsPermissionsManager->filters->byOrganizationRoleId($organizationsUsersManager->field(DBField::ORGANIZATION_ROLE_ID)),
            $organizationsPermissionsManager->filters->BitAnd(DBField::ACCESS_LEVEL, Rights::getAccessLevel(Rights::USE)),
            $organizationsPermissionsManager->filters->isActive()
        );

        // Filter for getting games by licenses
        $organizationsGamesLicensesManager = $request->managers->organizationsGamesLicenses();
        $joinOrganizationsGamesLicensesFilter = $this->filters->And_(
            $organizationsGamesLicensesManager->filters->byOrganizationId($organizationsPermissionsManager->field(DBField::ORGANIZATION_ID)),
            $organizationsGamesLicensesManager->filters->byGameId($gamesManager->createPkField()),
            $contextXGamesAssetsManager->filters->byUpdateChannel(GamesManager::UPDATE_CHANNEL_LIVE),
            $organizationsGamesLicensesManager->getActiveLicenseFilter($request->getCurrentSqlTime())
        );

        $whereFilter = $this->filters->Or_(
        // If there's a user license
            $gameLicensesManager->filters->IsNotNull($gameLicensesManager->createPkField()),
            // Or the user has permission to use a game build of the correct update channel
            $this->filters->And_(
                $gamesManager->filters->byOwnerTypeId(EntityType::ORGANIZATION),
                $organizationsUsersManager->filters->byOrganizationId($gamesManager->field(DBField::OWNER_ID)),
                $organizationsPermissionsManager->filters->IsNotNull($organizationsPermissionsManager->createPkField())
            ),
            $organizationsGamesLicensesManager->filters->IsNotNull($organizationsGamesLicensesManager->createPkField())
        );

        return $this->contextQueryJoin($request, $contextEntityTypeId, $updateChannel)
            ->inner_join($gamesManager, $this->filters->byGameId($gamesManager->createPkField()))
            ->left_join($gameLicensesManager, $gamesManager->getJoinGameLicenseFilter($request, $userId, $updateChannel))
            ->left_join($organizationsUsersManager, $joinOrganizationsUsersFilter)
            ->left_join($organizationsManager, $joinOrganizationsFilter)
            ->left_join($organizationsRightsManager, $joinOrganizationsRightsFilter)
            ->left_join($organizationsBaseRightsManager, $joinOrganizationsBaseRightsFilter)
            ->left_join($organizationsPermissionsManager, $joinOrganizationsPermissionsFilter)
            ->left_join($organizationsGamesLicensesManager, $joinOrganizationsGamesLicensesFilter)
            ->filter($contextXGamesAssetsManager->filters->isActive())
            ->filter($contextXGamesAssetsManager->filters->byPk($contextEntityId))
            ->filter($whereFilter)
            ->get_entity($request);
    }

    /**
     * @param Request $request
     * @param $contextEntityTypeId
     * @param $contextEntityId
     * @param $userId
     * @param string $updateChannel
     * @return array|CustomGameModBuildAssetEntity
     */
    protected function getSecureGameModBuildAssetByContextEntityId(Request $request, $contextEntityTypeId, $contextEntityId, $userId,
                                                           $updateChannel = GamesManager::UPDATE_CHANNEL_LIVE)
    {
        $gamesManager = $request->managers->games();
        $gamesModsManager = $request->managers->gamesMods();
        $gamesModsBuildsManager = $request->managers->gamesModsBuilds();
        $gamesModsLicensesManager = $request->managers->gamesModsLicenses();
        $contextXGamesAssetsManager = $request->managers->contextXGamesAssets();

        $joinGamesModsFilter = $this->filters->And_(
            $gamesModsManager->filters->byGameId($gamesManager->createPkField()),
            $gamesModsManager->filters->byGameModId($gamesModsBuildsManager->field(DBField::GAME_MOD_ID)),
            $gamesModsManager->filters->isActive()
        );

        // Filter for joining OrganizationUsers
        $organizationsUsersManager = $request->managers->organizationsUsers();
        $joinOrganizationsUsersFilter = $this->filters->And_(
            $organizationsUsersManager->filters->byOrganizationId($gamesModsManager->field(DBField::ORGANIZATION_ID)),
            $organizationsUsersManager->filters->isActive(),
            $organizationsUsersManager->filters->byUserId($userId)
        );

        // Filter for joining Organizations
        $organizationsManager = $request->managers->organizations();
        $joinOrganizationsFilter = $this->filters->And_(
            $organizationsManager->filters->byPk($organizationsUsersManager->field(DBField::ORGANIZATION_ID)),
            $organizationsManager->filters->isActive()
        );

        // Filter on how to join the organizations rights table
        $organizationsRightsManager = $request->managers->organizationsRights();
        $joinOrganizationsRightsFilter = $this->filters->And_(
            $organizationsRightsManager->filters->byOrganizationId($organizationsManager->createPkField())
        );

        // Filter for joining the rights. We want to join against dev and live channel permissions depending on the active build.
        $organizationsBaseRightsManager = $request->managers->organizationsBaseRights();
        $joinOrganizationsBaseRightsFilter = $this->filters->And_(
            $organizationsRightsManager->filters->byOrganizationBaseRightId($organizationsBaseRightsManager->createPkField()),
            $this->filters->Or_(
                $this->filters->And_(
                    $organizationsBaseRightsManager->filters->byName(OrganizationsBaseRightsManager::RIGHT_ORG_MODS_BUILDS_DEV),
                    $contextXGamesAssetsManager->filters->byUpdateChannel(GamesManager::UPDATE_CHANNEL_DEV)
                ),
                $this->filters->And_(
                    $organizationsBaseRightsManager->filters->byName(OrganizationsBaseRightsManager::RIGHT_ORG_MODS_BUILDS_LIVE),
                    $contextXGamesAssetsManager->filters->byUpdateChannel(GamesManager::UPDATE_CHANNEL_LIVE)
                )
            )
        );

        // Filter on how to join the organizations permissions table
        $organizationsPermissionsManager = $request->managers->organizationsPermissions();
        $joinOrganizationsPermissionsFilter = $this->filters->And_(
            $organizationsBaseRightsManager->filters->IsNotNull($organizationsBaseRightsManager->createPkField()),
            $organizationsPermissionsManager->filters->byOrganizationId($organizationsUsersManager->field(DBField::ORGANIZATION_ID)),
            $organizationsPermissionsManager->filters->byOrganizationRightId($organizationsRightsManager->createPkField()),
            $organizationsPermissionsManager->filters->byOrganizationRoleId($organizationsUsersManager->field(DBField::ORGANIZATION_ROLE_ID)),
            $organizationsPermissionsManager->filters->BitAnd(DBField::ACCESS_LEVEL, Rights::getAccessLevel(Rights::USE)),
            $organizationsPermissionsManager->filters->isActive()
        );

        $whereFilter = $this->filters->Or_(
        // If there's a user license
            $gamesModsLicensesManager->filters->IsNotNull($gamesModsLicensesManager->createPkField()),
            // Or the user has permission to use a game build of the correct update channel
            $organizationsPermissionsManager->filters->IsNotNull($organizationsPermissionsManager->createPkField())
        );

        return $this->contextQueryJoin($request, $contextEntityTypeId, $updateChannel)
            ->inner_join($gamesModsBuildsManager, $gamesModsBuildsManager->filters->byGameModBuildId($contextXGamesAssetsManager->field(DBField::CONTEXT_ENTITY_ID)))
            ->inner_join($gamesManager, $this->filters->byGameId($gamesManager->createPkField()))
            ->inner_join($gamesModsManager, $joinGamesModsFilter)
            ->left_join($gamesModsLicensesManager, $gamesModsManager->getJoinGameModsLicensesFilter($request, $userId, $updateChannel))
            ->left_join($organizationsUsersManager, $joinOrganizationsUsersFilter)
            ->left_join($organizationsManager, $joinOrganizationsFilter)
            ->left_join($organizationsRightsManager, $joinOrganizationsRightsFilter)
            ->left_join($organizationsBaseRightsManager, $joinOrganizationsBaseRightsFilter)
            ->left_join($organizationsPermissionsManager, $joinOrganizationsPermissionsFilter)
            ->filter($contextXGamesAssetsManager->filters->isActive())
            ->filter($contextXGamesAssetsManager->filters->byPk($contextEntityId))
            ->filter($gamesModsManager->filters->isActive())
            ->filter($gamesModsBuildsManager->filters->isActive())
            ->filter($whereFilter)
            ->get_entity($request);
    }

    /**
     * @param Request $request
     * @param $gameControllerIds
     * @return GameControllerAssetEntity[]
     */
    public function getGameControllerAssetsByGameControllerIds(Request $request, $gameControllerIds)
    {
        $contextXGamesAssetsManager = $request->managers->contextXGamesAssets();

        return $this->contextQueryJoin($request, EntityType::GAME_CONTROLLER)
            ->filter($contextXGamesAssetsManager->filters->byContextEntityId($gameControllerIds))
            ->filter($contextXGamesAssetsManager->filters->isActive())
            ->filter($this->filters->isActive())
            ->sort_asc($contextXGamesAssetsManager->field(DBField::FOLDER_PATH))
            ->sort_asc($contextXGamesAssetsManager->field(DBField::EXTENSION))
            ->sort_asc($contextXGamesAssetsManager->field(DBField::FILENAME))
            ->get_entities($request);
    }

    /**
     * @param Request $request
     * @param $gameControllerIds
     * @return array|GameControllerAssetEntity[]
     */
    public function getCachedGameControllerAssetsByGameControllerIds(Request $request, $gameControllerIds)
    {
        $contextXGamesAssetsManager = $request->managers->contextXGamesAssets();

        $queryBuilder = $this->contextQueryJoin($request, EntityType::GAME_CONTROLLER)
            ->filter($contextXGamesAssetsManager->filters->byContextEntityId($gameControllerIds))
            ->filter($contextXGamesAssetsManager->filters->isActive())
            ->filter($this->filters->isActive())
            ->sort_asc($contextXGamesAssetsManager->field(DBField::FOLDER_PATH))
            ->sort_asc($contextXGamesAssetsManager->field(DBField::EXTENSION))
            ->sort_asc($contextXGamesAssetsManager->field(DBField::FILENAME));

        return $this->getCachableEntitiesByField(
            $request,
            DBField::GAME_CONTROLLER_ID,
            VField::GAME_CONTROLLER_ASSET_ID,
            $gameControllerIds,
            $this->getGameControllerIdCacheKeyGenerator(),
            $queryBuilder,
            ONE_HOUR
        );
    }

    /**
     * @param Request $request
     * @param $gameBuildIds
     * @return GameBuildAssetEntity[]
     */
    public function getCachedGameBuildAssetsByGameBuildId(Request $request, $gameBuildIds)
    {
        $contextXGamesAssetsManager = $request->managers->contextXGamesAssets();

        $queryBuilder = $this->contextQueryJoin($request, EntityType::GAME_BUILD)
            ->filter($contextXGamesAssetsManager->filters->byContextEntityId($gameBuildIds))
            ->filter($contextXGamesAssetsManager->filters->isActive())
            ->filter($this->filters->isActive())
            ->sort_asc($contextXGamesAssetsManager->field(DBField::FOLDER_PATH))
            ->sort_asc($contextXGamesAssetsManager->field(DBField::EXTENSION))
            ->sort_asc($contextXGamesAssetsManager->field(DBField::FILENAME));

        return $this->getCachableEntitiesByField(
            $request,
            DBField::GAME_BUILD_ID,
            VField::GAME_BUILD_ASSET_ID,
            $gameBuildIds,
            $this->getGameBuildIdCacheKeyGenerator(),
            $queryBuilder,
            ONE_HOUR
        );
    }

    /**
     * @param Request $request
     * @param $gameBuildIds
     * @return GameBuildAssetEntity[]
     */
    public function getGameBuildAssetsByGameBuildId(Request $request, $gameBuildIds)
    {
        $contextXGamesAssetsManager = $request->managers->contextXGamesAssets();

        return $this->contextQueryJoin($request, EntityType::GAME_BUILD)
            ->filter($contextXGamesAssetsManager->filters->byContextEntityId($gameBuildIds))
            ->filter($contextXGamesAssetsManager->filters->isActive())
            ->filter($this->filters->isActive())
            ->sort_asc($contextXGamesAssetsManager->field(DBField::FOLDER_PATH))
            ->sort_asc($contextXGamesAssetsManager->field(DBField::EXTENSION))
            ->sort_asc($contextXGamesAssetsManager->field(DBField::FILENAME))
            ->get_entities($request);
    }

    /**
     * @param Request $request
     * @param $gameModBuildIds
     * @return CustomGameModBuildAssetEntity[]
     */
    public function getGameModBuildAssetsByGameModBuildIds(Request $request, $gameModBuildIds)
    {
        $contextXGamesAssetsManager = $request->managers->contextXGamesAssets();
        $gamesModsActiveCustomAssetsManager = $request->managers->gamesModsActiveCustomAssets();

        $joinGamesModsActiveCustomAssetsFilter = $this->filters->And_(
            $gamesModsActiveCustomAssetsManager->filters->byGameModBuildId($contextXGamesAssetsManager->field(DBField::CONTEXT_ENTITY_ID)),
            $gamesModsActiveCustomAssetsManager->filters->byContextXGameAssetId($contextXGamesAssetsManager->createPkField()),
            $gamesModsActiveCustomAssetsManager->filters->isActive()
        );

        return $this->contextQueryJoin($request, EntityType::GAME_MOD_BUILD)
            ->inner_join($gamesModsActiveCustomAssetsManager, $joinGamesModsActiveCustomAssetsFilter)
            ->fields($gamesModsActiveCustomAssetsManager->field(DBField::IS_PUBLIC))
            ->filter($contextXGamesAssetsManager->filters->byContextEntityId($gameModBuildIds))
            ->filter($contextXGamesAssetsManager->filters->isActive())
            ->filter($this->filters->isActive())
            ->sort_asc($contextXGamesAssetsManager->field(DBField::FOLDER_PATH))
            ->sort_asc($contextXGamesAssetsManager->field(DBField::EXTENSION))
            ->sort_asc($contextXGamesAssetsManager->field(DBField::FILENAME))
            ->get_entities($request);
    }


    /**
     * @param Request $request
     * @param $gameIds
     * @param $userId
     * @return CustomGameAssetEntity[]
     */
    public function getActivePlayableCustomGameAssetsByGameIds(Request $request, $gameIds, $userId)
    {
        $gamesManager = $request->managers->games();
        $contextXGamesAssetsManager = $request->managers->contextXGamesAssets();
        $gamesActiveCustomAssetsManager = $request->managers->gamesActiveCustomAssets();
        $gameLicensesManager = $request->managers->gameLicenses();
        $gamesActiveBuildsManager = $request->managers->gamesActiveBuilds();

        $updateChannelJoinFilter = $contextXGamesAssetsManager->field(DBField::UPDATE_CHANNEL);

        $joinGamesActiveBuildsFilter = $this->filters->And_(
            $gamesActiveBuildsManager->filters->byGameId($gamesManager->createPkField()),
            $gamesActiveBuildsManager->filters->isActive(),
            $gamesActiveBuildsManager->filters->byUpdateChannel($updateChannelJoinFilter)
        );

        $joinGamesActiveCustomAssetsFilter = $this->filters->And_(
            $gamesActiveCustomAssetsManager->filters->byGameBuildId($gamesActiveBuildsManager->field(DBField::GAME_BUILD_ID)),
            $gamesActiveCustomAssetsManager->filters->isActive(),
            $gamesActiveCustomAssetsManager->filters->byContextXGameAssetId($contextXGamesAssetsManager->createPkField())
        );

        $expireDt = new DateTime();
        $joinGameLicensesFilter = $this->filters->And_(
            $gameLicensesManager->filters->byGameId($gamesManager->createPkField()),
            $gameLicensesManager->filters->byUserId($userId),
            $gameLicensesManager->filters->isActive(),
            $gameLicensesManager->filters->byUpdateChannel($updateChannelJoinFilter),
            $this->filters->Or_(
                $gameLicensesManager->filters->IsNull(DBField::END_TIME),
                $this->filters->And_(
                    $gameLicensesManager->filters->IsNotNull(DBField::END_TIME),
                    $gameLicensesManager->filters->Gt(DBField::END_TIME, $expireDt->format(SQL_DATETIME))
                )
            )
        );

        $whereFilter = $this->filters->Or_(
            $gameLicensesManager->filters->IsNotNull($gameLicensesManager->createPkField()),
            $this->filters->And_(
                $gamesManager->filters->byOwnerTypeId(EntityType::USER),
                $gamesManager->filters->byOwnerId($userId)
            )
        );

        /** @var CustomGameAssetEntity[] $activePlayableCustomGameAssets */
        $activePlayableCustomGameAssets = $this->contextQueryJoin($request, EntityType::GAME)
            ->inner_join($gamesManager, $gamesManager->filters->byPk($this->field(DBField::GAME_ID)))
            ->inner_join($gamesActiveBuildsManager, $joinGamesActiveBuildsFilter)
            ->inner_join($gamesActiveCustomAssetsManager, $joinGamesActiveCustomAssetsFilter)
            ->left_join($gameLicensesManager, $joinGameLicensesFilter)
            ->fields($gamesActiveCustomAssetsManager->field(DBField::IS_PUBLIC))
            ->filter($gamesManager->filters->byPk($gameIds))
            ->filter($whereFilter)
            ->sort_asc($contextXGamesAssetsManager->field(DBField::FOLDER_PATH))
            ->sort_asc($contextXGamesAssetsManager->field(DBField::EXTENSION))
            ->sort_asc($contextXGamesAssetsManager->field(DBField::FILENAME))
            ->get_entities($request);

        return $activePlayableCustomGameAssets;
    }

    /**
     * @param $gameId
     * @param $updateChannel
     * @param $gameBuildId
     * @return string
     */
    public function generateActivePublicCustomGameAssetsCacheKey($gameId, $updateChannel, $gameBuildId)
    {
        return self::GNS_KEY_PREFIX . ".active-public-custom-assets.{$gameId}-{$updateChannel}-{$gameBuildId}";
    }

    /**
     * @param $gameId
     * @param $gameBuildId
     * @param $userId
     * @return string
     */
    public function generateUserGameBuildDownloadCacheKey($gameId, $gameBuildId, $userId)
    {
        return self::GNS_KEY_PREFIX. ".game-build-downloads.{$gameId}.{$gameBuildId}.{$userId}";
    }

    /**
     * @param $gameModBuildId
     * @param $updateChannel
     * @return string
     */
    public function generateActivePublicCustomGameModAssetsCacheKey($gameModBuildId, $updateChannel)
    {
        return self::GNS_KEY_PREFIX . ".active-public-custom-mod-assets.{$updateChannel}-{$gameModBuildId}";
    }

    /**
     * @param Request $request
     * @param $gameId
     * @return CustomGameAssetEntity[]
     */
    public function getActivePublicCustomGameAssetsByGameBuild(Request $request, $gameId, $updateChannel, $gameBuildId)
    {
        $contextXGamesAssetsManager = $request->managers->contextXGamesAssets();
        $gamesActiveCustomAssetsManager = $request->managers->gamesActiveCustomAssets();

        $joinGamesActiveCustomAssetsFilter = $this->filters->And_(
            $gamesActiveCustomAssetsManager->filters->byGameId($this->field(DBField::GAME_ID)),
            $gamesActiveCustomAssetsManager->filters->byContextXGameAssetId($contextXGamesAssetsManager->createPkField()),
            $gamesActiveCustomAssetsManager->filters->isActive()
        );

        $queryBuilder = $this->contextQueryJoin($request, EntityType::GAME, $updateChannel)
            ->inner_join($gamesActiveCustomAssetsManager, $joinGamesActiveCustomAssetsFilter)
            ->fields($gamesActiveCustomAssetsManager->field(DBField::IS_PUBLIC))
            ->filter($contextXGamesAssetsManager->filters->byContextEntityId($gameId))
            ->filter($gamesActiveCustomAssetsManager->filters->byGameBuildId($gameBuildId))
            ->filter($contextXGamesAssetsManager->filters->isActive())
            ->filter($gamesActiveCustomAssetsManager->filters->isPublic())
            ->filter($this->filters->isActive())
            ->sort_asc($contextXGamesAssetsManager->field(DBField::FOLDER_PATH))
            ->sort_asc($contextXGamesAssetsManager->field(DBField::EXTENSION))
            ->sort_asc($contextXGamesAssetsManager->field(DBField::FILENAME));

        if ($updateChannel == GamesManager::UPDATE_CHANNEL_LIVE)
            $queryBuilder->cache($this->generateActivePublicCustomGameAssetsCacheKey($gameId, $updateChannel, $gameBuildId), ONE_HOUR);

        /** @var CustomGameAssetEntity[] $customGameAssets */
        $customGameAssets = $queryBuilder->get_entities($request);

        foreach ($customGameAssets as $customGameAsset) {
            $publicUrl = $this->generatePublicCustomAssetUrl(
                $request,
                $customGameAsset->getGameId(),
                $customGameAsset->getUpdateChannel(),
                $customGameAsset->getSlug(),
                $customGameAsset->getCustomGameAssetId(),
                $customGameAsset->getFileName(),
                $gameBuildId
            );
            $customGameAsset->updateField(VField::PUBLIC_URL, $publicUrl);
        }

        return $customGameAssets;
    }

    /**
     * @param Request $request
     * @param $gameModBuildId
     * @param $updateChannel
     * @return CustomGameModBuildAssetEntity[]
     */
    public function getActivePublicCustomGameModAssetsByGameModBuildId(Request $request, $gameModBuildId, $updateChannel)
    {
        $contextXGamesAssetsManager = $request->managers->contextXGamesAssets();
        $gamesModsActiveCustomAssetsManager = $request->managers->gamesModsActiveCustomAssets();

        $joinGamesActiveCustomAssetsFilter = $this->filters->And_(
            $gamesModsActiveCustomAssetsManager->filters->byGameModBuildId($contextXGamesAssetsManager->field(DBField::GAME_MOD_BUILD_ID)),
            $gamesModsActiveCustomAssetsManager->filters->byContextXGameAssetId($contextXGamesAssetsManager->createPkField()),
            $gamesModsActiveCustomAssetsManager->filters->isPublic(),
            $gamesModsActiveCustomAssetsManager->filters->isActive()
        );

        $queryBuilder = $this->contextQueryJoin($request, EntityType::GAME_MOD_BUILD)
            ->inner_join($gamesModsActiveCustomAssetsManager, $joinGamesActiveCustomAssetsFilter)
            ->fields($gamesModsActiveCustomAssetsManager->field(DBField::IS_PUBLIC))
            ->filter($contextXGamesAssetsManager->filters->byContextEntityId($gameModBuildId))
            ->filter($contextXGamesAssetsManager->filters->isActive())
            ->filter($this->filters->isActive())
            ->sort_asc($contextXGamesAssetsManager->field(DBField::FOLDER_PATH))
            ->sort_asc($contextXGamesAssetsManager->field(DBField::EXTENSION))
            ->sort_asc($contextXGamesAssetsManager->field(DBField::FILENAME));

        if ($updateChannel == GamesManager::UPDATE_CHANNEL_LIVE)
            $queryBuilder->cache($this->generateActivePublicCustomGameModAssetsCacheKey($gameModBuildId, $updateChannel), ONE_HOUR);

        /** @var CustomGameModBuildAssetEntity[] $customGameAssets */
        $customGameAssets = $queryBuilder->get_entities($request);

        foreach ($customGameAssets as $customGameAsset) {
            $publicUrl = $this->generatePublicCustomModAssetUrl(
                $request,
                $customGameAsset->getGameId(),
                $customGameAsset->getUpdateChannel(),
                $customGameAsset->getSlug(),
                $customGameAsset->getCustomGameAssetId(),
                $customGameAsset->getFileName(),
                $gameModBuildId
            );
            $customGameAsset->updateField(VField::PUBLIC_URL, $publicUrl);
        }

        return $customGameAssets;
    }


    /**
     * @param Request $request
     * @param $md5
     * @param null $gameId
     * @return array|GameAssetEntity
     */
    public function getGameAssetByMd5(Request $request, $md5, $gameId = null)
    {
        $queryBuilder = $this->query($request->db)
            ->filter($this->filters->byMd5($md5));

        if ($gameId)
            $queryBuilder->filter($this->filters->byGameId($gameId));

        return $queryBuilder->get_entity($request);
    }


    /**
     * @param Request $request
     * @param $gameId
     * @param $md5
     * @param $mimeType
     * @param $bucketPath
     * @param $fileName
     * @param $fileSize
     * @return GameAssetEntity
     */
    public function createNewGameAsset(Request $request, $gameId, $md5, $mimeType, $bucket, $bucketPath, $fileSize)
    {
        $data = [
            DBField::GAME_ID => $gameId,
            DBField::MD5 => $md5,
            DBField::MIME_TYPE => $mimeType,
            DBField::BUCKET => $bucket,
            DBField::BUCKET_PATH => $bucketPath,
            DBField::COMPUTED_FILENAME => '',
            DBField::FILE_SIZE => $fileSize
        ];

        /** @var GameAssetEntity $gameAsset */
        $gameAsset = $this->query($request->db)->createNewEntity($request, $data);

        $computedFilename = "{$gameAsset->getPk()}_{$gameAsset->getMd5()}";

        $gameAsset->updateField(DBField::COMPUTED_FILENAME, $computedFilename)->saveEntityToDb($request);

        return $gameAsset;
    }


    /**
     * @param Request $request
     * @param $uploadId
     * @param $gameId
     * @return array|GameAssetEntity
     */
    public function handleGameAssetUpload(Request $request, $sourceFile, $md5, $gameId, $bucket)
    {

        $fileExists = false;

        if ($request->config['aws']['bucket_prefix'])
            $bucket = $request->config['aws']['bucket_prefix'].$bucket;

        $bucketPath = $this->buildGameAssetBucketPath($gameId);

        if (!$gameAsset = $this->getGameAssetByMd5($request, $md5, $gameId)) {

            $mimeType = mime_content_type($sourceFile);
            $fileSize = filesize($sourceFile);

            $gameAsset = $this->createNewGameAsset(
                $request,
                $gameId,
                $md5,
                $mimeType,
                $bucket,
                $bucketPath,
                $fileSize
            );
        }

        if (is_file($sourceFile)) {

            if (!$fileExists) {
                if (!$gameAsset->is_active())
                    $this->reActivateEntity($request, $gameAsset);

                try {
                    $request->s3->uploadFile($gameAsset->getBucket(), $gameAsset->getBucketKey(), $sourceFile, $gameAsset->getMimeType());

                } catch (\Aws\S3\Exception\S3Exception $e) {

                    $this->deactivateEntity($request, $gameAsset);

                    throw $e;
                } catch (Exception $e) {
                    $this->deactivateEntity($request, $gameAsset);

                    throw $e;
                }

            }

        }

        return $gameAsset;
    }


    /**
     * @param Request $request
     * @param $uploadId
     * @param $gameId
     * @param $gameControllerId
     * @return array|GameControllerAssetEntity
     */
    public function handleGameControllerAssetUpload(Request $request, $sourceFile, $md5, $fileName, $gameId, $gameControllerId,
                                                    $folderPath, $updateChannel, $uploadId = null)
    {
        $contextXGamesAssetsManager = $request->managers->contextXGamesAssets();

        try {

            $gameAsset = $this->handleGameAssetUpload(
                $request,
                $sourceFile,
                $md5,
                $gameId,
                S3::BUCKET_GAME_CONTROLLER_ASSETS
            );

        } catch (\Aws\S3\Exception\S3Exception $e) {
            if ($uploadId)
                UploadsHelper::delete_upload($uploadId);
            throw $e;
        } catch (Exception $e) {

            if ($uploadId)
                UploadsHelper::delete_upload($uploadId);

            throw $e;
        }

        if ($uploadId)
            UploadsHelper::delete_upload($uploadId);

        return $contextXGamesAssetsManager->linkGameAssetToGameController(
            $request,
            $gameAsset->getPk(),
            $gameControllerId,
            $folderPath,
            $fileName,
            $updateChannel
        );
    }

    /**
     * @param Request $request
     * @param $uploadId
     * @param $gameId
     * @param $gameBuildId
     * @return array|GameBuildAssetEntity
     */
    public function handleGameBuildAssetUpload(Request $request, $sourceFile, $md5, $fileName, $gameId, $gameBuildId,
                                               $folderPath, $updateChannel, $uploadId = null)
    {
        $contextXGamesAssetsManager = $request->managers->contextXGamesAssets();

        try {

            $gameAsset = $this->handleGameAssetUpload(
                $request,
                $sourceFile,
                $md5,
                $gameId,
                S3::BUCKET_GAME_ASSETS
            );

        } catch (\Aws\S3\Exception\S3Exception $e) {
            if ($uploadId)
                UploadsHelper::delete_upload($uploadId);
            throw $e;
        } catch (Exception $e) {

            if ($uploadId)
                UploadsHelper::delete_upload($uploadId);

            throw $e;
        }

        if ($uploadId)
            UploadsHelper::delete_upload($uploadId);

        return $contextXGamesAssetsManager->linkGameAssetToGameBuild(
            $request,
            $gameAsset->getPk(),
            $gameBuildId,
            $folderPath,
            $fileName,
            $updateChannel
        );
    }

    /**
     * @param Request $request
     * @param $gameId
     * @param $slug
     * @return CustomGameAssetEntity|array
     */
    public function getCustomGameAssetBySlug(Request $request, $gameId, $updateChannel, $slug)
    {
        $contextXGamesAssetsManager = $request->managers->contextXGamesAssets();

        return $this->contextQueryJoin($request, EntityType::GAME, $updateChannel)
            ->filter($contextXGamesAssetsManager->filters->byContextEntityId($gameId))
            ->filter($contextXGamesAssetsManager->filters->byUpdateChannel($updateChannel))
            ->filter($contextXGamesAssetsManager->filters->bySlug($slug))
            ->filter($contextXGamesAssetsManager->filters->isActive())
            ->get_entity($request);
    }

    /**
     * @param Request $request
     * @param $gameId
     * @param $slug
     * @return CustomGameAssetEntity[]
     */
    public function getCustomGameAssetsHistoryBySlug(Request $request, $gameId, $updateChannel, $slug)
    {
        $contextXGamesAssetsManager = $request->managers->contextXGamesAssets();

        /** @var CustomGameAssetEntity[] $customGameAssets */
        $customGameAssets = $this->contextQueryJoin($request, EntityType::GAME, $updateChannel)
            ->filter($contextXGamesAssetsManager->filters->byContextEntityId($gameId))
            ->filter($contextXGamesAssetsManager->filters->bySlug($slug))
            ->filter($contextXGamesAssetsManager->filters->isActive())
            ->sort_desc($contextXGamesAssetsManager->createPkField())
            ->get_entities($request);

        $this->postProcessCustomGameAssets($request, $customGameAssets);

        return $customGameAssets;
    }

    /**
     * @param Request $request
     * @param $gameModBuildId
     * @param $slug
     * @return CustomGameModBuildAssetEntity[]
     */
    public function getCustomGameModBuildAssetsHistoryBySlug(Request $request, $gameModBuildId, $slug)
    {
        $contextXGamesAssetsManager = $request->managers->contextXGamesAssets();

        /** @var CustomGameModBuildAssetEntity[] $customGameAssets */
        $customGameAssets = $this->contextQueryJoin($request, EntityType::GAME_MOD_BUILD)
            ->filter($contextXGamesAssetsManager->filters->byContextEntityId($gameModBuildId))
            ->filter($contextXGamesAssetsManager->filters->bySlug($slug))
            ->filter($contextXGamesAssetsManager->filters->isActive())
            ->sort_desc($contextXGamesAssetsManager->createPkField())
            ->get_entities($request);

        $this->postProcessCustomGameAssets($request, $customGameAssets);

        return $customGameAssets;
    }

    /**
     * @param Request $request
     * @param CustomGameAssetEntity[]|CustomGameAssetEntity|CustomGameModBuildAssetEntity|CustomGameModBuildAssetEntity[] $customGameAssets
     * @throws ObjectNotFound
     */
    protected function postProcessCustomGameAssets(Request $request, $customGameAssets)
    {
        $usersManager = $request->managers->users();

        if ($customGameAssets) {
            if ($customGameAssets instanceof GameAssetEntity)
                $customGameAssets = [$customGameAssets];

            $userIds = unique_array_extract(DBField::USER_ID, $customGameAssets);

            $users = $usersManager->getUsersByIds($request, $userIds);
            /** @var UserEntity[] $users */
            $users = array_index($users, $usersManager->getPkField());

            foreach ($customGameAssets as $customGameAsset) {
                $customGameAsset->updateField(VField::USER, $users[$customGameAsset->getUserId()]);
            }
        }
    }

    /**
     * @param Request $request
     * @param $sourceFile
     * @param $md5
     * @param $fileName
     * @param $gameId
     * @param $folderPath
     * @param null $uploadId
     * @return array|CustomGameAssetEntity
     */
    public function handleCustomGameAssetUpload(Request $request, $sourceFile, $md5, $fileName, $gameId, $folderPath,
                                                $updateChannel, $slug, $uploadId = null)
    {
        $contextXGamesAssetsManager = $request->managers->contextXGamesAssets();

        try {

            $gameAsset = $this->handleGameAssetUpload(
                $request,
                $sourceFile,
                $md5,
                $gameId,
                S3::BUCKET_GAME_ASSETS
            );

        } catch (\Aws\S3\Exception\S3Exception $e) {
            if ($uploadId)
                UploadsHelper::delete_upload($uploadId);
            throw $e;
        } catch (Exception $e) {

            if ($uploadId)
                UploadsHelper::delete_upload($uploadId);

            throw $e;
        }

        if ($uploadId)
            UploadsHelper::delete_upload($uploadId);

        return $contextXGamesAssetsManager->linkGameAssetToGame(
            $request,
            $gameAsset->getPk(),
            $gameId,
            $folderPath,
            $fileName,
            $updateChannel,
            $slug
        );
    }

    /**
     * @param Request $request
     * @param $sourceFile
     * @param $md5
     * @param $fileName
     * @param $gameId
     * @param $gameModBuildId
     * @param $folderPath
     * @param $updateChannel
     * @param $slug
     * @param null $uploadId
     * @return array|CustomGameModBuildAssetEntity
     */
    public function handleCustomGameModBuildAssetUpload(Request $request, $sourceFile, $md5, $fileName, $gameId, $gameModBuildId,
                                                        $folderPath, $updateChannel, $slug, $uploadId = null)
    {
        $contextXGamesAssetsManager = $request->managers->contextXGamesAssets();

        try {

            $gameAsset = $this->handleGameAssetUpload(
                $request,
                $sourceFile,
                $md5,
                $gameId,
                S3::BUCKET_GAME_ASSETS
            );

        } catch (\Aws\S3\Exception\S3Exception $e) {
            if ($uploadId)
                UploadsHelper::delete_upload($uploadId);
            throw $e;
        } catch (Exception $e) {

            if ($uploadId)
                UploadsHelper::delete_upload($uploadId);

            throw $e;
        }

        if ($uploadId)
            UploadsHelper::delete_upload($uploadId);

        return $contextXGamesAssetsManager->linkGameModAssetToGameModBuild(
            $request,
            $gameAsset->getPk(),
            $gameModBuildId,
            $folderPath,
            $fileName,
            $updateChannel,
            $slug
        );
    }

    /**
     * @param Request $request
     * @param $sourceFile
     * @param $md5
     * @param $fileName
     * @param $gameId
     * @param $folderPath
     * @param $updateChannel
     * @param $slug
     * @param null $uploadId
     * @return array|GameInstanceLogAssetEntity
     */
    public function handleGameInstanceLogAssetUpload(Request $request, $sourceFile, $md5, $fileName, $gameId, $folderPath,
                                                $updateChannel, $gameInstanceLogId)
    {
        $contextXGamesAssetsManager = $request->managers->contextXGamesAssets();

        $gameAsset = $this->handleGameAssetUpload(
            $request,
            $sourceFile,
            $md5,
            $gameId,
            S3::BUCKET_GAME_INSTANCE_LOGS
        );

        return $contextXGamesAssetsManager->linkGameInstanceLogAssetToGameInstanceLog(
            $request,
            $gameAsset->getPk(),
            $gameInstanceLogId,
            $folderPath,
            $fileName,
            $updateChannel,
            null
        );
    }

    /**
     * @param Request $request
     * @param GameBuildEntity $oldGameBuild
     * @param GameBuildEntity $newGameBuild
     */
    public function cloneGameBuildAssets(Request $request, GameBuildEntity $oldGameBuild, GameBuildEntity $newGameBuild)
    {
        $contextXGamesAssetsManager = $request->managers->contextXGamesAssets();

        foreach ($oldGameBuild->getGameAssets() as $gameBuildAsset) {
            $liveGameBuildAsset = $contextXGamesAssetsManager->linkGameAssetToGameBuild(
                $request,
                $gameBuildAsset->getPk(),
                $newGameBuild->getPk(),
                $gameBuildAsset->getFolderPath(),
                $gameBuildAsset->getFileName(),
                $newGameBuild->getUpdateChannel()
            );
            $newGameBuild->setGameAsset($liveGameBuildAsset);
        }
    }
}


class ContextXGamesAssetsManager extends BaseEntityManager
{
    protected $entityClass = ContextXGameAssetEntity::class;
    protected $table = Table::ContextXGameAsset;
    protected $table_alias = TableAlias::ContextXGameAsset;
    protected $pk = DBField::CONTEXT_X_GAME_ASSET_ID;

    protected $foreign_managers = [
        GamesAssetsManager::class => DBField::GAME_ASSET_ID
    ];

    public static $fields = [
        DBField::CONTEXT_X_GAME_ASSET_ID,
        DBField::CONTEXT_ENTITY_TYPE_ID,
        DBField::CONTEXT_ENTITY_ID,
        DBField::GAME_ASSET_ID,
        DBField::UPDATE_CHANNEL,
        DBField::SLUG,
        DBField::USER_ID,
        DBField::FOLDER_PATH,
        DBField::FILENAME,
        DBField::EXTENSION,
        DBField::IS_ACTIVE,
        DBField::CREATED_BY,
        DBField::MODIFIED_BY,
        DBField::DELETED_BY
    ];

    public $removed_json_fields = [
        VField::LOCAL_CREATE_TIME,
        DBField::COMPUTED_FILENAME,
    ];


    /**
     * @param Request $request
     * @param $gameAssetId
     * @param $contextEntityTypeId
     * @param $contextEntityId
     * @return ContextXGameAssetEntity
     */
    protected function linkGameAssetToContextEntity(Request $request, $gameAssetId, $contextEntityTypeId, $contextEntityId, $folderPath, $fileName, $updateChannel, $slug = null, $createTime = null)
    {
        $extension = FilesToolkit::get_file_extension($fileName);

        $data = [
            DBField::CONTEXT_ENTITY_TYPE_ID => $contextEntityTypeId,
            DBField::CONTEXT_ENTITY_ID => $contextEntityId,
            DBField::GAME_ASSET_ID => $gameAssetId,
            DBField::UPDATE_CHANNEL => $updateChannel,
            DBField::USER_ID => $request->user->id,
            DBField::SLUG => $slug,
            DBField::FOLDER_PATH => $folderPath,
            DBField::FILENAME => $fileName,
            DBField::EXTENSION => $extension,
            DBField::IS_ACTIVE => 1
        ];

        if ($createTime)
            $data[DBField::CREATE_TIME] = $createTime;

        /** @var ContextXGameAssetEntity $contextXGameAsset */
        $contextXGameAssetLink = $this->query($request->db)->createNewEntity($request, $data);

        return $contextXGameAssetLink;
    }

    /**
     * @param Request $request
     * @param $contextXGameAssetEntityId
     */
    public function deactivateLink(Request $request, $contextXGameAssetEntityId)
    {
        $deactivationData = [
            DBField::IS_ACTIVE => 0,
            DBField::MODIFIED_BY => $request->requestId,
            DBField::DELETED_BY => $request->requestId
        ];

        $this->query($request->db)
            ->filter($this->filters->byPk($contextXGameAssetEntityId))
            ->update($deactivationData);
    }


    /**
     * @param Request $request
     * @param $gameAssetId
     * @param $gameBuildId
     * @return array|GameBuildAssetEntity
     */
    public function linkGameAssetToGameBuild(Request $request, $gameAssetId, $gameBuildId, $folderPath, $fileName, $updateChannel)
    {
        $gamesAssetsManager = $request->managers->gamesAssets();

        $contextXGameAssetLink = $this->linkGameAssetToContextEntity(
            $request,
            $gameAssetId,
            EntityType::GAME_BUILD,
            $gameBuildId,
            $folderPath,
            $fileName,
            $updateChannel
        );

        return $gamesAssetsManager->getGameBuildAssetByGameBuildAssetId($request, $contextXGameAssetLink->getPk());
    }

    /**
     * @param Request $request
     * @param $gameAssetId
     * @param $gameControllerId
     * @return array|GameControllerAssetEntity
     */
    public function linkGameAssetToGameController(Request $request, $gameAssetId, $gameControllerId, $folderPath, $fileName,
                                                  $updateChannel)
    {
        $gamesAssetsManager = $request->managers->gamesAssets();

        $contextXGameAssetLink = $this->linkGameAssetToContextEntity(
            $request,
            $gameAssetId,
            EntityType::GAME_CONTROLLER,
            $gameControllerId,
            $folderPath,
            $fileName,
            $updateChannel
        );

        return $gamesAssetsManager->getGameControllerAssetByGameControllerAssetId($request, $contextXGameAssetLink->getPk());
    }

    /**
     * @param Request $request
     * @param $gameAssetId
     * @param $gameId
     * @param $folderPath
     * @param $fileName
     * @param null $slug
     * @return array|CustomGameAssetEntity
     */
    public function linkGameAssetToGame(Request $request, $gameAssetId, $gameId, $folderPath, $fileName, $updateChannel,
                                        $slug = null, $createTime = null)
    {
        $gamesAssetsManager = $request->managers->gamesAssets();

        $contextXGameAssetLink = $this->linkGameAssetToContextEntity(
            $request,
            $gameAssetId,
            EntityType::GAME,
            $gameId,
            $folderPath,
            $fileName,
            $updateChannel,
            $slug,
            $createTime
        );

        return $gamesAssetsManager->getCustomGameAssetByCustomGameAssetId(
            $request,
            $gameId,
            $contextXGameAssetLink->getPk(),
            $updateChannel
        );
    }

    /**
     * @param Request $request
     * @param $gameAssetId
     * @param $gameModBuildId
     * @param $folderPath
     * @param $fileName
     * @param $updateChannel
     * @param null $slug
     * @param null $createTime
     * @return array|CustomGameModBuildAssetEntity
     */
    public function linkGameModAssetToGameModBuild(Request $request, $gameAssetId, $gameModBuildId, $folderPath, $fileName, $updateChannel,
                                                   $slug = null, $createTime = null)
    {
        $gamesAssetsManager = $request->managers->gamesAssets();

        $contextXGameAssetLink = $this->linkGameAssetToContextEntity(
            $request,
            $gameAssetId,
            EntityType::GAME_MOD_BUILD,
            $gameModBuildId,
            $folderPath,
            $fileName,
            $updateChannel,
            $slug,
            $createTime
        );

        return $gamesAssetsManager->getCustomGameModBuildAssetByCustomGameAssetId(
            $request,
            $gameModBuildId,
            $contextXGameAssetLink->getPk()
        );
    }

    /**
     * @param Request $request
     * @param $gameAssetId
     * @param $gameInstanceLogId
     * @param $folderPath
     * @param $fileName
     * @param $updateChannel
     * @param null $slug
     * @param null $createTime
     * @return array|GameInstanceLogAssetEntity
     */
    public function linkGameInstanceLogAssetToGameInstanceLog(Request $request, $gameAssetId, $gameInstanceLogId, $folderPath, $fileName, $updateChannel,
                                                              $slug = null, $createTime = null)
    {
        $gamesAssetsManager = $request->managers->gamesAssets();

        $contextXGameAssetLink = $this->linkGameAssetToContextEntity(
            $request,
            $gameAssetId,
            EntityType::GAME_INSTANCE_LOG,
            $gameInstanceLogId,
            $folderPath,
            $fileName,
            $updateChannel,
            $slug,
            $createTime
        );

        return $gamesAssetsManager->getGameInstanceLogAssetByGameInstanceLogAssetIdAndContext(
            $request,
            $gameInstanceLogId,
            $contextXGameAssetLink->getPk(),
            $updateChannel
        );
    }
}


class GamesActiveCustomAssetsManager extends BaseEntityManager
{
    protected $entityClass = GameActiveCustomAssetEntity::class;
    protected $table = Table::GameActiveCustomAsset;
    protected $table_alias = TableAlias::GameActiveCustomAsset;
    protected $pk = DBField::GAME_ACTIVE_CUSTOM_ASSET_ID;

    protected $foreign_managers = [
        GamesManager::class => DBField::GAME_ID,
        ContextXGamesAssetsManager::class => DBField::CONTEXT_X_GAME_ASSET_ID
    ];

    public static $fields = [
        DBField::GAME_ACTIVE_CUSTOM_ASSET_ID,
        DBField::GAME_ID,
        DBField::UPDATE_CHANNEL,
        DBField::GAME_BUILD_ID,
        DBField::SLUG,
        DBField::CONTEXT_X_GAME_ASSET_ID,
        DBField::IS_PUBLIC,
        DBField::IS_ACTIVE,
        DBField::CREATED_BY,
        DBField::MODIFIED_BY,
        DBField::DELETED_BY
    ];

    /**
     * @param Request $request
     * @param $gameId
     * @param $updateChannel
     * @param $gameBuildId
     * @param $slug
     * @param $contextXGameAssetId
     * @param $isPublic
     * @return GameActiveCustomAssetEntity
     */
    public function createNewGameActiveCustomAsset(Request $request, $gameId, $updateChannel, $gameBuildId, $slug,
                                                   $contextXGameAssetId, $isPublic)
    {
        if (!$isPublic)
            $isPublic = 0;
        else
            $isPublic = 1;

        $data = [
            DBField::GAME_ID => $gameId,
            DBField::UPDATE_CHANNEL => $updateChannel,
            DBField::GAME_BUILD_ID => $gameBuildId,
            DBField::SLUG => $slug,
            DBField::CONTEXT_X_GAME_ASSET_ID => $contextXGameAssetId,
            DBField::IS_PUBLIC => $isPublic,
            DBField::IS_ACTIVE => 1
        ];

        return $this->query($request->db)->createNewEntity($request, $data);

    }

    /**
     * @param Request $request
     * @param $gameId
     * @param $updateChannel
     * @param $gameBuildId
     * @param $slug
     * @param null $contextXGameAssetId
     * @param int $isPublic
     * @return GameActiveCustomAssetEntity
     */
    public function createUpdateGameActiveCustomAsset(Request $request, $gameId, $updateChannel, $gameBuildId, $slug,
                                                      $contextXGameAssetId = null, $isPublic = 0)
    {
        if ($isPublic)
            $isPublic = 1;

        $gameActiveCustomAsset = $this->getGameActiveCustomAssetLinkByGameIdAndSlug($request, $gameId, $updateChannel, $slug, $gameBuildId);

        if ($gameActiveCustomAsset) {

            if (!$gameActiveCustomAsset->is_active()) {}
                $gameActiveCustomAsset->updateField(DBField::IS_ACTIVE, 1);

            $gameActiveCustomAsset->updateField(DBField::IS_PUBLIC, $isPublic);
            $gameActiveCustomAsset->updateField(DBField::CONTEXT_X_GAME_ASSET_ID, $contextXGameAssetId);
            $gameActiveCustomAsset->saveEntityToDb($request);

        } else {
            $gameActiveCustomAsset = $this->createNewGameActiveCustomAsset(
                $request,
                $gameId,
                $updateChannel,
                $gameBuildId,
                $slug,
                $contextXGameAssetId,
                $isPublic
            );
        }

        return $gameActiveCustomAsset;
    }

    /**
     * @param Request $request
     * @param $gameId
     * @param $updateChannel
     * @param $gameBuildId
     * @param $slug
     */
    public function deleteGameActiveCustomAssetLink(Request $request, $gameId, $updateChannel, $gameBuildId, $slug)
    {

        $deactivationData = [
            DBField::IS_ACTIVE => 0,
            DBField::MODIFIED_BY => $request->requestId,
            DBField::DELETED_BY => $request->requestId,
        ];

        $this->query($request->db)
            ->filter($this->filters->byGameId($gameId))
            ->filter($this->filters->byUpdateChannel($updateChannel))
            ->filter($this->filters->byGameBuildId($gameBuildId))
            ->filter($this->filters->bySlug($slug))
            ->update($deactivationData);
    }

    /**
     * @param Request $request
     * @param GameActiveCustomAssetEntity $oldGameActiveCustomAsset
     * @param $newSlug
     */
    public function renameCustomGameAssetSlug(Request $request, GameActiveCustomAssetEntity $oldGameActiveCustomAsset, $newSlug)
    {
        $gamesAssetsManager = $request->managers->gamesAssets();

        $contextXGamesAssetsManager = $request->managers->contextXGamesAssets();

        // Deactivate Old Asset
        $oldGameActiveCustomAsset->updateField(DBField::IS_ACTIVE, 0)->saveEntityToDb($request);

        // Get all the old game asset history -- we need this to create new links.
        $oldCustomGameAssets = $gamesAssetsManager->getCustomGameAssetsHistoryBySlug(
            $request,
            $oldGameActiveCustomAsset->getGameId(),
            $oldGameActiveCustomAsset->getUpdateChannel(),
            $oldGameActiveCustomAsset->getSlug()
        );

        /** @var CustomGameAssetEntity[] $oldCustomGameAssets */
        $oldCustomGameAssets = array_index(array_reverse($oldCustomGameAssets), VField::CUSTOM_GAME_ASSET_ID);

        $newCustomGameAssets = [];
        $newActiveCustomGameAssetId = null;

        foreach ($oldCustomGameAssets as $oldCustomGameAsset) {
            $customGameAsset = $contextXGamesAssetsManager->linkGameAssetToGame(
                $request,
                $oldCustomGameAsset->getPk(),
                $oldCustomGameAsset->getGameId(),
                $oldCustomGameAsset->getFolderPath(),
                $oldCustomGameAsset->getFileName(),
                $oldCustomGameAsset->getUpdateChannel(),
                $newSlug,
                $oldCustomGameAsset->getCreateTime()
            );
            if ($oldCustomGameAsset->getCustomGameAssetId() == $oldGameActiveCustomAsset->getContextXGameAssetId())
                $newActiveCustomGameAssetId = $customGameAsset->getCustomGameAssetId();

            $newCustomGameAssets[$customGameAsset->getCustomGameAssetId()] = $customGameAsset;
        }

        $gameActiveCustomAsset = $this->createUpdateGameActiveCustomAsset(
            $request,
            $oldGameActiveCustomAsset->getGameId(),
            $oldGameActiveCustomAsset->getUpdateChannel(),
            $oldGameActiveCustomAsset->getGameBuildId(),
            $newSlug,
            $newActiveCustomGameAssetId,
            $oldGameActiveCustomAsset->getIsPublic()
        );
    }

    /**
     * @param Request $request
     * @param GameBuildEntity $oldGameBuild
     * @param GameBuildEntity $newGameBuild
     */
    public function cloneCustomGameBuildAssets(Request $request, GameBuildEntity $oldGameBuild, GameBuildEntity $newGameBuild)
    {
        $gamesAssetsManager = $request->managers->gamesAssets();
        $contextXGameAssetsManager = $request->managers->contextXGamesAssets();

        $customGameAssetLinks = $this->getActiveGameActiveCustomAssetLinksByGameBuildId($request, $oldGameBuild->getPk());

        if ($customGameAssetLinks) {

            $customGameAssetIds = array_extract(DBField::CONTEXT_X_GAME_ASSET_ID, $customGameAssetLinks);

            $customGameAssets = $gamesAssetsManager->getCustomGameAssetsByCustomGameAssetIds(
                $request, $customGameAssetIds, $oldGameBuild->getGameId(), $oldGameBuild->getUpdateChannel()
            );
            if ($customGameAssets)
                $customGameAssets = array_index($customGameAssets, VField::CUSTOM_GAME_ASSET_ID);
        } else {
            $customGameAssets = [];
        }


        $liveCustomGameAssets = [];
        foreach ($customGameAssetLinks as $customGameAssetLink) {

            if (array_key_exists($customGameAssetLink->getContextXGameAssetId(), $customGameAssets)) {

                $customGameAsset = $customGameAssets[$customGameAssetLink->getContextXGameAssetId()];

                $newCustomGameAsset = $contextXGameAssetsManager->linkGameAssetToGame(
                    $request,
                    $customGameAsset->getPk(),
                    $customGameAssetLink->getGameId(),
                    $customGameAsset->getFolderPath(),
                    $customGameAsset->getFileName(),
                    $newGameBuild->getUpdateChannel(),
                    $customGameAsset->getSlug(),
                    $customGameAsset->getCreateTime()
                );

                $liveCustomGameAssetLink = $this->createNewGameActiveCustomAsset(
                    $request,
                    $newGameBuild->getGameId(),
                    $newGameBuild->getUpdateChannel(),
                    $newGameBuild->getPk(),
                    $customGameAssetLink->getSlug(),
                    $newCustomGameAsset->getCustomGameAssetId(),
                    $customGameAssetLink->getIsPublic()
                );
                $liveCustomGameAssets[] = $liveCustomGameAssetLink;

            }

        }
    }

    /**
     * @param Request $request
     * @param $gameIds
     * @return GameActiveCustomAssetEntity[]
     */
    public function getGameActiveCustomAssetLinksByGameIds(Request $request, $gameIds, $updateChannel, $gameBuildId = null)
    {
        return $this->query($request->db)
            ->filter($this->filters->byGameId($gameIds))
            ->filter($this->filters->byGameBuildId($gameBuildId))
            ->filter($this->filters->byUpdateChannel($updateChannel))
            ->filter($this->filters->isActive())
            ->sort_asc(DBField::SLUG)
            ->get_entities($request);
    }

    /**
     * @param Request $request
     * @param $gameBuildIds
     * @return GameActiveCustomAssetEntity[]
     */
    public function getGameActiveCustomAssetLinksByGameBuildIds(Request $request, $gameBuildIds)
    {
        return $this->query($request->db)
            ->filter($this->filters->byGameBuildId($gameBuildIds))
            ->filter($this->filters->isActive())
            ->get_entities($request);
    }

    /**
     * @param Request $request
     * @param $gameId
     * @param $slug
     * @return GameActiveCustomAssetEntity|array
     */
    public function getActiveGameActiveCustomAssetLinkByGameIdAndSlug(Request $request, $gameId, $updateChannel, $slug, $gameBuildId = null)
    {
        return $this->query($request->db)
            ->filter($this->filters->byGameId($gameId))
            ->filter($this->filters->byGameBuildId($gameBuildId))
            ->filter($this->filters->byUpdateChannel($updateChannel))
            ->filter($this->filters->bySlug($slug))
            ->filter($this->filters->isActive())
            ->get_entity($request);
    }

    /**
     * @param Request $request
     * @param $gameId
     * @param $updateChannel
     * @param $slug
     * @param $gameBuildId
     * @return array|GameActiveCustomAssetEntity
     */
    public function getGameActiveCustomAssetLinkByGameIdAndSlug(Request $request, $gameId, $updateChannel, $slug, $gameBuildId )
    {
        return $this->query($request->db)
            ->filter($this->filters->byGameId($gameId))
            ->filter($this->filters->byGameBuildId($gameBuildId))
            ->filter($this->filters->byUpdateChannel($updateChannel))
            ->filter($this->filters->bySlug($slug))
            ->get_entity($request);
    }

    /**
     * @param Request $request
     * @param $gameBuildId
     * @return GameActiveCustomAssetEntity[]
     */
    public function getActiveGameActiveCustomAssetLinksByGameBuildId(Request $request, $gameBuildId, $expand = false)
    {
        $gamesAssetsManager = $request->managers->gamesAssets();

        /** @var GameActiveCustomAssetEntity[] $gameActiveCustomAssets */
        $gameActiveCustomAssets = $this->query($request->db)
            ->filter($this->filters->byGameBuildId($gameBuildId))
            ->filter($this->filters->isActive())
            ->get_entities($request);

        if ($expand && $gameActiveCustomAssets) {
            $customGameAssetIds = array_extract(DBField::CONTEXT_X_GAME_ASSET_ID, $gameActiveCustomAssets);
            $gameId = $gameActiveCustomAssets[0][DBField::GAME_ID];
            $updateChannel = $gameActiveCustomAssets[0][DBField::UPDATE_CHANNEL];
            if ($customGameAssetIds) {

                $customGameAssets = $gamesAssetsManager->getCustomGameAssetsByCustomGameAssetIds(
                    $request, $customGameAssetIds, $gameId, $updateChannel
                );

                if ($customGameAssets)
                    $customGameAssets = array_index($customGameAssets, VField::CUSTOM_GAME_ASSET_ID);

                foreach ($gameActiveCustomAssets as $gameActiveCustomAsset) {
                    $customGameAssetId = $gameActiveCustomAsset->getContextXGameAssetId() ;

                    if ($customGameAssetId && array_key_exists($customGameAssetId, $customGameAssets))
                        $gameActiveCustomAsset->updateField(VField::GAME_ASSET, $customGameAssets[$customGameAssetId]);
                }
            }
        }

        return $gameActiveCustomAssets;
    }

    /**
     * @param Request $request
     * @param $gameId
     * @param $slug
     * @return array|GameActiveCustomAssetEntity
     */
    public function getPublicGameActiveCustomAssetLinkByGameIdAndSlug(Request $request, $gameId, $updateChannel, $slug, $gameBuildId = null)
    {
        return $this->query($request->db)
            ->filter($this->filters->byGameId($gameId))
            ->filter($this->filters->byUpdateChannel($updateChannel))
            ->filter($this->filters->byGameBuildId($gameBuildId))
            ->filter($this->filters->bySlug($slug))
            ->filter($this->filters->isActive())
            ->filter($this->filters->isPublic())
            ->get_entity($request);
    }


}
<?php
/**
 * Created by PhpStorm.
 * User: christophercarter
 * Date: 10/18/18
 * Time: 11:06 AM
 */



class GamesBuildsManager extends BaseEntityManager {

    protected $entityClass = GameBuildEntity::class;
    protected $table = Table::GameBuild;
    protected $table_alias = TableAlias::GameBuild;
    protected $pk = DBField::GAME_BUILD_ID;

    const AWS_BUCKET = 'game-build-assets';
    const ROOT_FILE_IDENTIFIER = 'index.html';

    const GNS_KEY_PREFIX = GNS_ROOT.'.game-builds';

    public static $fields = [
        DBField::GAME_BUILD_ID,
        DBField::GAME_ID,
        DBField::UPDATE_CHANNEL,
        DBField::GAME_BUILD_VERSION,
        DBField::CAN_MOD,
        DBField::IS_AGGREGATE_GAME,
        DBField::PUBLISHED_GAME_BUILD_ID,
        DBField::VERSION_HASH,
        DBField::DESCRIPTION,
        DBField::IS_ACTIVE,
        DBField::CREATE_TIME,
        DBField::CREATOR_ID,
        DBField::CREATED_BY,
        DBField::MODIFIED_BY,
        DBField::DELETED_BY
    ];

    protected $foreign_managers = [
        GamesManager::class => DBField::GAME_ID,
        GamesActiveBuildsManager::class => DBField::GAME_BUILD_ID,
    ];

    /**
     * @param $gameBuildId
     * @return string
     */
    public function generateEntityIdCacheKey($gameBuildId)
    {
        return self::GNS_KEY_PREFIX.".id.{$gameBuildId}";
    }

    /**
     * @param GameBuildEntity $data
     * @param Request $request
     */
    public function processVFields(DBManagerEntity $data, Request $request)
    {
        if (!$data->hasField(VField::USER))
            $data->updateField(VField::USER, []);

        if (!$data->hasField(VField::GAME_ASSETS))
            $data->updateField(VField::GAME_ASSETS, []);

        if (!$data->hasField(VField::CUSTOM_GAME_ASSETS))
            $data->updateField(VField::CUSTOM_GAME_ASSETS, []);


        if (!$data->hasField(VField::GAME_CONTROLLERS))
            $data->updateField(VField::GAME_CONTROLLERS, []);

        if ($data->hasField(DBField::DESCRIPTION))
            $data->updateField(VField::PARSED_DESCRIPTION, parse_post($data->getDescription()));
    }

    /**
     * @param bool $requirePk
     * @return FormField[]
     */
    public function getFormFields($games = [], $updateChannels = [], $requirePk = true)
    {
        $fields = [
            new IntegerField(DBField::ID, 'Game Build ID', $requirePk),
            new SelectField(DBField::UPDATE_CHANNEL, 'Update Channel', $updateChannels, true),
            new SelectField(DBField::GAME_ID, 'Game ID', $games, true),
            new CharField(DBField::GAME_BUILD_VERSION, 'Game Build Number', 0, true),
            //new CharField(DBField::GAME_CONTROLLER_VERSION, 'Game Controller Build Number', true),
        ];

        return $fields;
    }

    /**
     * @param Request $request
     * @param GameEntity $game
     * @return string
     */
    public function generateDestinationFolder(Request $request, GameEntity $game)
    {
        return "{$request->settings()->getMediaDir()}/games/{$game->getPk()}/build-{$request->requestId}/";
    }

    /**
     * @return string
     */
    public function getZipRootFileIdentifier()
    {
        return self::ROOT_FILE_IDENTIFIER;
    }

    /**
     * @param Request $request
     * @param $gameId
     * @param $updateChannel
     * @param $gameBuildVersion
     * @return GameBuildEntity
     */
    public function createNewGameBuild(Request $request, $gameId, $updateChannel, $gameBuildVersion, $versionHash = null, $canMod = 0, $isAggregateGame = 0, $description = null)
    {
        $data = [
            DBField::GAME_ID => $gameId,
            DBField::CAN_MOD => $canMod,
            DBField::DESCRIPTION => $description,
            DBField::IS_AGGREGATE_GAME => $isAggregateGame,
            DBField::UPDATE_CHANNEL => $updateChannel,
            DBField::GAME_BUILD_VERSION => $gameBuildVersion,
            DBField::VERSION_HASH => $versionHash,
            DBField::CREATOR_ID => $request->user->id,
            DBField::IS_ACTIVE => 1
        ];

        /** @var GameBuildEntity $gameBuild */
        $gameBuild = $this->query($request->db)->createNewEntity($request, $data);

        return $gameBuild;
    }

    /**
     * @param Request $request
     * @param array|GameBuildEntity $gameBuilds
     */
    protected function postProcessGameBuilds(Request $request, $gameBuilds, $expand = false)
    {
        $gameAssetsManager = $request->managers->gamesAssets();
        $gamesControllersManager = $request->managers->gamesControllers();
        $gamesActiveCustomAssetsManager = $request->managers->gamesActiveCustomAssets();

        if ($gameBuilds instanceof GameBuildEntity)
            $gameBuilds = [$gameBuilds];

        if ($gameBuilds) {
            /** @var GameBuildEntity[] $gameBuilds */
            $gameBuilds = array_index($gameBuilds, $this->getPkField());

            $gameBuildIds = array_keys($gameBuilds);

            $gameBuildAssets = $gameAssetsManager->getCachedGameBuildAssetsByGameBuildId($request, $gameBuildIds);
            foreach ($gameBuildAssets as $gameBuildAsset) {
                $gameBuilds[$gameBuildAsset->getGameBuildId()]->setGameAsset($gameBuildAsset);
            }

            $gameControllers = $gamesControllersManager->getGameControllersByGameBuildId($request, $gameBuildIds, $expand);
            foreach ($gameControllers as $gameController) {
                $gameBuilds[$gameController->getGameBuildId()]->setGameController($gameController);
            }

            $gameActiveCustomAssets = $gamesActiveCustomAssetsManager->getGameActiveCustomAssetLinksByGameBuildIds($request, $gameBuildIds);
            $customGameAssetIds = unique_array_extract(DBField::CONTEXT_X_GAME_ASSET_ID, $gameActiveCustomAssets);
            $gameIds = unique_array_extract(DBField::GAME_ID, $gameActiveCustomAssets);

            /** @var CustomGameAssetEntity[] $customGameAssets */
            $customGameAssets = $gameAssetsManager->getCustomGameAssetsByCustomGameAssetIds($request, $customGameAssetIds, $gameIds);
            $customGameAssets = $gameAssetsManager->index($customGameAssets, VField::CUSTOM_GAME_ASSET_ID);
            foreach ($gameActiveCustomAssets as $gameActiveCustomAsset) {
                $customGameAsset = $customGameAssets[$gameActiveCustomAsset->getContextXGameAssetId()] ?? null;
                if ($customGameAsset) {
                    $customGameAsset->setGameActiveCustomAsset($gameActiveCustomAsset);
                    $customGameAsset[DBField::IS_PUBLIC] = $gameActiveCustomAsset->getIsPublic();
                    if ($customGameAsset->is_public()) {
                        $customGameAsset[VField::PUBLIC_URL] = $gameAssetsManager->generatePublicCustomAssetUrl(
                          $request,
                            $customGameAsset->getGameId(),
                            $customGameAsset->getUpdateChannel(),
                            $customGameAsset->getSlug(),
                            $customGameAsset->getCustomGameAssetId(),
                            $customGameAsset->getFileName(),
                            $gameActiveCustomAsset->getGameBuildId()
                        );
                    }
                    $gameBuilds[$gameActiveCustomAsset->getGameBuildId()]->setCustomGameAsset($customGameAsset);
                }
            }

        }
    }

    /**
     * @param Request $request
     * @param $gameId
     * @param $updateChannel
     * @param $gameBuildVersion
     * @return bool
     */
    public function checkGameBuildVersionExists(Request $request, $gameId, $updateChannel, $gameBuildVersion)
    {
        return $this->query($request->db)
            ->filter($this->filters->byGameId($gameId))
            ->filter($this->filters->byUpdateChannel($updateChannel))
            ->filter($this->filters->byGameBuildVersion($gameBuildVersion))
            ->filter($this->filters->isActive())
            ->exists();
    }

    /**
     * @param Request $request
     * @param $gameBuildId
     * @return array|GameBuildEntity
     */
    public function getGameBuildById(Request $request, $gameBuildId, $gameId = null, $expand = false)
    {
        $queryBuilder =  $this->query($request->db)
            ->filter($this->filters->byPk($gameBuildId))
            ->filter($this->filters->isActive());

        if ($gameId)
            $queryBuilder->filter($this->filters->byGameId($gameId));

        /** @var GameBuildEntity $gameBuild */
        $gameBuild = $queryBuilder->get_entity($request);

        if ($expand)
            $this->postProcessGameBuilds($request, $gameBuild, $expand);

        return $gameBuild;
    }

    /**
     * @param Request $request
     * @param $gameId
     * @param $updateChannel
     * @return array
     */
    public function getGameBuildOptionsByGameAndUpdateChannel(Request $request, $gameId, $updateChannel)
    {
        $gameBuildOptions = $this->query($request->db)
            ->filter($this->filters->byGameId($gameId))
            ->filter($this->filters->byUpdateChannel($updateChannel))
            ->filter($this->filters->isActive())
            ->get_list($this->aliasField(DBField::GAME_BUILD_ID, DBField::ID), $this->aliasField(DBField::GAME_BUILD_VERSION, DBField::DISPLAY_NAME));

        if ($gameBuildOptions)
            $gameBuildOptions = array_index($gameBuildOptions, DBField::ID);

        return $gameBuildOptions;
    }

    /**
     * @param Request $request
     * @param $gameId
     * @param $updateChannel
     * @param bool $expand
     * @return GameBuildEntity[]
     */
    public function getGameBuildsByGameAndUpdateChannel(Request $request, $gameId, $updateChannel, $maxCount = 15, $expand = true)
    {
        $gameBuilds = $this->query($request->db)
            ->filter($this->filters->byGameId($gameId))
            ->filter($this->filters->byUpdateChannel($updateChannel))
            ->filter($this->filters->isActive())
            //->filter($this->filters->IsNotNull(DBField::PUBLISHED_GAME_BUILD_ID))
            ->sort_desc($this->field(DBField::CREATE_TIME))
            ->limit($maxCount)
            ->get_entities($request);

        $this->postProcessGameBuilds($request, $gameBuilds, $expand);

        if ($gameBuilds)
            $gameBuilds = array_index($gameBuilds, $this->getPkField());

        return $gameBuilds;
    }

    /**
     * @param Request $request
     * @param $gameIds
     * @param bool $expand
     * @return GameBuildEntity[]
     */
    public function getActiveGameBuildsByGameIds(Request $request, $gameIds, $expand = false)
    {
        $gamesActiveBuildsManager = $request->managers->gamesActiveBuilds();

        $joinGamesActiveBuildsFilter = $this->filters->And_(
            $gamesActiveBuildsManager->filters->byGameBuildId($this->createPkField()),
            $gamesActiveBuildsManager->filters->byUpdateChannel($this->field(DBField::UPDATE_CHANNEL)),
            $gamesActiveBuildsManager->filters->isActive()
        );

        $gameBuilds = $this->query($request->db)
            ->filter($this->filters->byGameId($gameIds))
            ->inner_join($gamesActiveBuildsManager, $joinGamesActiveBuildsFilter)
            ->filter($this->filters->isActive())
            ->get_entities($request);

        if ($expand)
            $this->postProcessGameBuilds($request, $gameBuilds, $expand);

        return $gameBuilds;
    }

    /**
     * @param Request $request
     * @param $gameId
     * @param $updateChannel
     * @param bool $expand
     * @return array|GameBuildEntity
     */
    public function getActiveGameBuildByGameAndUpdateChannel(Request $request, $gameId, $updateChannel, $expand = false)
    {
        $gamesActiveBuildsManager = $request->managers->gamesActiveBuilds();

        $joinGamesActiveBuildsFilter = $this->filters->And_(
            $gamesActiveBuildsManager->filters->byGameBuildId($this->createPkField()),
            $gamesActiveBuildsManager->filters->byUpdateChannel($this->field(DBField::UPDATE_CHANNEL)),
            $gamesActiveBuildsManager->filters->isActive()
        );

        $gameBuilds = $this->query($request->db)
            ->filter($this->filters->byGameId($gameId))
            ->filter($this->filters->byUpdateChannel($updateChannel))
            ->inner_join($gamesActiveBuildsManager, $joinGamesActiveBuildsFilter)
            ->filter($this->filters->isActive())
            ->get_entity($request);

        if ($expand)
            $this->postProcessGameBuilds($request, $gameBuilds, $expand);

        return $gameBuilds;
    }


    /**
     * @param Request $request
     * @return GameBuildEntity[]
     */
    public function getAllGameBuilds(Request $request)
    {
        $gameBuilds = $this->query($request->db)->get_entities($request);

        $this->postProcessGameBuilds($request, $gameBuilds);

        return $gameBuilds;
    }


    /**
     * @param Request $request
     * @param $gameId
     * @param string $updateChannel
     * @param bool $active
     * @param bool $expand
     * @param bool $reverse
     * @return GameBuildEntity[]
     */
    public function getGameBuildsByGameId(Request $request, $gameId, $active = true,
                                          $updateChannel = GamesManager::UPDATE_CHANNEL_LIVE, $expand = true, $reverse = false)
    {
        $queryBuilder = $this->query($request->db)
            ->filter($this->filters->byGameId($gameId))
            ->filter($this->filters->byUpdateChannel($updateChannel));

        if ($active)
            $queryBuilder->filter($this->filters->isActive());

        if ($reverse)
            $queryBuilder->sort_desc($this->getPkField());

        $gameBuilds = $queryBuilder->get_entities($request);

        if ($expand)
            $this->postProcessGameBuilds($request, $gameBuilds, $expand);

        return $gameBuilds;
    }


    /**
     * @param Request $request
     * @param $gameId
     * @param string $updateChannel
     * @param bool $expand
     * @return GameBuildEntity
     */
    public function getMostRecentGameBuildByGameId(Request $request, $gameId,
                                                   $updateChannel = GamesManager::UPDATE_CHANNEL_LIVE, $expand = true)
    {
        $gameBuild = $this->query($request->db)
            ->filter($this->filters->byGameId($gameId))
            ->filter($this->filters->byUpdateChannel($updateChannel))
            ->filter($this->filters->isActive())
            ->sort_desc(DBField::CREATE_TIME)
            ->get_entity($request);

        $this->postProcessGameBuilds($request, $gameBuild, $expand);

        return $gameBuild;
    }

    /**
     * @param Request $request
     * @param $gameBuildIds
     * @return GameBuildEntity[]
     */
    public function getGameBuildsByIds(Request $request, $gameBuildIds, $expand = true)
    {
        /** @var GameBuildEntity[] $gameBuilds */
        $gameBuilds = $this->getEntitiesByPks($request, $gameBuildIds, ONE_DAY, null, $this->filters->isActive());

        if ($expand)
            $this->postProcessGameBuilds($request, $gameBuilds, $expand);

        return $gameBuilds;
    }

    /**
     * @param Request $request
     * @param $gameIds
     * @param $userId
     * @return GameBuildEntity[]
     */
    public function getUserPlayableGameBuildsByGameIds(Request $request, $gameIds, $userId, $updateChannel = null)
    {
        $gamesActiveBuildsManager = $request->managers->gamesActiveBuilds();
        $gamesManager = $request->managers->games();
        $gameLicensesManager = $request->managers->gameLicenses();

        $joinGamesActiveBuildsFilter = $this->filters->And_(
            $gamesActiveBuildsManager->filters->byGameBuildId($this->createPkField()),
            $gamesActiveBuildsManager->filters->byUpdateChannel($this->field(DBField::UPDATE_CHANNEL))
        );

        $joiGameLicensesFilter = $gamesManager->getJoinGameLicenseFilter($request, $userId, $this->field(DBField::UPDATE_CHANNEL));

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
                    $gamesActiveBuildsManager->filters->byUpdateChannel(GamesManager::UPDATE_CHANNEL_DEV)
                ),
                $this->filters->And_(
                    $organizationsBaseRightsManager->filters->byName(OrganizationsBaseRightsManager::RIGHT_ORG_GAMES_CHANNELS_LIVE),
                    $gamesActiveBuildsManager->filters->byUpdateChannel(GamesManager::UPDATE_CHANNEL_LIVE)
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
            $gamesActiveBuildsManager->filters->byUpdateChannel(GamesManager::UPDATE_CHANNEL_LIVE),
            $organizationsGamesLicensesManager->getActiveLicenseFilter($request->getCurrentSqlTime())
        );

        $whereFilter = $this->filters->Or_(
        // If there's a user license
            $gameLicensesManager->filters->IsNotNull($gameLicensesManager->createPkField()),
            // Or the user has permission to use a game build of the correct update channel
            $this->filters->And_(
                $organizationsPermissionsManager->filters->IsNotNull($organizationsPermissionsManager->createPkField()),
                $gamesManager->filters->byOwnerTypeId(EntityType::ORGANIZATION),
                $organizationsUsersManager->filters->byOrganizationId($gamesManager->field(DBField::OWNER_ID))
            ),
            // Or the organization has a license to use a game
            $organizationsGamesLicensesManager->filters->IsNotNull($organizationsGamesLicensesManager->createPkField())
        );


        /** @var GameBuildEntity[] $gameBuilds */
        $gameBuilds = $this->query($request->db)
            ->inner_join($gamesActiveBuildsManager, $joinGamesActiveBuildsFilter)
            ->inner_join($gamesManager)
            ->left_join($gameLicensesManager, $joiGameLicensesFilter)
            ->left_join($organizationsUsersManager, $joinOrganizationsUsersFilter)
            ->left_join($organizationsManager, $joinOrganizationsFilter)
            ->left_join($organizationsRightsManager, $joinOrganizationsRightsFilter)
            ->left_join($organizationsBaseRightsManager, $joinOrganizationsBaseRightsFilter)
            ->left_join($organizationsPermissionsManager, $joinOrganizationsPermissionsFilter)
            ->left_join($organizationsGamesLicensesManager, $joinOrganizationsGamesLicensesFilter)
            ->filter($whereFilter)
            ->filter($gamesManager->filters->isActive())
            ->filter($gamesActiveBuildsManager->filters->isActive())
            ->filter($this->filters->byUpdateChannel($updateChannel))
            ->filter($this->filters->isActive())
            ->filter($this->filters->byGameId($gameIds))
            ->group_by($this->createPkField())
            ->get_entities($request);

        if ($gameBuilds)
            $this->postProcessGameBuilds($request, $gameBuilds, true);

        return $gameBuilds;
    }

    /**
     * @param Request $request
     * @param GameEntity $game
     * @param GameBuildEntity $gameBuild
     * @param $sourceFile
     */
    public function handleProcessUploadedGameBuildZipArchive(Request $request, GameEntity $game, GameBuildEntity $gameBuild, $sourceFile)
    {
        $gamesAssetsManager = $request->managers->gamesAssets();

        $zipArchive = new ZipArchive();

        $res = $zipArchive->open($sourceFile);

        $gameBuildAssets = [];

        $destinationFolder = $this->generateDestinationFolder($request, $game);

        if ($res === true) {

            if (!is_dir($destinationFolder)) {
                if (mkdir($destinationFolder, 0777, true)) {
                    $zipArchive->extractTo($destinationFolder);
                    $files = FilesToolkit::list_files($destinationFolder);

                    $removeFolderPrefix = '';

                    foreach ($files as $file) {
                        $stringPosition = strpos($file, "index.html");
                        if ($stringPosition !== false && $stringPosition !== 0) {
                            $removeFolderPrefix = str_replace('index.html', '', $file);
                        }

                    }

                    foreach ($files as $file) {

                        $realFilePath = "{$destinationFolder}{$file}";

                        if (strpos($file, '/')) {
                            $filePathParts = explode('/', $file);

                            $fileName = array_pop($filePathParts);

                            if (count($filePathParts) > 0) {
                                $filePathParts[] = '';
                                $folderPath = join('/', $filePathParts);
                            }

                        } else {
                            $fileName = $file;
                            $folderPath = null;
                        }

                        if ($folderPath && $removeFolderPrefix) {
                            $folderPath = str_replace($removeFolderPrefix, '', $folderPath);
                        }

                        $gameBuildAsset = $gamesAssetsManager->handleGameBuildAssetUpload(
                            $request,
                            $realFilePath,
                            md5_file($realFilePath),
                            $fileName,
                            $game->getPk(),
                            $gameBuild->getPk(),
                            $folderPath,
                            $gameBuild->getUpdateChannel()
                        );
                        $gameBuildAssets[] = $gameBuildAsset;
                        $gameBuild->setGameAsset($gameBuildAsset);
                    }
                }
            }

            FilesToolkit::clear_directory($destinationFolder);
            rmdir($destinationFolder);
        }
    }

    /**
     * @param Request $request
     * @param string $historyTimeModifier
     */
    public function ETL_computeCustomizerSupport(Request $request, $historyTimeModifier = "-3 month")
    {
        $gameAssetsManager = $request->managers->gamesAssets();

        $dt = new DateTime();
        $dt->modify($historyTimeModifier);

        $gameBuilds = $this->query($request->db)
            ->filter($this->filters->createdBefore($dt->format(SQL_DATETIME)))
            ->get_entities($request);

        if ($gameBuilds) {
            /** @var GameBuildEntity[] $gameBuilds */
            $gameBuilds = array_index($gameBuilds, $this->getPkField());

            $gameBuildIds = array_keys($gameBuilds);

            $gameBuildAssets = $gameAssetsManager->getCachedGameBuildAssetsByGameBuildId($request, $gameBuildIds);
            foreach ($gameBuildAssets as $gameBuildAsset) {
                $gameBuilds[$gameBuildAsset->getGameBuildId()]->setGameAsset($gameBuildAsset);
            }
        }

        foreach ($gameBuilds as $gameBuild) {
            $gameBuild->getCustomDataDefinition($request);
        }
    }

}

class GamesActiveBuildsManager extends BaseEntityManager
{
    protected $entityClass = GameActiveBuildEntity::class;
    protected $table = Table::GameActiveBuild;
    protected $table_alias = TableAlias::GameActiveBuild;
    protected $pk = DBField::GAME_ACTIVE_BUILD_ID;

    public $foreign_managers = [
        GamesBuildsManager::class => DBField::GAME_BUILD_ID
    ];

    public static $fields = [
        DBField::GAME_ACTIVE_BUILD_ID,
        DBField::GAME_ID,
        DBField::UPDATE_CHANNEL,
        DBField::GAME_BUILD_ID,
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
     * @return GameActiveBuildEntity
     */
    public function createUpdateGameActiveBuild(Request $request, $gameId, $updateChannel, $gameBuildId)
    {
        if ($gameActiveBuild = $this->getGameActiveBuildByUpdateChannel($request, $gameId, $updateChannel)) {

            if (!$gameActiveBuild->is_active())
                $gameActiveBuild->updateField(DBField::IS_ACTIVE, 1);

            $gameActiveBuild->updateField(DBField::GAME_BUILD_ID, $gameBuildId)->saveEntityToDb($request);

        } else {
            $data = [
                DBField::GAME_ID => $gameId,
                DBField::UPDATE_CHANNEL => $updateChannel,
                DBField::GAME_BUILD_ID => $gameBuildId,
                DBField::IS_ACTIVE => 1
            ];

            /** @var GameActiveBuildEntity $gameActiveBuild */
            $gameActiveBuild = $this->query($request->db)->createNewEntity($request, $data);
        }

        return $gameActiveBuild;
    }

    /**
     * @param Request $request
     * @param $gameId
     * @param $updateChannel
     * @return array|GameActiveBuildEntity
     */
    public function getGameActiveBuildByUpdateChannel(Request $request, $gameId, $updateChannel)
    {
        return $this->query($request)
            ->filter($this->filters->byGameId($gameId))
            ->filter($this->filters->byUpdateChannel($updateChannel))
            ->get_entity($request);
    }

    /**
     * @param Request $request
     * @param $gameId
     * @param $updateChannel
     * @return array
     */
    public function getGameActiveBuildVersionSummaryByUpdateChannel(Request $request, $gameId, $updateChannel)
    {
        $gamesBuildsManager = $request->managers->gamesBuilds();

        $fields = [
            $gamesBuildsManager->createPkField(),
            $gamesBuildsManager->field(DBField::GAME_BUILD_VERSION)
        ];

        try {
            $activeBuildVersionSummary = $this->query($request->db)
                ->fields($fields)
                ->inner_join($gamesBuildsManager)
                ->filter($this->filters->byGameId($gameId))
                ->filter($this->filters->byUpdateChannel($updateChannel))
                ->filter($this->filters->isActive())
                ->get();
        } catch (ObjectNotFound $e) {
            $activeBuildVersionSummary = [];
        }

        return $activeBuildVersionSummary;
    }

    /**
     * @param Request $request
     * @param $gameId
     * @return array
     */
    public function getGameActiveBuildVersionSummaries(Request $request, $gameId)
    {
        $gamesBuildsManager = $request->managers->gamesBuilds();

        $fields = [
            $gamesBuildsManager->createPkField(),
            $gamesBuildsManager->field(DBField::UPDATE_CHANNEL),
            $gamesBuildsManager->field(DBField::GAME_BUILD_VERSION)
        ];

        $activeBuildVersionSummaries = $this->query($request->db)
            ->fields($fields)
            ->inner_join($gamesBuildsManager)
            ->filter($this->filters->byGameId($gameId))
            ->filter($this->filters->isActive())
            ->get_list();

        if ($activeBuildVersionSummaries)
            $activeBuildVersionSummaries = array_index($activeBuildVersionSummaries, DBField::UPDATE_CHANNEL);

        return $activeBuildVersionSummaries;
    }

    /**
     * @param Request $request
     * @param $gameIds
     * @return array
     */
    public function getGameActiveBuildVersionSummeriesByGameIds(Request $request, $gameIds)
    {

        $gamesBuildsManager = $request->managers->gamesBuilds();
        $gamesControllersManager = $request->managers->gamesControllers();

        $joinGameControllersFilter = $gamesBuildsManager->filters->And_(
            $gamesControllersManager->filters->byGameBuildId($gamesBuildsManager->createPkField()),
            $gamesControllersManager->filters->isActive()
        );

        $fields = [
            $gamesBuildsManager->createPkField(),
            $gamesBuildsManager->field(DBField::GAME_ID),
            $gamesBuildsManager->field(DBField::UPDATE_CHANNEL),
            $gamesBuildsManager->field(DBField::GAME_BUILD_VERSION),
            $gamesControllersManager->field(DBField::GAME_CONTROLLER_VERSION)
        ];

        try {
            $activeBuildVersionSummaries = $this->query($request->db)
                ->fields($fields)
                ->inner_join($gamesBuildsManager)
                ->left_join($gamesControllersManager, $joinGameControllersFilter)
                ->filter($this->filters->byGameId($gameIds))
                ->filter($this->filters->isActive())
                ->get_list();
        } catch (ObjectNotFound $e) {
            $activeBuildVersionSummaries = [];
        }

        return $activeBuildVersionSummaries;
    }


}

class GamesControllersManager extends BaseEntityManager
{
    protected $entityClass = GameControllerEntity::class;
    protected $table = Table::GameController;
    protected $table_alias = TableAlias::GameController;
    protected $pk = DBField::GAME_CONTROLLER_ID;

    const ZIP_ROOT_FILE_IDENTIFIER = "index.html";
    const MAIN_CSS_FILE_NAME = "main.css";
    const MAIN_JS_FILE_NAME = "bundle.js";

    public $foreign_managers = [
        GamesControllersTypesManager::class => DBField::GAME_CONTROLLER_TYPE_ID
    ];

    public static $fields = [
        DBField::GAME_CONTROLLER_ID,
        DBField::GAME_ID,
        DBField::GAME_BUILD_ID,
        DBField::UPDATE_CHANNEL,
        DBField::GAME_CONTROLLER_TYPE_ID,
        DBField::GAME_CONTROLLER_VERSION,
        DBField::VERSION_HASH,
        DBField::IS_ACTIVE,
        DBField::CREATED_BY,
        DBField::MODIFIED_BY,
        DBField::DELETED_BY
    ];


    /**
     * @param Request $request
     * @param GameControllerEntity $gameController
     * @return string
     */
    public function generateDestinationFolder(Request $request, GameEntity $game)
    {
        return $destinationFolder = "{$request->settings()->getMediaDir()}/games/{$game->getPk()}/controller-{$request->requestId}/";
    }

    /**
     * @return string
     */
    public function getZipRootFileIdentifier()
    {
        return self::ZIP_ROOT_FILE_IDENTIFIER;
    }

    /**
     * @param GameControllerEntity $data
     * @param Request $request
     * @return GameControllerEntity
     */
    public function processVFields(DBManagerEntity $gameController, Request $request)
    {
        $gameController->updateField(VField::GAME_CONTROLLER_ASSETS, []);
        $gameController->updateField(VField::URL, $request->getPlayUrl("/game-controller/{$gameController->getPk()}/"));
    }

    /**
     * @param Request $request
     * @param $gameId
     * @param $gameControllerTypeId
     * @param $gameControllerVersion
     * @return GameControllerEntity
     */
    public function createNewGameController(Request $request, $gameId, $gameBuildId, $updateChannel, $gameControllerTypeId, $gameControllerVersion, $versionHash = null)
    {
        $gameControllerData = [
            DBField::GAME_ID => $gameId,
            DBField::GAME_BUILD_ID => $gameBuildId,
            DBField::UPDATE_CHANNEL => $updateChannel,
            DBField::GAME_CONTROLLER_TYPE_ID => $gameControllerTypeId,
            DBField::GAME_CONTROLLER_VERSION => $gameControllerVersion,
            DBField::VERSION_HASH => $versionHash
        ];

        $gameController = $this->query($request->db)->createNewEntity($request, $gameControllerData);

        return $gameController;
    }

    /**
     * @param Request $request
     * @return SQLQuery
     */
    protected function queryJoinControllerTypes(Request $request)
    {
        $gamesControllersTypesManager = $request->managers->gamesControllersTypes();

        $fields = array_merge($this->createDBFields(), [$gamesControllersTypesManager->field(DBField::DISPLAY_NAME)]);

        return $this->query($request->db)
            ->fields($fields)
            ->inner_join($gamesControllersTypesManager);
    }

    /**
     * @param Request $request
     * @param array $games
     * @return FormField[]
     */
    public function getFormFields(Request $request, $games = [])
    {
        $gamesControllersTypesManager = $request->managers->gamesControllersTypes();

        $gameControllerTypes = $gamesControllersTypesManager->getAllGameControllerTypes($request);

        return [
            new SelectField(DBField::GAME_ID, 'Game ID', $games),
            new IntegerField(DBField::GAME_BUILD_ID, 'Game Build ID'),
            new SelectField(DBField::GAME_CONTROLLER_TYPE_ID, 'Game Controller Type Id', $gameControllerTypes),
            new CharField(DBField::GAME_CONTROLLER_VERSION, 'Game Controller Version Number', true)
        ];
    }

    /**
     * @param Request $request
     * @param GameControllerEntity[] $gameControllers
     * @throws ObjectNotFound
     */
    public function postProcessGameControllers(Request $request, $gameControllers = [])
    {
        $gamesAssetsManager = $request->managers->gamesAssets();
        $gamesControllersTypesManager = $request->managers->gamesControllersTypes();

        $gameControllerTypes = $gamesControllersTypesManager->getAllGameControllerTypes($request);
        $gameControllerTypes = $gamesControllersTypesManager->index($gameControllerTypes);

        if ($gameControllers instanceof GameControllerEntity)
            $gameControllers = [$gameControllers];

        if ($gameControllers) {
            $gameControllers = array_index($gameControllers, $this->getPkField());


            /** @var GameControllerEntity[] $gameControllers */
            foreach ($gameControllers as $gameController) {
                $gameController->setGameControllerType($gameControllerTypes[$gameController->getGameControllerTypeId()]);
            }

            //$gameControllerAssets = $gamesAssetsManager->getGameControllerAssetsByGameControllerIds($request, array_keys($gameControllers));
            $gameControllerAssets = $gamesAssetsManager->getCachedGameControllerAssetsByGameControllerIds($request, array_keys($gameControllers));

            foreach ($gameControllerAssets as $gameControllerAsset) {
                $gameControllers[$gameControllerAsset->getGameControllerId()]->setGameControllerAsset($gameControllerAsset);
            }
        }
    }

    /**
     * @param Request $request
     * @param $gameId
     * @param $updateChannel
     * @param $gameControllerTypeId
     * @param $gameControllerVersion
     * @param null $gameBuildId
     * @return bool
     */
    public function checkGameControllerVersionExists(Request $request, $gameId, $updateChannel, $gameControllerTypeId, $gameControllerVersion, $gameBuildId = null)
    {
        return $this->query($request->db)
            ->filter($this->filters->byGameBuildId($gameBuildId))
            ->filter($this->filters->byGameControllerTypeId($gameControllerTypeId))
            ->filter($this->filters->byGameId($gameId))
            ->filter($this->filters->byUpdateChannel($updateChannel))
            ->filter($this->filters->byGameControllerVersion($gameControllerVersion))
            ->filter($this->filters->isActive())
            ->exists();
    }


    /**
     * @param Request $request
     * @param $gameBuildId
     * @return GameControllerEntity[]
     */
    public function getGameControllersByGameBuildId(Request $request, $gameBuildId, $expand = true)
    {
        /** @var GameControllerEntity[] $gameControllers */
        $gameControllers = $this->queryJoinControllerTypes($request)
            ->filter($this->filters->byGameBuildId($gameBuildId))
            ->filter($this->filters->isActive())
            ->get_entities($request);

        if ($expand)
            $this->postProcessGameControllers($request, $gameControllers);

        return $gameControllers;
    }

    /**
     * @param Request $request
     * @param $gameControllerId
     * @param null $gameId
     * @param bool $expand
     * @return GameControllerEntity
     */
    public function getGameControllerById(Request $request, $gameControllerId, $gameId = null, $expand = true)
    {
        $queryBuilder = $this->queryJoinControllerTypes($request)
            ->filter($this->filters->byPk($gameControllerId));

        if ($gameId)
            $queryBuilder->filter($this->filters->byGameId($gameId));

        /** @var GameControllerEntity $gameController */
        $gameController = $queryBuilder->get_entity($request);

        if ($expand)
            $this->postProcessGameControllers($request, $gameController);

        return $gameController;
    }

    /**
     * @param Request $request
     * @param $gameBuildId
     * @param $gameControllerTypeId
     */
    public function deactivateOldGameControllerTypeForBuild(Request $request, $gameBuildId, $gameControllerTypeId, $newGameControllerId)
    {
        $updatedData = [
            DBField::DELETED_BY => $request->requestId,
            DBField::MODIFIED_BY => $request->requestId,
            DBField::IS_ACTIVE => 0
        ];

        $this->query($request->db)
            ->filter($this->filters->byGameBuildId($gameBuildId))
            ->filter($this->filters->byGameControllerTypeId($gameControllerTypeId))
            ->filter($this->filters->NotEq($this->getPkField(), $newGameControllerId))
            ->update($updatedData);
    }

    /**
     * @param Request $request
     * @return GameControllerEntity[]
     */
    public function getAllActiveGameControllers(Request $request)
    {
        /** @var GameControllerEntity[] $gameControllers */
        $gameControllers = $this->query($request->db)
            ->filter($this->filters->isActive())
            ->sort_desc($this->createPkField())
            ->get_entities($request);

        $this->postProcessGameControllers($request, $gameControllers);

        return $gameControllers;
    }

    /**
     * @param Request $request
     */
    public function ETL_summarizeVersionHashes(Request $request)
    {
        $gameControllerCount = $this->query($request->db)->count();

        $perPage = 10;

        if ($gameControllerCount > $perPage) {
            $pages = ceil($gameControllerCount/$perPage);
        } else {
            $pages = 1;
        }

        for ($i = 1; $i <= $pages; $i++) {
            /** @var GameControllerEntity[] $gameControllers */
            $gameControllers = $this->query($request->db)
                ->sort_asc($this->createPkField())
                ->paging($i, $perPage)
                ->get_entities($request);

            $this->postProcessGameControllers($request, $gameControllers);

            foreach ($gameControllers as $gameController) {

                if ($gameControllerAssets = $gameController->getGameControllerAssets()) {

                    $md5s = array_extract(DBField::MD5, $gameControllerAssets);
                    sort($md5s);

                    $hashString = '';

                    foreach ($md5s as $md5)
                        $hashString .= $md5;

                    if ($hashString)
                        $gameController->updateField(DBField::VERSION_HASH, sha1($hashString))->saveEntityToDb($request);
                }
            }

            unset($gameControllers);
        }
    }

    /**
     * @param Request $request
     * @param GameBuildEntity $oldGameBuild
     * @param GameBuildEntity $newGameBuild
     */
    public function cloneGameBuildControllers(Request $request, GameBuildEntity $oldGameBuild, GameBuildEntity $newGameBuild)
    {
        $contextXGamesAssetsManager = $request->managers->contextXGamesAssets();

        foreach ($oldGameBuild->getGameControllers() as $gameController) {
            $liveGameBuildController = $this->createNewGameController(
                $request,
                $newGameBuild->getGameId(),
                $newGameBuild->getPk(),
                $newGameBuild->getUpdateChannel(),
                $gameController->getGameControllerTypeId(),
                $gameController->getGameControllerVersion(),
                $gameController->getVersionHash()
            );
            foreach ($gameController->getGameControllerAssets() as $gameControllerAsset) {
                $liveGameControllerAsset = $contextXGamesAssetsManager->linkGameAssetToGameController(
                    $request,
                    $gameControllerAsset->getPk(),
                    $liveGameBuildController->getPk(),
                    $gameControllerAsset->getFolderPath(),
                    $gameControllerAsset->getFileName(),
                    $liveGameBuildController->getUpdateChannel()
                );
                $liveGameBuildController->setGameControllerAsset($liveGameControllerAsset);
            }
            $newGameBuild->setGameController($liveGameBuildController);
        }
    }
}

class GamesControllersTypesManager extends BaseEntityManager
{
    const ID_PLAYER = 1;
    const ID_GAME_ADMIN = 2;
    const ID_JOIN = 3;
    const ID_SPECTATOR = 4;
    const ID_CUSTOM = 5;

    const SLUG_PLAYER = "player";
    const SLUG_ADMIN = "admin";
    const SLUG_JOIN = "join";
    const SLUG_SPECTATOR = "spectator";
    const SLUG_CUSTOM = "custom";

    protected $entityClass = GameControllerTypeEntity::class;
    protected $table = Table::GameControllerType;
    protected $table_alias = TableAlias::GameControllerType;
    protected $pk = DBField::GAME_CONTROLLER_TYPE_ID;

    public static $fields = [
        DBField::GAME_CONTROLLER_TYPE_ID,
        DBField::DISPLAY_NAME,
        DBField::SLUG,
        DBField::IS_ACTIVE,
        DBField::CREATED_BY,
        DBField::MODIFIED_BY,
        DBField::DELETED_BY
    ];

    /**
     * @param Request $request
     * @return GameControllerTypeEntity[]
     */
    public function getAllGameControllerTypes(Request $request)
    {
        return $this->query($request->db)
            ->filter($this->filters->isActive())
            ->get_entities($request);
    }
}


class GamesBuildsControllersManager extends BaseEntityManager
{
    protected $entityClass = GameBuildControllerEntity::class;
    protected $table = Table::GameBuildController;
    protected $table_alias = TableAlias::GameBuildController;
    protected $pk = DBField::GAME_BUILD_CONTROLLER_ID;

    public static $fields = [
        DBField::GAME_BUILD_CONTROLLER_ID,
        DBField::GAME_BUILD_ID,
        DBField::GAME_CONTROLLER_ID,
        DBField::IS_ACTIVE,
        DBField::IS_DELETED,
        DBField::CREATED_BY,
        DBField::MODIFIED_BY,
        DBField::DELETED_BY
    ];

    /**
     * @param GameBuildControllerEntity $data
     * @param Request $request
     * @return GameBuildControllerEntity
     */
    public function processVFields(DBManagerEntity $data, Request $request)
    {

    }

    /**
     * @param Request $request
     * @param $gameBuildId
     * @param $gameControllerId
     * @return GameBuildControllerEntity
     */
    public function createNewGameBuildControllerLink(Request $request, $gameBuildId, $gameControllerId)
    {
        $data = [
            DBField::GAME_BUILD_ID => $gameBuildId,
            DBField::GAME_CONTROLLER_ID => $gameControllerId,
            DBField::IS_ACTIVE => 1,
        ];

        /** @var GameBuildControllerEntity $gameBuildController */
        $gameBuildController = $this->query($request->db)->createNewEntity($request, $data);

        return $gameBuildController;
    }

    public function ETL_copyOldControllersToLinks(Request $request)
    {
        $gamesControllersManager = $request->managers->gamesControllers();

        $gameControllerVersionMaps = [];

        $oldGameControllers = $gamesControllersManager->getAllActiveGameControllers($request);

        foreach ($oldGameControllers as $oldGameController) {

            if (!array_key_exists($oldGameController->getGameId(), $gameControllerVersionMaps))
                $gameControllerVersionMaps[$oldGameController->getGameId()] = [];

            if (!array_key_exists($oldGameController->getGameControllerVersion(), $gameControllerVersionMaps[$oldGameController->getGameId()])) {

                $gameControllerVersionMaps[$oldGameController->getGameId()][$oldGameController->getGameControllerVersion()] = $oldGameController;

            }

        }


        $gameControllerLink = $this->createNewGameBuildControllerLink(
            $request,
            $oldGameController->getGameBuildId(),
            $oldGameController->getPk()
        );

    }
}



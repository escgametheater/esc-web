<?php
/**
 * Created by PhpStorm.
 * User: christophercarter
 * Date: 6/6/18
 * Time: 12:25 AM
 */
Entities::uses('games');



class GamesManager extends BaseEntityManager {

    const ID_BOROUGH_GODS = 1;
    const ID_THE_RACES = 4;
    const ID_SHAKE_2 = 13;

    const SLUG_SHAKE_2 = 'shake-it-up-2';
    const SLUG_SHAKE_TRIAL = 'shake-it-up-trial';

    const GNS_KEY_PREFIX = GNS_ROOT.'game';

    protected $entityClass = GameEntity::class;
    protected $table = Table::Game;
    protected $table_alias = TableAlias::Game;
    protected $pk = DBField::GAME_ID;

    protected $foreign_managers = [
        GamesCategoriesManager::class => DBField::GAME_CATEGORY_ID,
    ];

    const UPDATE_CHANNEL_LIVE = 'live';
    const UPDATE_CHANNEL_DEV = 'dev';


    protected $updateChannels = [
        self::UPDATE_CHANNEL_DEV => 'Development / Test',
        self::UPDATE_CHANNEL_LIVE => 'Live / Public',
    ];

    /**
     * @param $gameId
     * @return string
     */
    public function generateEntityIdCacheKey($gameId)
    {
        return self::GNS_KEY_PREFIX.".id.{$gameId}";
    }

    /**
     * @param $gameSlug
     * @return string
     */
    public function generateEntityStringCacheKey($gameSlug)
    {
        return self::GNS_KEY_PREFIX.".slug.{$gameSlug}";
    }

    /**
     * @param $gameId
     */
    public function bustCache($gameId)
    {
        Cache::delete($this->generateEntityIdCacheKey($gameId));
    }


    /**
     * @param $updateChannel
     * @return string
     */
    public function getUpdateChannelDisplayName($updateChannel)
    {
        return $this->updateChannels[$updateChannel];
    }

    /**
     * @var array
     */
    protected $updateChannelOptions = [
        self::UPDATE_CHANNEL_DEV => [
            DBField::ID => self::UPDATE_CHANNEL_DEV,
            DBField::DISPLAY_NAME => 'Development'
        ],
        self::UPDATE_CHANNEL_LIVE => [
            DBField::ID => self::UPDATE_CHANNEL_LIVE,
            DBField::DISPLAY_NAME => 'Live / Public',
        ],
    ];

    public static $fields = [
        DBField::GAME_ID,
        DBField::SLUG,
        DBField::OWNER_TYPE_ID,
        DBField::OWNER_ID,
        DBField::GAME_TYPE_ID,
        DBField::CAN_MOD,
        DBField::GAME_CATEGORY_ID,
        DBField::GAME_ENGINE_ID,
        DBField::DISPLAY_NAME,
        DBField::DESCRIPTION,
        DBField::IS_DOWNLOADABLE,
        DBField::IS_WAN_ENABLED,
        DBField::IS_AGGREGATE_GAME,
        DBField::IS_ACTIVE,
        DBField::CREATED_BY,
        DBField::MODIFIED_BY,
        DBField::DELETED_BY
    ];

    public $removed_json_fields = [

    ];

    /**
     * @param GameEntity $data
     * @param Request $request
     */
    public function processVFields(DBManagerEntity $data, Request $request)
    {
        $imageTypeSizesManager = $request->managers->imagesTypesSizes();

        if (!$data->hasField(VField::OWNER_DISPLAY_NAME))
            $data->updateField(VField::OWNER_DISPLAY_NAME, null);

        if (!$data->hasField(VField::GAME_TYPE))
            $data->updateField(VField::GAME_TYPE, []);

        if (!$data->hasField(VField::GAME_BUILD))
            $data->updateField(VField::GAME_BUILD, []);

        if (!$data->hasField(VField::GAME_BUILDS))
            $data->updateField(VField::GAME_BUILDS, null);

        if (!$data->hasField(VField::GAME_MODS))
            $data->updateField(VField::GAME_MODS, []);

        if (!$data->hasField(VField::GAME_ENGINE))
            $data->updateField(VField::GAME_ENGINE, []);

        if (!$data->hasField(VField::GAME_CATEGORY))
            $data->updateField(VField::GAME_CATEGORY, []);

        if (!$data->hasField(VField::AVATAR))
            $data->updateField(VField::AVATAR, []);

        if (!$data->hasField(VField::IMAGES))
            $data->updateField(VField::IMAGES, []);

        if (!$data->hasField(VField::LATEST_GAME_VERSIONS))
            $data->updateField(VField::LATEST_GAME_VERSIONS, []);

        $data->updateField(VField::PARSED_DESCRIPTION, parse_post($data->getDescription()));

        if (!$data->hasField(VField::ORGANIZATION_SLUG))
            $data->updateField(VField::ORGANIZATION_SLUG, null);

        if ($data->getOrganizationSlug()) {
            $url = $request->getDevelopUrl("/teams/{$data->getOrganizationSlug()}/manage-games/{$data->getSlug()}");
        } else {
            $url = $request->getDevelopUrl("/account/manage-games/{$data->getSlug()}");
        }

        $data->updateField(VField::EDIT_URL, $url);

        $avatars = [];
        $imageTypeSizes = $imageTypeSizesManager->getAllImageTypeSizesByImageTypeId($request, ImagesTypesManager::ID_GAME_AVATAR);
        foreach ($imageTypeSizes as $imageTypeSize) {
            $avatars[$imageTypeSize->generateUrlField()] = $request->getWwwUrl("/static/images/placeholder-image.jpg?b=1");
        }
        $data->updateField(VField::AVATAR, $avatars);
    }

    /**
     * @param Request $request
     * @param GameEntity|GameEntity[] $games
     * @param bool $expand
     */
    public function postProcessGames(Request $request, $games, $expand = true, $updateChannel = null, $instOrgPerms = false)
    {
        $gamesAssetsManager = $request->managers->gamesAssets();
        $gamesEnginesManager = $request->managers->gamesEngines();
        $gamesBuildsManager = $request->managers->gamesBuilds();
        $gamesCategoriesManager = $request->managers->gamesCategories();
        $gamesTypesManager = $request->managers->gamesTypes();
        $organizationsManager = $request->managers->organizations();
        $imagesManager = $request->managers->images();
        $usersManager = $request->managers->users();

        if ($games instanceof GameEntity)
            $games = [$games];

        if ($games) {

            /** @var GameEntity[] $games */
            $games = array_index($games, $this->getPkField());
            $gameIds = array_keys($games);

            $ownerUserIds = [];
            $organizationIds = [];

            // Get Game Builds for Games
            $gameBuilds = $gamesBuildsManager->getActiveGameBuildsByGameIds($request, $gameIds, true);
            foreach ($gameBuilds as $gameBuild) {
                $games[$gameBuild->getGameId()]->setGameBuild($gameBuild);
            }

            // Get/set Game Categories & Engines for games
            $gameCategories = $gamesCategoriesManager->getAllGameCategories($request);
            $gameEngines = $gamesEnginesManager->getAllGameEngines($request);
            $gameTypes = $gamesTypesManager->getAllGameTypes($request);

            foreach ($games as $game) {
                $game->setGameCategory($gameCategories[$game->getGameCategoryId()]);
                $game->setGameEngine($gameEngines[$game->getGameEngineId()]);
                $game->setGameType($gameTypes[$game->getGameTypeId()]);

                if ($game->getOwnerTypeId() == EntityType::USER && !in_array($game->getOwnerId(), $ownerUserIds))
                    $ownerUserIds[] = $game->getOwnerId();

                if ($game->getOwnerTypeId() == EntityType::ORGANIZATION)
                    $organizationIds[] = $game->getOwnerId();
            }

            $users = [];
            if ($ownerUserIds) {
                $users = $usersManager->getUsersByIds($request, $ownerUserIds);
                /** @var UserEntity[] $users */
                $users = array_index($users, $usersManager->getPkField());
            }

            // Get/set organizations for games
            if ($organizationIds) {
                $organizations = $organizationsManager->getOrganizationsByIds($request, $organizationIds, true, false);

                foreach ($games as $game) {
                    if ($game->getOwnerTypeId() == EntityType::ORGANIZATION) {
                        $organization = $organizations[$game->getOwnerId()];
                        $game->setOrganization($organization);
                        $game->setOwnerDisplayName($organization->getDisplayName());
                    } elseif ($game->getOwnerTypeId() == EntityType::USER && array_key_exists($game->getOwnerId(), $users)) {
                        $user = $users[$game->getOwnerId()];
                        $displayName = $user->getDisplayName() ? $user->getDisplayName() : $user->getUsername();
                        $game->setOwnerDisplayName($displayName);
                    }
                }
            }
            // Get/set game thumbnail images
            $avatarImages = $imagesManager->getActiveGameAvatarImagesByGameIds($request, $gameIds);
            foreach ($avatarImages as $avatarImage) {
                $games[$avatarImage->getContextEntityId()]->setAvatarImageUrls($avatarImage);
            }
        }
    }


    /**
     * @param Request $request
     * @param GameEntity|GameEntity[] $games
     * @param bool $expand
     */
    public function postProcessHostGames(Request $request, $games, $expand = true, $userId = null, $updateChannel = null)
    {
        $gamesAssetsManager = $request->managers->gamesAssets();
        $gamesEnginesManager = $request->managers->gamesEngines();
        $gamesBuildsManager = $request->managers->gamesBuilds();
        $gamesCategoriesManager = $request->managers->gamesCategories();
        $organizationsManager = $request->managers->organizations();
        $imagesManager = $request->managers->images();
        $usersManager = $request->managers->users();
        $gamesModsManager = $request->managers->gamesMods();

        if (!$userId)
            $userId = $request->user->id;

        if ($games instanceof GameEntity)
            $games = [$games];

        if ($games) {

            /** @var GameEntity[] $games */
            $games = array_index($games, $this->getPkField());
            $gameIds = array_keys($games);

            $ownerUserIds = [];

            $organizationIds = [];

            $latestGameVersionSummaries = [];

            // Get Game Builds for Games
            $customGameAssetsHack = []; // This is a hack for backwards compatibility in the host for now.
            $gameBuilds = $gamesBuildsManager->getUserPlayableGameBuildsByGameIds($request, $gameIds, $userId, $updateChannel);

            $gameBuildIdVersions = [];

            foreach ($gameBuilds as $gameBuild) {

                $games[$gameBuild->getGameId()]->setGameBuild($gameBuild);

                if (!in_array($gameBuild->getPk(), $gameBuildIdVersions)) {
                    $gameBuildIdVersions[] = $gameBuild->getPk();

                    $latestGameVersionSummaries[$gameBuild->getGameId()][] = [
                        DBField::GAME_BUILD_ID => $gameBuild->getPk(),
                        DBField::UPDATE_CHANNEL => $gameBuild->getUpdateChannel(),
                        DBField::GAME_BUILD_VERSION => $gameBuild->getGameBuildVersion(),
                        DBField::DISPLAY_NAME => $this->getUpdateChannelDisplayName($gameBuild->getUpdateChannel())
                    ];
                }

                if (!array_key_exists($gameBuild->getGameId(), $customGameAssetsHack))
                    $customGameAssetsHack[$gameBuild->getGameId()] = [];

                foreach ($gameBuild->getCustomGameAssets() as $customGameAsset) {
                    $customGameAssetsHack[$customGameAsset->getGameId()][] = $customGameAsset;
                }
            }

            foreach ($latestGameVersionSummaries as $gameId => $gameVersionSummary)
                $games[$gameId]->assign([VField::LATEST_GAME_VERSIONS => $gameVersionSummary]);

            $gameMods = $gamesModsManager->getPlayableGameModsByUserIdAndGameIds($request, $userId, $gameIds, $updateChannel);
            foreach ($gameMods as $gameMod) {
                $games[$gameMod->getGameId()]->setGameMod($gameMod);
            }

            // Get/set Game Categories & Engines for games
            $gameCategories = $gamesCategoriesManager->getAllGameCategories($request);
            $gameEngines = $gamesEnginesManager->getAllGameEngines($request);
            foreach ($games as $game) {

                if ($game->getOwnerTypeId() == EntityType::USER && !in_array($game->getOwnerId(), $ownerUserIds))
                    $ownerUserIds[] = $game->getOwnerId();

                $game->setGameCategory($gameCategories[$game->getGameCategoryId()]);
                $game->setGameEngine($gameEngines[$game->getGameEngineId()]);

                if ($game->getOwnerTypeId() == EntityType::ORGANIZATION)
                    $organizationIds[] = $game->getOwnerId();
            }

            $users = [];
            if ($ownerUserIds) {
                $users = $usersManager->getUsersByIds($request, $ownerUserIds);
                /** @var UserEntity[] $users */
                $users = array_index($users, $usersManager->getPkField());
            }

            // Get/set organizations for games
            if ($organizationIds) {
                $organizations = $organizationsManager->getOrganizationsByIds($request, $organizationIds, true, false);

                foreach ($games as $game) {
                    if ($game->getOwnerTypeId() == EntityType::ORGANIZATION) {
                        $organization = $organizations[$game->getOwnerId()];
                        $game->setOrganization($organization);
                        $game->setOwnerDisplayName($organization->getDisplayName());
                    } elseif ($game->getOwnerTypeId() == EntityType::USER && array_key_exists($game->getOwnerId(), $users)) {
                        $user = $users[$game->getOwnerId()];
                        $displayName = $user->getDisplayName() ? $user->getDisplayName() : $user->getUsername();
                        $game->setOwnerDisplayName($displayName);
                    }
                }
            }

            // Get/set game thumbnail images
            $avatarImages = $imagesManager->getActiveGameAvatarImagesByGameIds($request, $gameIds);
            foreach ($avatarImages as $avatarImage) {
                $games[$avatarImage->getContextEntityId()]->setAvatarImageUrls($avatarImage);
            }
        }
    }

    /**
     * @param Request $request
     * @return SQLQuery
     */
    public function queryJoinOrganizations(Request $request, $active = false)
    {
        $organizationsManager = $request->managers->organizations();

        $joinOrganizationsFilter = $this->filters->And_(
            $this->filters->byOwnerTypeId(EntityType::ORGANIZATION),
            $this->filters->byOwnerId($organizationsManager->createPkField()),
            $active ? $organizationsManager->filters->isActive() : null
        );

        $fields = $this->createDBFields();
        $fields[] = $organizationsManager->aliasField(DBField::SLUG, VField::ORGANIZATION_SLUG);

        $queryBuilder = $this->query($request->db)
            ->fields($fields)
            ->inner_join($organizationsManager, $joinOrganizationsFilter);

        return $queryBuilder;
    }

    /**
     * @return array
     */
    public function getUpdateChannelOptions()
    {
        return $this->updateChannelOptions;
    }

    /**
     * @param $updateChannel
     * @return array
     */
    public function getUpdateChannelOption($updateChannel)
    {
        return $this->isValidUpdateChannel($updateChannel) ? $this->updateChannelOptions[$updateChannel] : [];
    }

    /**
     * @param $updateChannel
     * @return bool
     */
    public function isValidUpdateChannel($updateChannel)
    {
        return array_key_exists($updateChannel, $this->updateChannelOptions);
    }


    /**
     * @param bool $requirePk
     * @return FormField[]
     */
    public function getFormFields(Request $request, $requirePk = true)
    {
        $gamesCategoriesManager = $request->managers->gamesCategories();
        $gamesTypesManager = $request->managers->gamesTypes();

        $gameCategories = $gamesCategoriesManager->getAllActiveGameCategories($request);
        $gameTypes = $gamesTypesManager->getAllActiveGameTypes($request);

        $fields = [
            new IntegerField(DBField::ID, 'Game ID', $requirePk),
            new IntegerField(DBField::OWNER_TYPE_ID, 'Owner Type ID', false),
            new IntegerField(DBField::OWNER_ID, 'Owner ID', false),
            new CharField(DBField::SLUG, 'Slug', 100, false),
            new CharField(DBField::DISPLAY_NAME, 'Name', 100, true),
            new SelectField(DBField::GAME_TYPE_ID, 'Game Type Id', $gameTypes, false),
            new SelectField(DBField::GAME_CATEGORY_ID, 'Game Category Id', $gameCategories)
        ];

        return $fields;
    }

    /**
     * @param string $slug
     * @return bool
     */
    public function checkSlugExists($slug)
    {
        return $this->query()
            ->filter($this->filters->bySlug($slug))
            ->filter($this->filters->isActive())
            ->limit(1)
            ->exists();
    }

    /**
     * @param Request $request
     * @param $userId
     * @return AndFilter
     */
    public function getJoinGameLicenseFilter(Request $request, $userId, $updateChannel = GamesManager::UPDATE_CHANNEL_LIVE)
    {
        $gameLicensesManager = $request->managers->gameLicenses();

        $expireDt = new DateTime();

        return $this->filters->And_(
            $gameLicensesManager->filters->byGameId($this->createPkField()),
            $gameLicensesManager->filters->byUserId($userId),
            $gameLicensesManager->filters->byUpdateChannel($updateChannel),
            $gameLicensesManager->filters->isActive(),
            $this->filters->Or_(
                $gameLicensesManager->filters->IsNull(DBField::END_TIME),
                $this->filters->And_(
                    $gameLicensesManager->filters->IsNotNull(DBField::END_TIME),
                    $gameLicensesManager->filters->Gt(DBField::END_TIME, $expireDt->format(SQL_DATETIME))
                )
            )
        );
    }

    /**
     * @param Request $request
     * @param $userId
     * @return OrFilter
     */
    public function getAccessibleGamesWhereFilter(Request $request, $userId)
    {
        $gameLicensesManager = $request->managers->gameLicenses();
        $organizationsUsersManager = $request->managers->organizationsUsers();

        return $this->filters->Or_(
            $gameLicensesManager->filters->IsNotNull($gameLicensesManager->createPkField()),
            $this->filters->And_(
                $this->filters->byOwnerTypeId(EntityType::USER),
                $this->filters->byOwnerId($userId)
            ),
            $this->filters->And_(
                $this->filters->byOwnerTypeId(EntityType::ORGANIZATION),
                $organizationsUsersManager->filters->IsNotNull($organizationsUsersManager->getPkField())
            )
        );
    }

    /**
     * @param Request $request
     * @param $userId
     * @return OrFilter
     */
    public function getJoinGameLicenseWhereFilter(Request $request, $userId)
    {
        $gameLicensesManager = $request->managers->gameLicenses();

        return $this->filters->Or_(
            $gameLicensesManager->filters->IsNotNull($gameLicensesManager->createPkField()),
            $this->filters->And_(
                $this->filters->byOwnerTypeId(EntityType::USER),
                $this->filters->byOwnerId($userId)
            )
        );
    }

    /**
     * @param Request $request
     * @param $displayName
     * @param null $ownerTypeId
     * @param null $ownerId
     * @return GameEntity
     */
    public function createNewGame(Request $request, $slug = null, $displayName, $ownerTypeId = null, $ownerId = null,
                                  $gameCategoryId = 1, $gameTypeId = GamesTypesManager::ID_MULTI_PLAYER,
                                  $gameEngineId = GamesEnginesManager::ID_UNITY, $canMod = 0, $organizationSlug = null)
    {
        $origSlug = slugify($displayName);

        if ($gameTypeId == GamesTypesManager::ID_MOD_HACK) {
            $origSlug = uuidV4HostName();
        }
        else if($slug) {
            $origSlug = $slug;
        }

        $slug = $origSlug;

        $slugExists = $this->checkSlugExists($origSlug);

        $i = 0;

        while ($slugExists) {
            $i++;
            $slug = "{$origSlug}-{$i}";
            $slugExists = $this->checkSlugExists($slug);
        }

        $data = [
            DBField::OWNER_TYPE_ID => $ownerTypeId,
            DBField::OWNER_ID => $ownerId,
            DBField::GAME_TYPE_ID => $gameTypeId,
            DBField::CAN_MOD => $canMod,
            DBField::GAME_CATEGORY_ID => $gameCategoryId,
            DBField::GAME_ENGINE_ID => $gameEngineId,
            DBField::DISPLAY_NAME => $displayName,
            DBField::SLUG => $slug,
            DBField::IS_ACTIVE => 1,
            VField::ORGANIZATION_SLUG => $organizationSlug
        ];

        /** @var GameEntity $game */
        $game = $this->query($request->db)->createNewEntity($request, $data, false);

        return $this->createDefaultGameBuild($request, $game);
    }

    /**
     * @param Request $request
     * @param GameEntity $newGame
     * @return GameEntity
     */
    public function createDefaultGameBuild($request, $newGame)
    {
        $gamesBuildsManager = $request->managers->gamesBuilds();
        $gamesActiveBuildsManager = $request->managers->gamesActiveBuilds();

        // Create default empty game build
        $newGameBuild = $gamesBuildsManager->createNewGameBuild(
            $request,
            $newGame->getPk(),
            GamesManager::UPDATE_CHANNEL_DEV,
            "0.0.1",
            null,
            0,
            0
        );

        $gamesActiveBuildsManager->createUpdateGameActiveBuild($request, $newGame->getPk(), $newGameBuild->getUpdateChannel(), $newGameBuild->getPk());

        $this->postProcessGames($request, $newGame, true, GamesManager::UPDATE_CHANNEL_DEV);

        return $newGame;
    }

    /**
     * @param Request $request
     * @param $gameId
     * @return array|GameEntity
     */
    public function getGameById(Request $request, $gameId, $expand = false)
    {
        $game = $this->queryJoinOrganizations($request)
            ->filter($this->filters->byPk($gameId))
            ->cache($this->generateEntityIdCacheKey($gameId), ONE_HOUR)
            //->filter($this->filters->isActive())
            ->get_entity($request);

        $this->postProcessGames($request, $game, $expand);

        return $game;
    }

    /**
     * @param Request $request
     * @param $gameIds
     * @return GameEntity[]
     */
    public function getGamesByIds(Request $request, $gameIds, $expand = false)
    {
        /** @var GameEntity[] $games */
        $games = $this->getEntitiesByPks($request, $gameIds, ONE_HOUR, $this->queryJoinOrganizations($request));

        $this->postProcessGames($request, $games, $expand);

        return $games;
    }

    /**
     * @param Request $request
     * @param $ownerId
     * @param bool $expand
     * @param int $entityTypeId
     * @return GameEntity[]
     */
    public function getGamesByOwnerId(Request $request, $ownerId, $expand = false, $entityTypeId = EntityType::USER)
    {
        $games = $this->queryJoinOrganizations($request)
            ->filter($this->filters->byOwnerTypeId($entityTypeId))
            ->filter($this->filters->byOwnerId($ownerId))
            ->filter($this->filters->isActive())
            ->sort_asc($this->field(DBField::DISPLAY_NAME))
            ->get_entities($request);

        $this->postProcessGames($request, $games, $expand);

        return $games;
    }

    /**
     * @param Request $request
     * @param $excludeGameIds
     * @return GameEntity[]
     */
    public function getAdminGamesByNotGameId(Request $request, $excludeGameIds)
    {
        $queryBuilder = $this->queryJoinOrganizations($request)
            ->filter($this->filters->isActive())
            ->sort_asc(DBField::DISPLAY_NAME);

        if ($excludeGameIds)
            $queryBuilder->filter($this->filters->NotEq($this->getPkField(), $excludeGameIds));

        /** @var GameEntity[] $games */
        $games = $queryBuilder->get_entities($request);

        $this->postProcessGames($request, $games);

        return $games;
    }

    /**
     * @param Request $request
     * @param $slug
     * @return array|GameEntity
     */
    public function getGameBySlug(Request $request, $slug, $expand = false)
    {
        /** @var GameEntity $game */
        $game = $this->queryJoinOrganizations($request)
            ->filter($this->filters->bySlug($slug))
            ->get_entity($request);

        if ($expand)
            $this->postProcessGames($request, $game);

        return $game;
    }

    /**
     * @param Request $request
     * @param $slug
     * @param bool $isEscAdmin
     * @param bool $expand
     * @return GameEntity
     */
    public function getEditableGameBySlug(Request $request, $slug, $isEscAdmin = false, $expand = false)
    {
        $organizationsUsersManager = $request->managers->organizationsUsers();

        if ($isEscAdmin) {
            /** @var GameEntity $game */
            $game = $this->queryJoinOrganizations($request)
                ->filter($this->filters->bySlug($slug))
                ->get_entity($request);

        } else {

            $joinOrganizationsUsersFilter = $this->filters->And_(
                $this->filters->byOwnerTypeId(EntityType::ORGANIZATION),
                $organizationsUsersManager->filters->byOrganizationId($this->field(DBField::OWNER_ID)),
                $organizationsUsersManager->filters->byUserId($request->user->id),
                $organizationsUsersManager->filters->isActive()
            );

            /** @var GameEntity $game */
            $game = $this->queryJoinOrganizations($request)
                ->left_join($organizationsUsersManager, $joinOrganizationsUsersFilter)
                ->filter($this->filters->bySlug($slug))
                ->filter($organizationsUsersManager->filters->IsNotNull($organizationsUsersManager->createPkField()))
                ->get_entity($request);
        }

        if ($expand)
            $this->postProcessGames($request, $game, $expand, null, $expand);

        return $game;
    }

    /**
     * @param Request $request
     * @param $userId
     * @return GameEntity[]
     */
    public function getPlayableGamesByUserId(Request $request, $userId, $updateChannel = null, $gameTypeId = null)
    {
        $gamesActiveBuildsManager = $request->managers->gamesActiveBuilds();
        $gamesBuildsManager = $request->managers->gamesBuilds();
        $gameLicensesManager = $request->managers->gameLicenses();
        $contextXGamesAssetsManager = $request->managers->contextXGamesAssets();

        // Filter for joining OrganizationUsers
        $organizationsUsersManager = $request->managers->organizationsUsers();
        $joinOrganizationsUsersFilter = $this->filters->And_(
            $organizationsUsersManager->filters->isActive(),
            $organizationsUsersManager->filters->byUserId($userId)
        );

        // Filter on how to join the organizations rights table
        $organizationsRightsManager = $request->managers->organizationsRights();
        $joinOrganizationsRightsFilter = $this->filters->And_(
            $organizationsRightsManager->filters->byOrganizationId($organizationsUsersManager->field(DBField::ORGANIZATION_ID))
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
            $organizationsGamesLicensesManager->filters->byGameId($this->createPkField()),
            $gamesActiveBuildsManager->filters->byUpdateChannel(GamesManager::UPDATE_CHANNEL_LIVE),
            $organizationsGamesLicensesManager->getActiveLicenseFilter($request->getCurrentSqlTime())
        );

        $whereFilter = $this->filters->Or_(
            // If there's a user license
            $gameLicensesManager->filters->IsNotNull($gameLicensesManager->createPkField()),
            // Or the user has permission to use a game build of the correct update channel
            $this->filters->And_(
                $organizationsPermissionsManager->filters->IsNotNull($organizationsPermissionsManager->createPkField()),
                $this->filters->byOwnerTypeId(EntityType::ORGANIZATION),
                $organizationsUsersManager->filters->byOrganizationId($this->field(DBField::OWNER_ID))
            ),
            // Or the organization has a license to use a game
            $organizationsGamesLicensesManager->filters->IsNotNull($organizationsGamesLicensesManager->createPkField())
        );

        $joinContextXGamesAssetsFilter = $this->filters->And_(
            $contextXGamesAssetsManager->filters->byContextEntityTypeId(EntityType::GAME_BUILD),
            $contextXGamesAssetsManager->filters->byContextEntityId($gamesBuildsManager->createPkField()),
            $contextXGamesAssetsManager->filters->isActive()
        );

        /** @var GameEntity[] $games */
        $games = $this->queryJoinOrganizations($request)
            ->inner_join($gamesActiveBuildsManager, $gamesActiveBuildsManager->filters->byGameId($this->createPkField()))
            ->inner_join($gamesBuildsManager, $gamesBuildsManager->filters->byPk($gamesActiveBuildsManager->field(DBField::GAME_BUILD_ID)))
            ->inner_join($contextXGamesAssetsManager, $joinContextXGamesAssetsFilter)
            ->left_join($gameLicensesManager, $this->getJoinGameLicenseFilter($request, $userId, $gamesActiveBuildsManager->field(DBField::UPDATE_CHANNEL)))
            ->left_join($organizationsUsersManager, $joinOrganizationsUsersFilter)
            ->left_join($organizationsRightsManager, $joinOrganizationsRightsFilter)
            ->left_join($organizationsBaseRightsManager, $joinOrganizationsBaseRightsFilter)
            ->left_join($organizationsPermissionsManager, $joinOrganizationsPermissionsFilter)
            ->left_join($organizationsGamesLicensesManager, $joinOrganizationsGamesLicensesFilter)
            ->filter($whereFilter)
            ->filter($this->filters->byGameTypeId($gameTypeId))
            ->filter($gamesActiveBuildsManager->filters->byUpdateChannel($updateChannel))
            ->filter($gamesActiveBuildsManager->filters->isActive())
            ->filter($this->filters->isDownloadable())
            ->filter($this->filters->isActive())
            ->sort_asc($this->field(DBField::DISPLAY_NAME))
            ->group_by($this->createPkField())
            ->get_entities($request);

        $this->postProcessHostGames($request, $games, true, $userId, $updateChannel);

        return $games;
    }

    /**
     * @param Request $request
     * @param $userId
     * @return GameEntity[]
     */
    public function getDevGamesByUserId(Request $request, $userId)
    {
        // Filter for joining OrganizationUsers
        $organizationsUsersManager = $request->managers->organizationsUsers();
        $joinOrganizationsUsersFilter = $this->filters->And_(
            $this->filters->byOwnerTypeId(EntityType::ORGANIZATION),
            $organizationsUsersManager->filters->byOrganizationId($this->field(DBField::OWNER_ID)),
            $organizationsUsersManager->filters->isActive(),
            $organizationsUsersManager->filters->byUserId($userId)
        );

        // Filter on how to join the organizations rights table
        $organizationsRightsManager = $request->managers->organizationsRights();
        $joinOrganizationsRightsFilter = $this->filters->And_(
            $organizationsRightsManager->filters->byOrganizationId($organizationsUsersManager->field(DBField::ORGANIZATION_ID))
        );

        // Filter for joining the rights. We want to join against dev and live channel permissions depending on the active build.
        $organizationsBaseRightsManager = $request->managers->organizationsBaseRights();
        $joinOrganizationsBaseRightsFilter = $this->filters->And_(
            $organizationsRightsManager->filters->byOrganizationBaseRightId($organizationsBaseRightsManager->createPkField()),
            $this->filters->And_(
                $organizationsBaseRightsManager->filters->byName(OrganizationsBaseRightsManager::RIGHT_ORG_GAMES_CHANNELS_DEV)
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
            // Or the user has permission to use a game build of the correct update channel
            $organizationsPermissionsManager->filters->IsNotNull($organizationsPermissionsManager->createPkField()),
            // Or the user owns the game directly (ToDo: Deprecate user ownership)
            $this->filters->And_(
                $this->filters->byOwnerTypeId(EntityType::USER),
                $this->filters->byOwnerId($userId)
            )
        );

        /** @var GameEntity[] $games */
        $games = $this->queryJoinOrganizations($request)
            ->left_join($organizationsUsersManager, $joinOrganizationsUsersFilter)
            ->left_join($organizationsRightsManager, $joinOrganizationsRightsFilter)
            ->left_join($organizationsBaseRightsManager, $joinOrganizationsBaseRightsFilter)
            ->left_join($organizationsPermissionsManager, $joinOrganizationsPermissionsFilter)
            ->filter($whereFilter)
            ->filter($this->filters->isDownloadable())
            ->filter($this->filters->isActive())
            ->sort_asc($this->field(DBField::DISPLAY_NAME))
            ->group_by($this->createPkField())
            ->get_entities($request);

        $this->postProcessHostGames($request, $games, true, $userId, GamesManager::UPDATE_CHANNEL_DEV);

        return $games;
    }


    /**
     * @param Request $request
     * @param $userId
     * @param string $updateChannel
     * @return GameEntity[]
     */
    public function getAvailableOfflineGamesByUserId(Request $request, $userId, $updateChannel = GamesManager::UPDATE_CHANNEL_LIVE)
    {
        $gamesActiveBuildsManager = $request->managers->gamesActiveBuilds();
        $gamesBuildsManager = $request->managers->gamesBuilds();
        $gameLicensesManager = $request->managers->gameLicenses();

        // Filter for joining OrganizationUsers
        $organizationsUsersManager = $request->managers->organizationsUsers();
        $joinOrganizationsUsersFilter = $this->filters->And_(
            $this->filters->byOwnerTypeId(EntityType::ORGANIZATION),
            $organizationsUsersManager->filters->byOrganizationId($this->field(DBField::OWNER_ID)),
            $organizationsUsersManager->filters->isActive(),
            $organizationsUsersManager->filters->byUserId($userId)
        );

        // Filter on how to join the organizations rights table
        $organizationsRightsManager = $request->managers->organizationsRights();
        $joinOrganizationsRightsFilter = $this->filters->And_(
            $organizationsRightsManager->filters->byOrganizationId($organizationsUsersManager->field(DBField::ORGANIZATION_ID))
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

        $whereFilter = $this->filters->Or_(
        // If there's a user license
            $gameLicensesManager->filters->IsNotNull($gameLicensesManager->createPkField()),
            // Or the user has permission to use a game build of the correct update channel
            $organizationsPermissionsManager->filters->IsNotNull($organizationsPermissionsManager->createPkField())
        );

        /** @var GameEntity[] $games */
        $games = $this->queryJoinOrganizations($request)
            ->inner_join($gamesActiveBuildsManager, $gamesActiveBuildsManager->filters->byGameId($this->createPkField()))
            ->inner_join($gamesBuildsManager, $gamesBuildsManager->filters->byPk($gamesActiveBuildsManager->field(DBField::GAME_BUILD_ID)))
            ->left_join($gameLicensesManager, $this->getJoinGameLicenseFilter($request, $userId, $gamesActiveBuildsManager->field(DBField::UPDATE_CHANNEL)))
            ->left_join($organizationsUsersManager, $joinOrganizationsUsersFilter)
            ->left_join($organizationsRightsManager, $joinOrganizationsRightsFilter)
            ->left_join($organizationsBaseRightsManager, $joinOrganizationsBaseRightsFilter)
            ->left_join($organizationsPermissionsManager, $joinOrganizationsPermissionsFilter)
            ->filter($whereFilter)
            ->filter($gamesActiveBuildsManager->filters->byUpdateChannel($updateChannel))
            ->filter($gamesActiveBuildsManager->filters->isActive())
            ->filter($this->filters->byGameTypeId(GamesTypesManager::ID_CLOUD_GAME))
            ->filter($this->filters->isActive())
            ->group_by($this->createPkField())
            ->sort_asc($this->field(DBField::DISPLAY_NAME))
            ->get_entities($request);

        // $this->postProcessHostGames($request, $games);

        return $games;
    }

    /**
     * @param Request $request
     * @param $organizationId
     * @param string $updateChannel
     * @return GameEntity[]
     */
    public function getAvailableGamesByTypeAndOrganizationId(Request $request, $organizationId, $gameTypeId = GamesTypesManager::ID_CLOUD_GAME, $updateChannel = GamesManager::UPDATE_CHANNEL_LIVE)
    {
        $gamesActiveBuildsManager = $request->managers->gamesActiveBuilds();
        $gamesBuildsManager = $request->managers->gamesBuilds();
        $gameLicensesManager = $request->managers->gameLicenses();

        $organizationsGameLicensesManager = $request->managers->organizationsGamesLicenses();
        $joinOrganizationsGamesLicensesFilter = $this->filters->And_(
            $organizationsGameLicensesManager->filters->byOrganizationId($organizationId),
            $organizationsGameLicensesManager->filters->byGameId($this->createPkField()),
            $organizationsGameLicensesManager->getActiveLicenseFilter($request->getCurrentSqlTime())
        );

        $gameTypeIsDownloadableFilter = $gameTypeId == GamesTypesManager::ID_MULTI_PLAYER ? $this->filters->isDownloadable() : null;

        $whereFilter = $this->filters->Or_(
            $this->filters->And_(
                $this->filters->byOwnerTypeId(EntityType::ORGANIZATION),
                $this->filters->byOwnerId($organizationId)
            ),
            // Or has license to use game
            $organizationsGameLicensesManager->filters->IsNotNull($organizationsGameLicensesManager->createPkField())
        );

        /** @var GameEntity[] $games */
        $games = $this->queryJoinOrganizations($request)
            ->inner_join($gamesActiveBuildsManager, $gamesActiveBuildsManager->filters->byGameId($this->createPkField()))
            ->inner_join($gamesBuildsManager, $gamesBuildsManager->filters->byPk($gamesActiveBuildsManager->field(DBField::GAME_BUILD_ID)))
            ->left_join($gameLicensesManager, $this->getJoinGameLicenseFilter($request, $organizationId, $gamesActiveBuildsManager->field(DBField::UPDATE_CHANNEL)))
            ->left_join($organizationsGameLicensesManager, $joinOrganizationsGamesLicensesFilter)
            ->filter($whereFilter)
            ->filter($gamesActiveBuildsManager->filters->byUpdateChannel($updateChannel))
            ->filter($gamesActiveBuildsManager->filters->isActive())
            ->filter($gameTypeIsDownloadableFilter)
            ->filter($this->filters->byGameTypeId($gameTypeId))
            ->filter($this->filters->isActive())
            ->group_by($this->createPkField())
            ->sort_asc($this->field(DBField::DISPLAY_NAME))
            ->get_entities($request);

        // $this->postProcessHostGames($request, $games);

        return $games;
    }


    /**
     * @param Request $request
     * @return GameEntity[]
     */
    public function getAssignableCloudGames(Request $request, $expand = false)
    {
        $games = $this->queryJoinOrganizations($request)
            ->filter($this->filters->isActive())
            ->filter($this->filters->byGameTypeId(GamesTypesManager::ID_CLOUD_GAME))
            ->filter($this->filters->canMod())
            ->get_entities($request);

        $this->postProcessGames($request, $games, $expand);

        return $this->index($games);
    }

    /**
     * @param Request $request
     * @param bool $postProcess
     * @return GameEntity[]
     */
    public function getAssignableLbeGames(Request $request, $expand = false)
    {
        $games = $this->queryJoinOrganizations($request)
            ->filter($this->filters->isActive())
            ->filter($this->filters->byGameTypeId(GamesTypesManager::ID_MULTI_PLAYER))
            ->filter($this->filters->isDownloadable())
            ->filter($this->filters->canMod())
            ->get_entities($request);

        $this->postProcessGames($request, $games, $expand);

        return $this->index($games);
    }

    /**
     * @param Request $request
     * @param $query
     * @param int $maxGamesResults
     * @param null $userId
     * @param bool $isGamesAdmin
     * @return array|GameEntity[]
     */
    public function searchAutoCompleteGames(Request $request, $query, $maxGamesResults = 3, $userId = null, $isGamesAdmin = false)
    {
        $organizationsManager = $request->managers->organizations();
        $imagesManager = $request->managers->images();

        $gameTitleFilter = $this->filters->Or_(
            $organizationsManager->filters->Like(DBField::DISPLAY_NAME, $query),
            $this->filters->Like(DBField::DISPLAY_NAME, $query)
        );

        $queryBuilder = $this->queryJoinOrganizations($request)
            ->filter($gameTitleFilter)
            ->filter($this->filters->isActive())
            ->limit($maxGamesResults)
            ->group_by($this->createPkField());

        if (Validators::int($userId) && $userId && !$isGamesAdmin) {

            // Filter for joining OrganizationUsers
            $organizationsUsersManager = $request->managers->organizationsUsers();
            $joinOrganizationsUsersFilter = $this->filters->And_(
                $this->filters->byOwnerTypeId(EntityType::ORGANIZATION),
                $organizationsUsersManager->filters->byOrganizationId($organizationsManager->createPkField()),
                $organizationsUsersManager->filters->isActive(),
                $organizationsUsersManager->filters->byUserId($userId)
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
                $organizationsBaseRightsManager->filters->byName(OrganizationsBaseRightsManager::RIGHT_ORG_GAMES_PROFILE)
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

            $whereFilter = $organizationsPermissionsManager->filters->IsNotNull($organizationsPermissionsManager->createPkField());

            $queryBuilder
                ->inner_join($organizationsUsersManager, $joinOrganizationsUsersFilter)
                ->inner_join($organizationsRightsManager, $joinOrganizationsRightsFilter)
                ->inner_join($organizationsBaseRightsManager, $joinOrganizationsBaseRightsFilter)
                ->inner_join($organizationsPermissionsManager, $joinOrganizationsPermissionsFilter)
                ->filter($whereFilter);
        }

        /** @var GameEntity[] $games */
        $games = $queryBuilder->get_entities($request);

        if ($games) {
            $games = $this->index($games);
            $avatarImages = $imagesManager->getActiveGameAvatarImagesByGameIds($request, extract_pks($games));
            foreach ($avatarImages as $avatarImage) {
                $games[$avatarImage->getContextEntityId()]->setAvatarImageUrls($avatarImage);
            }
        }

        return $games;
    }
}

class GamesTypesManager extends BaseEntityManager
{
    const ID_MULTI_PLAYER = 1;
    const ID_MOD_HACK = 2;
    const ID_CLOUD_GAME = 3;

    protected $entityClass = GameTypeEntity::class;
    protected $table = Table::GameType;
    protected $table_alias = TableAlias::GameType;
    protected $pk = DBField::GAME_TYPE_ID;

    const GNS_KEY_PREFIX = GNS_ROOT . '.game-types';

    public static $fields = [
        DBField::GAME_TYPE_ID,
        DBField::DISPLAY_NAME,
        DBField::IS_ACTIVE,
        DBField::CREATED_BY,
        DBField::MODIFIED_BY,
        DBField::DELETED_BY
    ];

    /** @var GameTypeEntity[] */
    protected $gameTypes = [];

    /**
     * @return string
     */
    public function generateteAllCacheKey()
    {
        return self::GNS_KEY_PREFIX.'.all';
    }

    /**
     * @param Request $request
     * @return GameTypeEntity[]
     */
    public function getAllGameTypes(Request $request)
    {
        if (!$this->gameTypes) {
            $gameTypes = $this->query($request->db)
                ->cache($this->generateteAllCacheKey(), ONE_WEEK)
                ->get_entities($request);

            $this->gameTypes = array_index($gameTypes, $this->getPkField());
        }

        return $this->gameTypes;
    }

    /**
     * @param Request $request
     * @return GameTypeEntity[]
     */
    public function getAllActiveGameTypes(Request $request)
    {
        if (!$this->gameTypes)
            $this->getAllGameTypes($request);

        $activeGameTypes = [];

        foreach ($this->getAllGameTypes($request) as $gameType) {
            if ($gameType->is_active())
                $activeGameTypes[$gameType->getPk()] = $gameType;
        }

        return $activeGameTypes;
    }

    /**
     * @param Request $request
     * @param $gameTypeId
     * @return GameTypeEntity
     */
    public function getGameTypeById(Request $request, $gameTypeId)
    {
        return $this->getAllGameTypes($request)[$gameTypeId];
    }
}


class GamesEnginesManager extends BaseEntityManager
{
    const ID_UNITY = 1;
    const ID_UNREAL = 2;
    const ID_ESC_REACT = 3;

    protected $entityClass = GameEngineEntity::class;
    protected $table = Table::GameEngine;
    protected $table_alias = TableAlias::GameEngine;
    protected $pk = DBField::GAME_ENGINE_ID;

    const GNS_KEY_PREFIX = GNS_ROOT . '.game-engines';

    public static $fields = [
        DBField::GAME_ENGINE_ID,
        DBField::DISPLAY_NAME,
        DBField::IS_ACTIVE,
        DBField::CREATED_BY,
        DBField::MODIFIED_BY,
        DBField::DELETED_BY
    ];

    /**
     * @return string
     */
    public function generateCacheKey()
    {
        return self::GNS_KEY_PREFIX.'.all';
    }

    /**
     * @param Request $request
     * @return GameEngineEntity[]
     */
    public function getAllGameEngines(Request $request)
    {
        $gameEngines = $this->query($request->db)
            ->cache($this->generateCacheKey(), ONE_WEEK)
            ->get_entities($request);

        /** @var GameEngineEntity[] $gameEngines */
        $gameEngines = array_index($gameEngines, $this->getPkField());

        return $gameEngines;
    }

    /**
     * @param Request $request
     * @return GameEngineEntity[]
     */
    public function getAllActiveGameEngines(Request $request)
    {
        $activeGameEngines = [];

        foreach ($this->getAllGameEngines($request) as $key => $gameEngine) {
            if ($gameEngine->is_active())
                $activeGameEngines[$key] = $gameEngine;
        }

        return $activeGameEngines;
    }

    /**
     * @param Request $request
     * @param $gameEngineId
     * @return GameEngineEntity
     */
    public function getGameEngineById(Request $request, $gameEngineId)
    {
        return $this->getAllGameEngines($request)[$gameEngineId];
    }
}


class GamesCategoriesManager extends BaseEntityManager
{
    protected $entityClass = GameCategoryEntity::class;
    protected $table = Table::GameCategory;
    protected $table_alias = TableAlias::GameCategory;
    protected $pk = DBField::GAME_CATEGORY_ID;

    const GNS_KEY_PREFIX = GNS_ROOT.'.game-categories';

    const ID_ACTION = 1;
    const ID_ADVENTURE = 2;
    const ID_ROLE_PLAYING = 3;
    const ID_SIMULATION = 4;
    const ID_STRATEGY = 5;
    const ID_SPORTS = 6;
    const ID_PUZZLE = 7;
    const ID_CARD = 8;
    const ID_MASSIVE_CROWD = 9;
    const ID_TRIVIA = 10;

    public static $fields = [
        DBField::GAME_CATEGORY_ID,
        DBField::SLUG,
        DBField::DISPLAY_NAME,
        DBField::IS_ACTIVE,
        DBField::CREATED_BY,
        DBField::MODIFIED_BY,
        DBField::DELETED_BY
    ];

    /**
     * @return string
     */
    public function generateCacheKey()
    {
        return self::GNS_KEY_PREFIX.'.all';
    }

    /**
     * @param Request $request
     * @return GameCategoryEntity[]
     */
    public function getAllGameCategories(Request $request)
    {
        $gameCategories = $this->query($request->db)
            ->cache($this->generateCacheKey(), ONE_DAY)
            ->get_entities($request);

        return array_index($gameCategories, $this->getPkField());
    }

    /**
     * @param Request $request
     * @return GameCategoryEntity[]
     */
    public function getAllActiveGameCategories(Request $request)
    {
        $gameCategories = $this->getAllGameCategories($request);

        foreach ($gameCategories as $key => $gameCategory) {
            if (!$gameCategory->is_active())
                unset($gameCategories[$key]);
        }

        return $gameCategories;
    }

    /**
     * @param Request $request
     * @param $gameCategoryId
     * @return GameCategoryEntity
     */
    public function getGameCategoryById(Request $request, $gameCategoryId)
    {
        return $this->getAllGameCategories($request)[$gameCategoryId];
    }
}

class PlatformsManager extends BaseEntityManager
{
    const ID_WINDOWS = 1;
    const ID_MAC = 2;
    const ID_LINUX = 3;

    const SLUG_WINDOWS = 'win';
    const SLUG_MACOS = 'mac';

    protected $entityClass = PlatformEntity::class;
    protected $table = Table::Platform;
    protected $table_alias = TableAlias::Platform;
    protected $pk = DBField::PLATFORM_ID;

    public static $fields = [
        DBField::PLATFORM_ID,
        DBField::SLUG,
        DBField::DISPLAY_NAME,
        DBField::DISPLAY_ORDER,
        DBField::IS_ACTIVE,
        DBField::CREATED_BY,
        DBField::MODIFIED_BY,
        DBField::DELETED_BY
    ];

    const GNS_KEY_PREFIX = GNS_ROOT.'.platforms';

    /**
     * @param PlatformEntity $data
     * @param Request $request
     */
    public function processVFields(DBManagerEntity $data, Request $request)
    {
        $image_url = "{$request->getWwwUrl()}{$request->settings()->getImagesUrl()}entities/platform/{$data->getSlug()}.jpg";

        $data->updateField(VField::IMAGE_URL, $image_url);
    }

    /**
     * @return string
     */
    public function generateCacheKey()
    {
        return self::GNS_KEY_PREFIX.'.all';
    }

    /**
     * @param Request $request
     * @return PlatformEntity[]
     */
    public function getAllPlatforms(Request $request)
    {
        /** @var PlatformEntity[] $platforms */
        $platforms = $this->query($request->db)
            ->cache($this->generateCacheKey(), ONE_DAY)
            ->get_entities($request);

        if ($platforms)
            $platforms = array_index($platforms, $this->getPkField());

        return $platforms;
    }

    /**
     * @param Request $request
     * @return PlatformEntity[]
     */
    public function getAllActivePlatforms(Request $request)
    {
        /** @var PlatformEntity[] $platforms */
        $platforms = [];

        foreach ($this->getAllPlatforms($request) as $platform) {
            if ($platform->is_active())
                $platforms[] = $platform;
        }

        return $platforms;
    }

    /**
     * @param Request $request
     * @return PlatformEntity[]
     */
    public function getAllActivePlatformSlugOptions(Request $request)
    {
        /** @var PlatformEntity[] $platforms */
        $platforms = [];

        foreach ($this->getAllPlatforms($request) as $platform) {
            if ($platform->is_active()) {

                $platforms[] = [
                    DBField::ID => $platform->getSlug(),
                    DBField::DISPLAY_NAME => $platform->getDisplayName(),
                ];


            }
        }

        return $platforms;
    }

    /**
     * @param Request $request
     * @param $platformId
     * @return PlatformEntity
     */
    public function getPlatformById(Request $request, $platformId)
    {
        return $this->getAllPlatforms($request)[$platformId];
    }

    /**
     * @param Request $request
     * @param $platformSlug
     * @return array|PlatformEntity
     */
    public function getPlatformBySlug(Request $request, $platformSlug)
    {
        $platforms = $this->getAllPlatforms($request);

        foreach ($platforms as $platform) {
            if ($platform->getSlug() == $platformSlug)
                return $platform;
        }
        return [];
    }
}


class PlatformsVersionsManager extends BaseEntityManager
{
    protected $entityClass = PlatformVersionEntity::class;
    protected $table = Table::PlatformVersion;
    protected $table_alias = TableAlias::PlatformVersion;
    protected $pk = DBField::PLATFORM_VERSION_ID;

    public static $fields = [
        DBField::PLATFORM_VERSION_ID,
        DBField::PLATFORM_ID,
        DBField::DISPLAY_NAME,
        DBField::DISPLAY_ORDER,
        DBField::IS_ACTIVE,
        DBField::CREATED_BY,
        DBField::MODIFIED_BY,
        DBField::DELETED_BY
    ];
}


class GamesXPlatformsManager extends BaseEntityManager
{
    protected $entityClass = GameXPlatformEntity::class;
    protected $table = Table::GameXPlatform;
    protected $table_alias = TableAlias::GameXPlatform;
    protected $pk = DBField::GAME_X_PLATFORM_ID;

    protected $foreign_managers = [
        GamesManager::class => DBField::GAME_ID,
        PlatformsManager::class => DBField::PLATFORM_ID,
        PlatformsVersionsManager::class => DBField::PLATFORM_VERSION_ID,
        GamesBuildsManager::class => DBField::LATEST_GAME_BUILD_ID
    ];

    public static $fields = [
        DBField::GAME_X_PLATFORM_ID,
        DBField::GAME_ID,
        DBField::PLATFORM_ID,
        DBField::PLATFORM_VERSION_ID,
        DBField::LATEST_GAME_BUILD_ID,
        DBField::IS_ACTIVE,
        DBField::CREATED_BY,
        DBField::MODIFIED_BY,
        DBField::DELETED_BY
    ];
}


class GameLicensesManager extends BaseEntityManager
{
    protected $entityClass = GameLicenseEntity::class;
    protected $table = Table::GameLicense;
    protected $table_alias = TableAlias::GameLicense;
    protected $pk = DBField::GAME_LICENSE_ID;

    protected $foreign_managers = [
        UsersManager::class => DBField::USER_ID,
        GamesManager::class => DBField::GAME_ID
    ];

    public static $fields = [
        DBField::GAME_LICENSE_ID,
        DBField::GAME_ID,
        DBField::USER_ID,
        DBField::UPDATE_CHANNEL,
        DBField::START_TIME,
        DBField::END_TIME,
        DBField::CREATE_TIME,
        DBField::IS_ACTIVE,
        DBField::CREATED_BY,
        DBField::MODIFIED_BY,
        DBField::DELETED_BY
    ];

    /**
     * @param GameLicenseEntity $data
     * @param Request $request
     */
    public function processVFields(DBManagerEntity $data, Request $request)
    {

    }

    /**
     * @param Request $request
     * @param GameLicenseEntity[] $gameUsers
     * @return UserEntity[]
     */
    protected function postProcessAsUsersGames(Request $request, $gameUsers)
    {
        $usersManager = $request->managers->users();

        if ($gameUsers instanceof GameLicenseEntity)
            $gameUsers = [$gameUsers];

        $users = [];

        if ($gameUsers) {

            $userIds = unique_array_extract(DBField::USER_ID, $gameUsers);

            $users = $usersManager->getUsersByIds($request, $userIds);
            /** @var UserEntity[] $users */
            $users = array_index($users, $usersManager->getPkField());

            $usersGameInstances = [];

            foreach ($gameUsers as $gameUser) {
                if (!array_key_exists($gameUser->getUserId(), $usersGameInstances))
                    $usersGameInstances[$gameUser->getUserId()] = [];

                $usersGameInstances[$gameUser->getUserId()][$gameUser->getPk()] = $gameUser;
            }

            foreach ($usersGameInstances as $userId => $userGameInstances) {
                $users[$userId]->updateField(VField::GAME_LICENSES, array_reverse($userGameInstances));
            }


        }

        return $users;
    }

    /**
     * @param Request $request
     * @param $gameId
     * @param $userId
     * @param $startTime
     * @param null $endTime
     * @return GameLicenseEntity
     */
    public function createNewGameUserLicense(Request $request, $gameId, $userId, $updateChannel, $startTime, $endTime = null)
    {
        $data = [
            DBField::GAME_ID => $gameId,
            DBField::USER_ID => $userId,
            DBField::UPDATE_CHANNEL => $updateChannel,
            DBField::START_TIME => $startTime,
            DBField::END_TIME => $endTime,
        ];

        /** @var GameLicenseEntity $gameUser */
        $gameUser = $this->query($request->db)->createNewEntity($request, $data);

        return $gameUser;
    }

    /**
     * @param Request $request
     * @param $GameLicenseId
     * @param null $gameId
     * @return array|GameLicenseEntity
     */
    public function getUserGameLicenseById(Request $request, $GameLicenseId, $gameId = null)
    {
        $queryBuilder = $this->query($request->db)
            ->filter($this->filters->byPk($GameLicenseId))
            ->filter($this->filters->isActive());

        if ($gameId)
            $queryBuilder->filter($this->filters->byGameId($gameId));

        return $queryBuilder->get_entity($request);
    }

    /**
     * @param Request $request
     * @param $gameId
     * @return UserEntity[]
     */
    public function getUsersGameLicensesByGameId(Request $request, $gameId, $updateChannel)
    {
        $gameUsers = $this->query($request->db)
            ->filter($this->filters->byGameId($gameId))
            ->filter($this->filters->byUpdateChannel($updateChannel))
            ->filter($this->filters->isActive())
            ->get_entities($request);

        /** @var UserEntity[] $users */
        $users = $this->postProcessAsUsersGames($request, $gameUsers);

        return $users;
    }

    /**
     * @param Request $request
     * @param $gameId
     * @param $userId
     * @return GameLicenseEntity[]
     */
    public function getAllActiveGameUserLicensesByUserId(Request $request, $gameId, $userId, $updateChannel = null)
    {
        $activeFilter = $this->filters->And_(
            $this->filters->isActive(),
            $this->filters->Or_(
                $this->filters->IsNull(DBField::END_TIME),
                $this->filters->Lte(DBField::END_TIME, $request->getCurrentSqlTime())
            )
        );

        return $this->query($request->db)
            ->filter($this->filters->byGameId($gameId))
            ->filter($this->filters->byUserId($userId))
            ->filter($this->filters->byUpdateChannel($updateChannel))
            ->filter($activeFilter)
            ->get_entities($request);
    }

    /**
     * @param Request $request
     * @param $userId
     * @return GameLicenseEntity[]
     */
    public function getAllUserGameLicensesByUserId(Request $request, $userId)
    {
        $gamesManager = $request->managers->games();

        $activeFilter = $this->filters->And_(
            $this->filters->isActive(),
            $this->filters->Or_(
                $this->filters->IsNull(DBField::END_TIME),
                $this->filters->Lte(DBField::END_TIME, $request->getCurrentSqlTime())
            )
        );

        /** @var GameLicenseEntity[] $gameLicenses */
        $gameLicenses = $this->query($request->db)
            ->filter($this->filters->byUserId($userId))
            //->filter($activeFilter)
            ->get_entities($request);


        if ($gameLicenses) {
            $gameIds = unique_array_extract(DBField::GAME_ID, $gameLicenses);
            $games = $gamesManager->getGamesByIds($request, $gameIds, true);
            /** @var GameEntity[] $games */
            $games = array_index($games, $gamesManager->getPkField());

            foreach ($gameLicenses as $gameLicense) {
                $gameLicense->updateField(VField::GAME, $games[$gameLicense->getGameId()]);
                $gameLicense->updateField(VField::UPDATE_CHANNEL_NAME, $gamesManager->getUpdateChannelDisplayName($gameLicense->getUpdateChannel()));
            }
        }

        return $gameLicenses;
    }
}

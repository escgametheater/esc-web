<?php
/**
 * Created by PhpStorm.
 * User: christophercarter
 * Date: 2/25/19
 * Time: 11:52 AM
 */


class GamesModsManager extends BaseEntityManager
{
    protected $entityClass = GameModEntity::class;
    protected $table = Table::GameMod;
    protected $table_alias = TableAlias::GameMod;
    protected $pk = DBField::GAME_MOD_ID;

    const GNS_KEY_PREFIX =  GNS_ROOT.'.game-mods';

    public static $fields = [
        DBField::GAME_MOD_ID,
        DBField::ORGANIZATION_ID,
        DBField::GAME_ID,
        DBField::DISPLAY_NAME,
        DBField::DESCRIPTION,
        DBField::CREATE_TIME,

        DBField::IS_ACTIVE,
        DBField::CREATED_BY,
        DBField::MODIFIED_BY,
        DBField::DELETED_BY
    ];

    protected $foreign_managers = [
        GamesManager::class => DBField::GAME_ID,
        OrganizationsManager::class => DBField::ORGANIZATION_ID
    ];

    /**
     * @param GameModEntity $data
     * @param Request $request
     * @return GameModEntity
     */
    public function processVFields(DBManagerEntity $data, Request $request)
    {
        if ($data->hasField(VField::GAME_SLUG)) {
            $editUrl = $this->getModEditUrl($request, $data->getOrganizationSlug(), $data->getGameSlug(), $data->getPk());
            $data->updateField(VField::EDIT_URL, $editUrl);

            $cloneUrl = $this->getModCloneUrl($request, $data->getOrganizationSlug(), $data->getGameSlug(), $data->getPk());
            $data->updateField(VField::CLONE_URL, $cloneUrl);

            $deleteUrl = $this->getModDeleteUrl($request, $data->getOrganizationSlug(), $data->getGameSlug(), $data->getPk());
            $data->updateField(VField::DELETE_URL, $deleteUrl);
        }

        if (!$data->hasField(VField::GAME_MOD_BUILDS))
            $data->updateField(VField::GAME_MOD_BUILDS, []);

        if (!$data->hasField(VField::GAME))
            $data->updateField(VField::GAME, []);

    }

    /**
     * @param Request $request
     * @param $gameSlug
     * @param $gameModId
     * @return string
     */
    public function getModEditUrl(Request $request, $organizationSlug, $gameSlug, $gameModId)
    {
        return $request->getDevelopUrl("/teams/{$organizationSlug}/manage-mods/{$gameSlug}/{$gameModId}");
    }

    /**
     * @param Request $request
     * @param $organizationSlug
     * @param $gameSlug
     * @param $gameModId
     * @return string
     */
    public function getModCloneUrl(Request $request, $organizationSlug, $gameSlug, $gameModId)
    {
        return $request->getDevelopUrl("/teams/{$organizationSlug}/manage-mods/{$gameSlug}/{$gameModId}/clone");
    }

    /**
     * @param Request $request
     * @param $organizationSlug
     * @param $gameSlug
     * @param $gameModId
     * @return string
     */
    public function getModDeleteUrl(Request $request, $organizationSlug, $gameSlug, $gameModId)
    {
        return $request->getDevelopUrl("/teams/{$organizationSlug}/manage-mods/{$gameSlug}/{$gameModId}/delete");
    }

    /**
     * @param Request $request
     * @param $userId
     * @param string $updateChannel
     * @return AndFilter
     */
    public function getJoinGameModsLicensesFilter(Request $request, $userId, $updateChannel = GamesManager::UPDATE_CHANNEL_LIVE)
    {
        $gamesModsLicensesManager = $request->managers->gamesModsLicenses();

        $expireDt = new DateTime();

        return $this->filters->And_(
            $gamesModsLicensesManager->filters->byGameModId($this->createPkField()),
            $gamesModsLicensesManager->filters->byUserId($userId),
            $gamesModsLicensesManager->filters->byUpdateChannel($updateChannel),
            $gamesModsLicensesManager->filters->isActive(),
            $this->filters->Or_(
                $gamesModsLicensesManager->filters->IsNull(DBField::END_TIME),
                $this->filters->And_(
                    $gamesModsLicensesManager->filters->IsNotNull(DBField::END_TIME),
                    $gamesModsLicensesManager->filters->Gt(DBField::END_TIME, $expireDt->format(SQL_DATETIME))
                )
            )
        );
    }

    /**
     * @param Request $request
     * @param $userId
     * @return OrFilter
     */
    public function getJoinGameModsLicensesWhereFilter(Request $request, $userId)
    {
        $gamesModsLicensesManager = $request->managers->gamesModsLicenses();

        return $this->filters->Or_(
            $gamesModsLicensesManager->filters->IsNotNull($gamesModsLicensesManager->createPkField())
        );
    }

    /**
     * @param $gameModId
     * @return string
     */
    public function generateEntityIdCacheKey($gameModId)
    {
        return self::GNS_KEY_PREFIX.".{$gameModId}";
    }

    /**
     * @param Request $request
     * @param $organizationId
     * @param $gameId
     * @param $displayName
     * @param null $description
     * @return GameModEntity
     */
    public function createNewGameMod(Request $request, $organizationId, $organizationSlug, $gameId, $gameSlug, $displayName, $description = null)
    {
        $data = [
            DBField::ORGANIZATION_ID => $organizationId,
            DBField::GAME_ID => $gameId,
            DBField::DISPLAY_NAME => $displayName,
            DBField::DESCRIPTION => $description,
            DBField::CREATE_TIME => $request->getCurrentSqlTime(),
            DBField::IS_ACTIVE => 1,
            DBField::CREATED_BY => $request->requestId
        ];

        /** @var GameModEntity $gameMod */
        $gameMod = $this->query($request->db)->createNewEntity($request, $data);

        $virtualFields = [
            VField::GAME_SLUG => $gameSlug,
            VField::ORGANIZATION_SLUG => $organizationSlug,
            VField::EDIT_URL => $this->getModEditUrl($request, $organizationSlug, $gameSlug, $gameMod->getPk()),
        ];

        $gameMod->assign($virtualFields);

        return $gameMod;
    }

    /**
     * @param Request $request
     * @param bool $activeGamesOnly
     * @return SQLQuery
     */
    public function queryJoinGamesOrganizations(Request $request, $activeGamesOnly = false)
    {
        $gamesManager = $request->managers->games();
        $organizationsManager = $request->managers->organizations();

        $fields = array_merge($this->createDBFields(), [
            $gamesManager->aliasField(DBField::SLUG, VField::GAME_SLUG),
            $organizationsManager->aliasField(DBField::SLUG, VField::ORGANIZATION_SLUG)
        ]);

        $queryBuilder = $this->query($request->db)
            ->fields($fields)
            ->inner_join($gamesManager)
            ->inner_join($organizationsManager);

        if ($activeGamesOnly)
            $queryBuilder->filter($gamesManager->filters->isActive());

        return $queryBuilder;
    }

    /**
     * @param Request $request
     * @param GameModEntity[]|GameModEntity $gameMods
     */
    public function postProcessGameMods(Request $request, $gameMods, $expand = true)
    {
        $gamesManager = $request->managers->games();
        $gamesModsBuildsManager = $request->managers->gamesModsBuilds();

        if ($gameMods) {
            if ($gameMods instanceof GameModEntity)
                $gameMods = [$gameMods];

            /** @var GameModEntity[] $gameMods */
            $gameMods = array_index($gameMods, $this->getPkField());
            $gameModIds = array_keys($gameMods);

            $gameModBuilds = $gamesModsBuildsManager->getActiveGameModBuildsByGameModIds($request, $gameModIds);
            foreach ($gameModBuilds as $gameModBuild)
                $gameMods[$gameModBuild->getGameModId()]->setGameModBuild($gameModBuild);

            $gameIds = unique_array_extract(DBField::GAME_ID, $gameMods);
            $games = $gamesManager->getGamesByIds($request, $gameIds, false);
            if ($games)
                $games = array_index($games, $gamesManager->getPkField());

            foreach ($gameMods as $gameMod) {
                $game = $games[$gameMod->getGameId()];
                $gameMod->setGame($game);
            }
        }
    }

    /**
     * @param Request $request
     * @param $gameMods
     * @param bool $expand
     */
    public function postProcessHostGameMods(Request $request, $gameMods, $userId, $expand = true)
    {
        $gamesModsBuildsManager = $request->managers->gamesModsBuilds();

        if ($gameMods) {
            if ($gameMods instanceof GameModEntity)
                $gameMods = [$gameMods];

            /** @var GameModEntity[] $gameMods */
            $gameMods = $this->index($gameMods);
            $gameModIds = array_keys($gameMods);

            $gameModBuilds = $gamesModsBuildsManager->getUserPlayableGameModBuildsByGameModIds($request, $gameModIds, $userId);
            foreach ($gameModBuilds as $gameModBuild) {
                $gameMods[$gameModBuild->getGameModId()]->setGameModBuild($gameModBuild);
            }
        }
    }

    /**
     * @param Request $request
     * @param $gameSlug
     * @param $gameModId
     * @return GameModEntity
     */
    public function getGameModByGameSlugAndPk(Request $request, $gameSlug, $gameModId)
    {
        $gamesManager = $request->managers->games();

        /** @var GameModEntity $gameMod */
        $gameMod = $this->queryJoinGamesOrganizations($request)
            ->filter($gamesManager->filters->bySlug($gameSlug))
            ->filter($this->filters->isActive())
            ->filter($this->filters->byPk($gameModId))
            ->get_entity($request);

        $this->postProcessGameMods($request, $gameMod);

        return $gameMod;
    }


    /**
     * @param Request $request
     * @param $gameSlug
     * @return GameModEntity[]
     */
    public function getGameModsByGameSlug(Request $request, $gameSlug)
    {
        $gamesManager = $request->managers->games();

        /** @var GameModEntity[] $gameMods */
        $queryBuilder = $this->queryJoinGamesOrganizations($request)
            ->filter($gamesManager->filters->bySlug($gameSlug))
            ->filter($this->filters->isActive());

        $gameMods = $queryBuilder->get_entities($request);

        $this->postProcessGameMods($request, $gameMods);

        return $gameMods;
    }

    /**
     * @param Request $request
     * @param $organizationIds
     * @return GameModEntity[]
     */
    public function getEditableGameModsByOrganizationIds(Request $request, $organizationIds, $gameSlug = null)
    {
        $gamesManager = $request->managers->games();

        /** @var GameModEntity[] $gameMods */
        $queryBuilder = $this->queryJoinGamesOrganizations($request)
            ->filter($this->filters->byOrganizationId($organizationIds))
            ->filter($this->filters->isActive())
            ->sort_asc($gamesManager->field(DBField::DISPLAY_NAME))
            ->sort_asc($this->field(DBField::DISPLAY_NAME));

        if ($gameSlug)
            $queryBuilder->filter($gamesManager->filters->bySlug($gameSlug));

        $gameMods = $queryBuilder->get_entities($request);

        $this->postProcessGameMods($request, $gameMods);

        return $gameMods;
    }


    /**
     * @param Request $request
     * @param $organizationIds
     * @param int $page
     * @param int $perPage
     * @return array|GameModEntity[]
     */
    public function getRecentEditableGameModsByOrganizationIds(Request $request, $organizationIds, $page = 1, $perPage = 5)
    {
        $gamesModsBuildsManager = $request->managers->gamesModsBuilds();
        $joinGamesModsBuildsFilter = $this->filters->And_(
            $gamesModsBuildsManager->filters->byGameModId($this->createPkField()),
            $gamesModsBuildsManager->filters->isActive()
        );

        /** @var GameModEntity[] $gameMods */
        $queryBuilder = $this->queryJoinGamesOrganizations($request)
            //->inner_join($gamesModsBuildsManager, $joinGamesModsBuildsFilter)
            ->filter($this->filters->byOrganizationId($organizationIds))
            ->filter($this->filters->isActive())
            ->sort_desc($this->field(DBField::CREATE_TIME))
            ->paging($page, $perPage);

        $gameMods = $queryBuilder->get_entities($request);

        $this->postProcessGameMods($request, $gameMods);

        return $gameMods;
    }

    /**
     * @param Request $request
     * @param $gameModIds
     * @return GameModEntity[]
     */
    public function getGameModsByIds(Request $request, $gameModIds, $expand = false)
    {
        /** @var GameModEntity[] $gameMods */
        $gameMods = $this->getEntitiesByPks($request, $gameModIds, DONT_CACHE, $this->queryJoinGamesOrganizations($request));

        if ($expand)
            $this->postProcessGameMods($request, $gameMods, false);

        return $gameMods;
    }

    /**
     * @param Request $request
     * @param $gameModId
     * @param bool $expand
     * @return GameModEntity
     */
    public function getGameModById(Request $request, $gameModId, $expand = true)
    {
        /** @var GameModEntity $gameMod */
        $gameMod = $this->queryJoinGamesOrganizations($request)
            ->filter($this->filters->byPk($gameModId))
            ->get_entity($request);

        $this->postProcessGameMods($request, $gameMod, $expand);

        return $gameMod;
    }

    /**
     * @param Request $request
     * @param $organizationId
     * @return GameModEntity[]
     */
    public function getGameModsByOrganizationAndLicenses(Request $request, $organizationId, $gameId)
    {
        $gameModActiveBuildsManager = $request->managers->gamesModsActiveBuilds();
        $joinGamesModsActiveBuildsFilter = $this->filters->And_(
            $gameModActiveBuildsManager->filters->byGameModId($this->createPkField()),
            $gameModActiveBuildsManager->filters->byUpdateChannel(GamesManager::UPDATE_CHANNEL_LIVE),
            $gameModActiveBuildsManager->filters->isActive()
        );

        $organizationsGamesModsLicensesManager = $request->managers->organizationsGamesModsLicenses();

        $joinOrganizationsGamesLicensesManager = $this->filters->And_(
            $organizationsGamesModsLicensesManager->filters->byOrganizationId($organizationId),
            $organizationsGamesModsLicensesManager->filters->isActive(),
            $organizationsGamesModsLicensesManager->filters->Lte(DBField::START_TIME, $request->getCurrentSqlTime()),
            $organizationsGamesModsLicensesManager->filters->Or_(
                $organizationsGamesModsLicensesManager->filters->IsNull(DBField::END_TIME),
                $organizationsGamesModsLicensesManager->filters->Gte(DBField::END_TIME, $request->getCurrentSqlTime())
            )
        );

        $whereFilter = $this->filters->Or_(
            $this->filters->And_(
                $organizationsGamesModsLicensesManager->filters->IsNotNull($organizationsGamesModsLicensesManager->createPkField())
            ),
            $this->filters->byOrganizationId($organizationId)
        );

        /** @var GameModEntity[] $gameMods */
        $gameMods = $this->queryJoinGamesOrganizations($request, true)
            ->left_join($organizationsGamesModsLicensesManager, $joinOrganizationsGamesLicensesManager)
            ->inner_join($gameModActiveBuildsManager, $joinGamesModsActiveBuildsFilter)
            ->filter($this->filters->isActive())
            ->filter($this->filters->byGameId($gameId))
            ->filter($whereFilter)
            ->get_entities($request);

        $this->postProcessGameMods($request, $gameMods);

        return $gameMods;
    }

    /**
     * @param Request $request
     * @param $userId
     * @return GameModEntity[]
     */
    public function getPlayableGameModsByUserIdAndGameIds(Request $request, $userId, $gameIds, $updateChannel = null)
    {
        $gamesModsActiveBuildsManager = $request->managers->gamesModsActiveBuilds();
        $gamesModsBuildsManager = $request->managers->gamesModsBuilds();
        $gamesModsLicenses = $request->managers->gamesModsLicenses();
        $organizationsManager = $request->managers->organizations();

        $joinGameModActiveBuildsFilter = $this->filters->And_(
            $gamesModsActiveBuildsManager->filters->byGameModId($this->createPkField()),
            $gamesModsActiveBuildsManager->filters->byUpdateChannel($updateChannel),
            $gamesModsActiveBuildsManager->filters->isActive()
        );

        $joinGameModsBuildsFilter = $this->filters->And_(
            $gamesModsBuildsManager->filters->byPk($gamesModsActiveBuildsManager->field(DBField::GAME_MOD_BUILD_ID)),
            $gamesModsBuildsManager->filters->isActive()
        );

        // Filter for joining OrganizationUsers
        $organizationsUsersManager = $request->managers->organizationsUsers();
        $joinOrganizationsUsersFilter = $this->filters->And_(
            $organizationsUsersManager->filters->byOrganizationId($this->field(DBField::ORGANIZATION_ID)),
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
            $this->filters->Or_(
                $this->filters->And_(
                    $organizationsBaseRightsManager->filters->byName(OrganizationsBaseRightsManager::RIGHT_ORG_MODS_BUILDS_DEV),
                    $gamesModsActiveBuildsManager->filters->byUpdateChannel(GamesManager::UPDATE_CHANNEL_DEV)
                ),
                $this->filters->And_(
                    $organizationsBaseRightsManager->filters->byName(OrganizationsBaseRightsManager::RIGHT_ORG_MODS_BUILDS_LIVE),
                    $gamesModsActiveBuildsManager->filters->byUpdateChannel(GamesManager::UPDATE_CHANNEL_LIVE)
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
            $gamesModsLicenses->filters->IsNotNull($gamesModsLicenses->createPkField()),
            // Or the user has permission to use a game build of the correct update channel
            $organizationsPermissionsManager->filters->IsNotNull($organizationsPermissionsManager->createPkField())
        );


        /** @var GameModEntity[] $gameMods */
        $gameMods = $this->queryJoinGamesOrganizations($request)
            ->inner_join($gamesModsActiveBuildsManager, $joinGameModActiveBuildsFilter)
            ->inner_join($gamesModsBuildsManager, $joinGameModsBuildsFilter)
            ->left_join($gamesModsLicenses, $this->getJoinGameModsLicensesFilter($request, $userId, $gamesModsActiveBuildsManager->field(DBField::UPDATE_CHANNEL)))
            ->left_join($organizationsUsersManager, $joinOrganizationsUsersFilter)
            ->left_join($organizationsRightsManager, $joinOrganizationsRightsFilter)
            ->left_join($organizationsBaseRightsManager, $joinOrganizationsBaseRightsFilter)
            ->left_join($organizationsPermissionsManager, $joinOrganizationsPermissionsFilter)
            ->filter($this->filters->byGameId($gameIds))
            ->filter($whereFilter)
            ->filter($gamesModsActiveBuildsManager->filters->isActive())
            ->filter($this->filters->isActive())
            ->group_by($this->createPkField())
            ->sort_asc($this->field(DBField::DISPLAY_NAME))
            ->get_entities($request);

        $this->postProcessHostGameMods($request, $gameMods, $userId);

        return $gameMods;
    }

    /**
     * @param Request $request
     * @return GameModEntity[]
     */
    public function searchAutoCompleteGamesMods(Request $request, $query, $maxModResults, $userId, $isModAdmin = false)
    {
        $organizationsManager = $request->managers->organizations();
        $gamesManager = $request->managers->games();
        $imagesManager = $request->managers->images();

        $modsQueryFilter = $this->filters->Or_(
            $organizationsManager->filters->Like(DBField::DISPLAY_NAME, $query),
            $gamesManager->filters->Like(DBField::DISPLAY_NAME, $query),
            $this->filters->Like(DBField::DISPLAY_NAME, $query)
        );

        $queryBuilder = $this->queryJoinGamesOrganizations($request)
            ->filter($modsQueryFilter)
            ->filter($this->filters->isActive())
            ->group_by($this->createPkField())
            ->limit($maxModResults);

        if (Validators::int($userId) && $userId && !$isModAdmin) {

            // Filter for joining OrganizationUsers
            $organizationsUsersManager = $request->managers->organizationsUsers();
            $joinOrganizationsUsersFilter = $this->filters->And_(
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
                $organizationsBaseRightsManager->filters->byName(OrganizationsBaseRightsManager::RIGHT_ORG_MODS_PROFILE)
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

        /** @var GameModEntity[] $mods */
        $mods = $queryBuilder->get_entities($request);

        if ($mods) {
            /** @var GameModEntity[] $mods */
            $mods = $this->index($mods);
            $gameIds = unique_array_extract(DBField::GAME_ID, $mods);
            $modGames = $gamesManager->getGamesByIds($request, $gameIds);
            /** @var GameEntity[] $modGames */
            $modGames = $gamesManager->index($modGames);
            $avatarImages = $imagesManager->getActiveGameAvatarImagesByGameIds($request, extract_pks($modGames));
            foreach ($avatarImages as $avatarImage) {
                $modGames[$avatarImage->getContextEntityId()]->setAvatarImageUrls($avatarImage);
            }

            foreach ($mods as $mod) {
                $game = $modGames[$mod->getGameId()];
                $mod->setGame($game);
            }
        }

        return $mods;
    }

}
class GamesModsBuildsManager extends BaseEntityManager
{
    protected $entityClass = GameModBuildEntity::class;
    protected $table = Table::GameModBuild;
    protected $table_alias = TableAlias::GameModBuild;
    protected $pk = DBField::GAME_MOD_BUILD_ID;

    public static $fields = [
        DBField::GAME_MOD_BUILD_ID,
        DBField::GAME_MOD_ID,
        DBField::UPDATE_CHANNEL,
        DBField::PUBLISHED_GAME_MOD_BUILD_ID,
        DBField::PUBLISHED_TIME,
        DBField::BUILD_VERSION,
        DBField::CREATE_TIME,
        DBField::CREATOR_ID,
        DBField::IS_ACTIVE,
        DBField::CREATED_BY,
        DBField::MODIFIED_BY,
        DBField::DELETED_BY
    ];

    protected $foreign_managers = [
        GamesModsManager::class => DBField::GAME_MOD_ID
    ];

    /**
     * @param GameModBuildEntity $data
     * @param Request $request
     * @return GameModBuildEntity
     */
    public function processVFields(DBManagerEntity $data, Request $request)
    {
        if (!$data->hasField(VField::USER))
            $data->updateField(VField::USER, []);

        if (!$data->hasField(VField::CUSTOM_GAME_ASSETS))
            $data->updateField(VField::CUSTOM_GAME_ASSETS, []);
    }

    /**
     * @param Request $request
     * @param $gameModId
     * @param $updateChannel
     * @param $buildVersion
     * @return GameModBuildEntity
     */
    public function createNewGameModBuild(Request $request, $gameModId, $updateChannel, $buildVersion)
    {
        $data = [
            DBField::GAME_MOD_ID => $gameModId,
            DBField::UPDATE_CHANNEL => $updateChannel,
            DBField::PUBLISHED_GAME_MOD_BUILD_ID => null,
            DBField::PUBLISHED_TIME => null,
            DBField::BUILD_VERSION => $buildVersion,
            DBField::CREATE_TIME => $request->getCurrentSqlTime(),
            DBField::CREATOR_ID => $request->user->id,
            DBField::IS_ACTIVE => 1,
            DBField::CREATED_BY => $request->requestId
        ];

        /** @var GameModBuildEntity $gameModBuild */
        $gameModBuild = $this->query($request->db)->createNewEntity($request, $data);

        return $gameModBuild;
    }

    /**
     * @param Request $request
     * @param GameModBuildEntity[]|GameModBuildEntity $gameModBuilds
     */
    public function postProcessGameModBuilds(Request $request, $gameModBuilds, $expand = false)
    {
        $gamesAssetsManager = $request->managers->gamesAssets();

        if ($gameModBuilds) {
            if ($gameModBuilds instanceof GameModBuildEntity)
                $gameModBuilds = [$gameModBuilds];

            /** @var GameModBuildEntity[] $gameModBuilds */
            $gameModBuilds = array_index($gameModBuilds, $this->getPkField());
            $gameModBuildIds = array_keys($gameModBuilds);

            $customGameAssets = $gamesAssetsManager->getGameModBuildAssetsByGameModBuildIds($request, $gameModBuildIds);
            foreach ($customGameAssets as $customGameAsset) {
                $gameModBuilds[$customGameAsset->getGameModBuildId()]->setCustomGameModBuildAsset($customGameAsset);
            }
        }
    }

    /**
     * @param Request $request
     * @param $gameModBuildId
     * @param null $gameModId
     * @return GameModBuildEntity
     */
    public function getGameModBuildById(Request $request, $gameModBuildId, $gameModId = null)
    {
        $queryBuilder = $this->query($request->db)
            ->filter($this->filters->byGameModBuildId($gameModBuildId));

        if ($gameModId)
            $queryBuilder->filter($this->filters->byGameModId($gameModId));

        /** @var GameModBuildEntity $gameModBuild */
        $gameModBuild = $queryBuilder->get_entity($request);

        $this->postProcessGameModBuilds($request, $gameModBuild);

        return $gameModBuild;
    }

    /**
     * @param Request $request
     * @param $gameModBuildIds
     * @return GameModBuildEntity[]
     */
    public function getGameModBuildsByIds(Request $request, $gameModBuildIds)
    {
        /** @var GameModBuildEntity[] $gameModBuilds */
        $gameModBuilds = $this->query($request->db)
            ->filter($this->filters->byGameModBuildId($gameModBuildIds))
            ->get_entities($request);

        $this->postProcessGameModBuilds($request, $gameModBuilds);

        return $gameModBuilds;
    }

    /**
     * @param Request $request
     * @param $gameModId
     * @param $updateChannel
     * @return array
     */
    public function getGameModBuildOptionsByGameModAndUpdateChannel(Request $request, $gameModId, $updateChannel)
    {
        $gameBuildOptions = $this->query($request->db)
            ->filter($this->filters->byGameModId($gameModId))
            ->filter($this->filters->byUpdateChannel($updateChannel))
            ->filter($this->filters->isActive())
            ->get_list($this->aliasField(DBField::GAME_MOD_BUILD_ID, DBField::ID), $this->aliasField(DBField::BUILD_VERSION, DBField::DISPLAY_NAME));


        if ($gameBuildOptions)
            $gameBuildOptions = array_index($gameBuildOptions, DBField::ID);

        return $gameBuildOptions;
    }

    /**
     * @param Request $request
     * @param $gameModId
     * @param bool $active
     * @param string $updateChannel
     * @param bool $expand
     * @param bool $reverse
     * @return GameModBuildEntity[]
     */
    public function getGameModBuildsByGameModId(Request $request, $gameModId, $active = true,
                                                $updateChannel = GamesManager::UPDATE_CHANNEL_LIVE, $expand = true, $reverse = false)
    {
        $queryBuilder = $this->query($request->db)
            ->filter($this->filters->byGameModId($gameModId))
            ->filter($this->filters->byUpdateChannel($updateChannel));

        if ($active)
            $queryBuilder->filter($this->filters->isActive());

        if ($reverse)
            $queryBuilder->sort_desc($this->getPkField());

        /** @var GameModBuildEntity[] $gameModBuilds */
        $gameModBuilds = $queryBuilder->get_entities($request);

        if ($expand)
            $this->postProcessGameModBuilds($request, $gameModBuilds, $expand);

        return $gameModBuilds;
    }

    /**
     * @param Request $request
     * @param $gameModIds
     * @param string $updateChannel
     * @param bool $expand
     * @return GameModBuildEntity[]
     */
    public function getActiveGameModBuildsByGameModIds(Request $request, $gameModIds, $updateChannel = null, $expand = true)
    {
        $gamesModsActiveBuildsManager = $request->managers->gamesModsActiveBuilds();

        $joinGamesModsActiveBuildsFilter = $this->filters->And_(
            $this->filters->byPk($gamesModsActiveBuildsManager->field(DBField::GAME_MOD_BUILD_ID)),
            $gamesModsActiveBuildsManager->filters->isActive()
        );

        /** @var GameModBuildEntity[] $gameModBuilds */
        $gameModBuilds = $this->query($request->db)
            ->inner_join($gamesModsActiveBuildsManager, $joinGamesModsActiveBuildsFilter)
            ->filter($this->filters->byGameModId($gameModIds))
            ->filter($this->filters->byUpdateChannel($updateChannel))
            ->filter($this->filters->isActive())
            ->get_entities($request);

        if ($expand)
            $this->postProcessGameModBuilds($request, $gameModBuilds, $expand);

        return $gameModBuilds;
    }

    /**
     * @param Request $request
     * @param $gameModIds
     * @param $updateChannel
     * @param bool $expand
     * @return GameModBuildEntity
     */
    public function getActiveGameModBuildByGameModIdAndUpdateChannel(Request $request, $gameModIds, $updateChannel, $expand = true)
    {
        $gamesModsActiveBuildsManager = $request->managers->gamesModsActiveBuilds();

        $joinGamesModsActiveBuildsFilter = $this->filters->And_(
            $this->filters->byPk($gamesModsActiveBuildsManager->field(DBField::GAME_MOD_BUILD_ID)),
            $gamesModsActiveBuildsManager->filters->isActive()
        );

        /** @var GameModBuildEntity $gameModBuilds */
        $gameModBuilds = $this->query($request->db)
            ->inner_join($gamesModsActiveBuildsManager, $joinGamesModsActiveBuildsFilter)
            ->filter($this->filters->byGameModId($gameModIds))
            ->filter($this->filters->byUpdateChannel($updateChannel))
            ->filter($this->filters->isActive())
            ->get_entity($request);

        if ($expand)
            $this->postProcessGameModBuilds($request, $gameModBuilds, $expand);

        return $gameModBuilds;
    }


    /**
     * @param Request $request
     * @param $gameModIds
     * @param $userId
     * @return GameModBuildEntity[]
     */
    public function getUserPlayableGameModBuildsByGameModIds(Request $request, $gameModIds, $userId)
    {
        $gamesModsActiveBuildsManager = $request->managers->gamesModsActiveBuilds();
        $gamesModsManager = $request->managers->gamesMods();
        $gamesModsLicenses = $request->managers->gamesModsLicenses();

        $joinGamesActiveBuildsFilter = $this->filters->And_(
            $gamesModsActiveBuildsManager->filters->byGameModBuildId($this->createPkField()),
            $gamesModsActiveBuildsManager->filters->byUpdateChannel($this->field(DBField::UPDATE_CHANNEL))
        );

        $joiGameLicensesFilter = $gamesModsManager->getJoinGameModsLicensesFilter($request, $userId, $this->field(DBField::UPDATE_CHANNEL));

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
                    $gamesModsActiveBuildsManager->filters->byUpdateChannel(GamesManager::UPDATE_CHANNEL_DEV)
                ),
                $this->filters->And_(
                    $organizationsBaseRightsManager->filters->byName(OrganizationsBaseRightsManager::RIGHT_ORG_MODS_BUILDS_LIVE),
                    $gamesModsActiveBuildsManager->filters->byUpdateChannel(GamesManager::UPDATE_CHANNEL_LIVE)
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
            $gamesModsLicenses->filters->IsNotNull($gamesModsLicenses->createPkField()),
            // Or the user has permission to use a game build of the correct update channel
            $organizationsPermissionsManager->filters->IsNotNull($organizationsPermissionsManager->createPkField())
        );

        /** @var GameModBuildEntity[] $gameBuilds */
        $gameBuilds = $this->query($request->db)
            ->inner_join($gamesModsActiveBuildsManager, $joinGamesActiveBuildsFilter)
            ->inner_join($gamesModsManager)
            ->left_join($gamesModsLicenses, $joiGameLicensesFilter)
            ->left_join($organizationsUsersManager, $joinOrganizationsUsersFilter)
            ->left_join($organizationsManager, $joinOrganizationsFilter)
            ->left_join($organizationsRightsManager, $joinOrganizationsRightsFilter)
            ->left_join($organizationsBaseRightsManager, $joinOrganizationsBaseRightsFilter)
            ->left_join($organizationsPermissionsManager, $joinOrganizationsPermissionsFilter)
            ->filter($whereFilter)
            ->filter($gamesModsManager->filters->isActive())
            ->filter($gamesModsActiveBuildsManager->filters->isActive())
            ->filter($this->filters->isActive())
            ->filter($this->filters->byGameModId($gameModIds))
            ->group_by($this->createPkField())
            ->get_entities($request);

        if ($gameBuilds)
            $this->postProcessGameModBuilds($request, $gameBuilds, true);

        return $gameBuilds;
    }

}




class GamesModsDataManager extends BaseEntityManager
{

    protected $entityClass = GameModDataEntity::class;
    protected $table = Table::GameModData;
    protected $table_alias = TableAlias::GameModData;
    protected $pk = DBField::GAME_MOD_DATA_ID;

    public static $fields = [
        DBField::GAME_MOD_DATA_ID,
        DBField::GAME_MOD_BUILD_ID,
        DBField::KEY,
        DBField::FIRST_ACTIVE_GAME_BUILD_ID,
        DBField::IS_ACTIVE,
        DBField::CREATED_BY,
        DBField::MODIFIED_BY,
        DBField::DELETED_BY
    ];

    protected $foreign_managers = [
        GamesModsBuildsManager::class => DBField::GAME_MOD_BUILD_ID
    ];

    /**
     * @param GameModDataEntity $data
     * @param Request $request
     * @return GameModDataEntity
     */
    public function processVFields(DBManagerEntity $data, Request $request)
    {
        if (!$data->hasField(VField::GAME_MOD_DATA_SHEETS))
            $data->updateField(VField::GAME_MOD_DATA_SHEETS, []);
    }

    /**
     * @param Request $request
     * @param $gameModBuildId
     * @param $key
     * @param null $firstActiveGameBuildId
     * @return GameModDataEntity
     */
    public function createNewGameModData(Request $request, $gameModBuildId, $key, $firstActiveGameBuildId = null)
    {
        $data = [
            DBField::GAME_MOD_BUILD_ID => $gameModBuildId,
            DBField::KEY => $key,
            DBField::FIRST_ACTIVE_GAME_BUILD_ID => $firstActiveGameBuildId,
            DBField::IS_ACTIVE => 1,
            DBField::CREATED_BY => $request->requestId,
        ];

        /** @var GameModDataEntity $gameData */
        $gameData = $this->query($request->db)->createNewEntity($request, $data);

        return $gameData;
    }

    /**
     * @param Request $request
     * @param GameModDataEntity|GameModDataEntity[] $gameModData
     */
    public function postProcessGameModData(Request $request, $gameModData)
    {
        $gameModDataSheetsManager = $request->managers->gamesModsDataSheets();

        if ($gameModData) {
            if ($gameModData instanceof GameModDataEntity)
                $gameModData = [$gameModData];

            /** @var GameModDataEntity[] $gameModData */
            $gameModData = array_index($gameModData, $this->getPkField());
            $gameModDataIds = array_keys($gameModData);

            $gameModDataSheets = $gameModDataSheetsManager->getGameModDataSheetsByDataIds($request, $gameModDataIds);
            foreach ($gameModDataSheets as $gameModDataSheet) {
                $gameModData[$gameModDataSheet->getGameModDataId()]->setGameModDataSheet($gameModDataSheet);
            }

        }
    }

    /**
     * @param Request $request
     * @param $gameModDataId
     * @param bool $expand
     * @return GameModDataEntity
     */
    public function getGameModDataById(Request $request, $gameModDataId, $gameModBuildId = null, $expand = true)
    {
        /** @var GameModDataEntity $gameModData */
        $gameModData = $this->query($request->db)
            ->filter($this->filters->byPk($gameModDataId))
            ->filter($this->filters->byGameModBuildId($gameModBuildId))
            ->get_entity($request);

        if ($expand)
            $this->postProcessGameModData($request, $gameModData);

        return $gameModData;
    }

    /**
     * @param Request $request
     * @param $gameModBuildId
     * @param bool $expand
     * @return GameModDataEntity[]
     */
    public function getGameModDataByGameModBuildId(Request $request, $gameModBuildId, $expand = true)
    {
        /** @var GameModDataEntity[] $gameModData */
        $gameModData = $this->query($request->db)
            ->filter($this->filters->byGameModBuildId($gameModBuildId))
            ->filter($this->filters->isActive())
            ->get_entities($request);

        if ($expand)
            $this->postProcessGameModData($request, $gameModData);

        return $gameModData;
    }

    /**
     * @param Request $request
     * @param $gameModBuildId
     * @param $key
     * @param bool $expand
     * @return GameModDataEntity
     */
    public function getGameModDataByGameModBuildIdAndKey(Request $request, $gameModBuildId, $key, $expand = true)
    {
        /** @var GameModDataEntity $gameModData */
        $gameModData = $this->query($request->db)
            ->filter($this->filters->byGameModBuildId($gameModBuildId))
            ->filter($this->filters->byKey($key))
            ->filter($this->filters->isActive())
            ->get_entity($request);

        if ($expand)
            $this->postProcessGameModData($request, $gameModData);

        return $gameModData;
    }

    /**
     * @param Request $request
     * @param $gameId
     * @param $gameModBuildId
     * @param $key
     * @param bool $expand
     * @return GameModDataEntity
     */
    public function getModdedGameData(Request $request, $gameId, $gameModBuildId, $key, $expand = true)
    {
        $gamesModsManager = $request->managers->gamesMods();
        $gamesModsBuildsManager = $request->managers->gamesModsBuilds();

        $joinGamesModBuildsFilter = $this->filters->And_(
            $gamesModsBuildsManager->filters->byPk($this->field(DBField::GAME_MOD_BUILD_ID)),
            $gamesModsBuildsManager->filters->isActive()
        );

        $joinGamesModsFilter = $this->filters->And_(
            $gamesModsManager->filters->byPk($gamesModsBuildsManager->field(DBField::GAME_MOD_ID)),
            $gamesModsManager->filters->isActive()
        );

        /** @var GameModDataEntity $gameModData */
        $gameModData = $this->query($request->db)
            ->inner_join($gamesModsBuildsManager, $joinGamesModBuildsFilter)
            ->inner_join($gamesModsManager, $joinGamesModsFilter)
            ->filter($gamesModsManager->filters->byGameId($gameId))
            ->filter($this->filters->byGameModBuildId($gameModBuildId))
            ->filter($this->filters->byKey($key))
            ->filter($this->filters->isActive())
            ->get_entity($request);

        if ($expand)
            $this->postProcessGameModData($request, $gameModData);

        return $gameModData;
    }

    /**
     * @param Request $request
     * @param $gameModBuildId
     * @param $key
     * @param \PhpOffice\PhpSpreadsheet\Spreadsheet $spreadSheet
     * @return GameModDataEntity
     */
    public function processSpreadsheet(Request $request, $gameModBuildId, $key, \PhpOffice\PhpSpreadsheet\Spreadsheet $spreadSheet)
    {
        $gamesModsDataSheetsManager = $request->managers->gamesModsDataSheets();
        $gamesModsDataSheetsColumnsManager = $request->managers->gamesModsDataSheetsColumns();
        $gamesModsDataSheetsRowsManager = $request->managers->gamesModsDataSheetsRows();

        // Try to get game data record from DB
        if ($gameModData = $this->getGameModDataByGameModBuildIdAndKey($request, $gameModBuildId, $key)) {
            // If the data exists, deactivate previous sheets.
            foreach ($gameModData->getGameModDataSheets() as $gameModDataSheet) {
                $gamesModsDataSheetsManager->deactivateEntity($request, $gameModDataSheet);
            }
        } else {
            // We need to create a new game data for this key/channel/build.
            $gameModData = $this->createNewGameModData($request, $gameModBuildId, $key);
        }

        // Process All Sheets in XLS
        foreach($spreadSheet->getSheetNames() as $sheetName) {

            /** @var array $sheetData */
            $sheetData = $spreadSheet->getSheetByName($sheetName)->toArray();

            $headerRow = array_shift($sheetData);

            $oldIndexFieldName = null;

            // Preserve can_mod setting from previous sheet of same name if it exists.
            if ($oldGameDataSheet = $gameModData->getGameModDataSheetByName($sheetName)) {
                $oldIndexFieldName = $oldGameDataSheet->getIndexColumn()->getName();
            }

            // Create New Sheet
            $gameModDataSheet = $gamesModsDataSheetsManager->createNewGameModDataSheet($request, $gameModData->getPk(), $sheetName);

            // Extract and create the sheet column names.
            $headerIsEmpty = true;

            foreach ($headerRow as $index => $header) {
                if ($header !== null)
                    $headerIsEmpty = false;
            }

            if (!$headerIsEmpty) {

                $displayOrder = 1;
                foreach ($headerRow as $index => $columnName) {
                    if ($columnName != null) {
                        $gameModDataSheetColumn = $gamesModsDataSheetsColumnsManager->createNewGameModDataSheetColumn(
                            $request,
                            $gameModDataSheet->getPk(),
                            $columnName,
                            $displayOrder
                        );
                        $gameModDataSheet->setGameModDataSheetColumn($gameModDataSheetColumn);

                        // If this is the first column, or the column that previously matches index field, set the index field.
                        if ($displayOrder == 1 || $oldIndexFieldName == $columnName) {
                            $gameModDataSheet->updateField(DBField::GAME_MOD_DATA_SHEET_COLUMN_ID, $gameModDataSheetColumn->getPk())->saveEntityToDb($request);
                        }

                        $displayOrder++;

                    } else {
                        continue;
                    }
                }

                $displayOrder = 1;
                foreach ($sheetData as $row) {

                    $isEmpty = true;

                    foreach ($headerRow as $index => $header) {
                        if ($row[$index] !== null)
                            $isEmpty = false;
                    }

                    if ($isEmpty) {
                        continue;
                    }

                    $dataRow = [];

                    foreach ($headerRow as $index => $header) {
                        $dataRow[$header] = $row[$index];
                    }

                    $gameModDataSheetRow = $gamesModsDataSheetsRowsManager->createNewGameModDataSheetRow(
                        $request,
                        $gameModDataSheet->getPk(),
                        $dataRow,
                        $displayOrder,
                        $dataRow[$gameModDataSheet->getIndexColumn()->getName()]
                    );

                    $gameModDataSheet->setGameModDataSheetRow($gameModDataSheetRow);

                    $displayOrder++;
                }
                $gameModData->setGameModDataSheet($gameModDataSheet);
            }
        }

        return $gameModData;
    }

    /**
     * @param Request $request
     * @param GameModBuildEntity $oldGameModBuild
     * @param GameModBuildEntity $newGameModBuild
     */
    public function cloneGameModBuildData(Request $request, GameModBuildEntity $oldGameModBuild, GameModBuildEntity $newGameModBuild)
    {
        $gamesModsDataSheetsManager = $request->managers->gamesModsDataSheets();
        $gamesModsDataSheetsColumnsManager = $request->managers->gamesModsDataSheetsColumns();
        $gamesModsDataSheetsRowsManager = $request->managers->gamesModsDataSheetsRows();

        $customGameModDatas = $this->getGameModDataByGameModBuildId($request, $oldGameModBuild->getPk());

        foreach ($customGameModDatas as $customGameModData) {
            $newGameModData = $this->createNewGameModData(
                $request,
                $newGameModBuild->getPk(),
                $customGameModData->getKey()
            );

            foreach ($customGameModData->getGameModDataSheets() as $gameModDataSheet) {
                $newGameModDataSheet = $gamesModsDataSheetsManager->createNewGameModDataSheet(
                    $request,
                    $newGameModData->getPk(),
                    $gameModDataSheet->getName()
                );

                foreach ($gameModDataSheet->getGameModDataSheetColumns() as $column) {
                    $newGameModDataSheetColumn = $gamesModsDataSheetsColumnsManager->createNewGameModDataSheetColumn(
                        $request,
                        $newGameModDataSheet->getPk(),
                        $column->getName(),
                        $column->getDisplayOrder()
                    );
                    $newGameModDataSheet->setGameModDataSheetColumn($newGameModDataSheetColumn);

                    if ($gameModDataSheet->getIndexColumn()->getName() == $newGameModDataSheetColumn->getName())
                        $newGameModDataSheet->updateField(DBField::GAME_MOD_DATA_SHEET_COLUMN_ID, $newGameModDataSheetColumn->getPk())->saveEntityToDb($request);
                }

                foreach ($gameModDataSheet->getGameModDataSheetRows() as $gameModDataSheetRow) {
                    $newGameModDataSheetRow = $gamesModsDataSheetsRowsManager->createNewGameModDataSheetRow(
                        $request,
                        $newGameModDataSheet->getPk(),
                        $gameModDataSheetRow->getProcessedValues(),
                        $gameModDataSheetRow->getDisplayOrder(),
                        $gameModDataSheetRow->getIndexKey()
                    );
                    $newGameModDataSheet->setGameModDataSheetRow($newGameModDataSheetRow);
                }
            }
        }
    }

}
class GamesModsDataSheetsManager extends BaseEntityManager
{
    protected $entityClass = GameModDataSheetEntity::class;
    protected $table = Table::GameModDataSheet;
    protected $table_alias = TableAlias::GameModDataSheet;
    protected $pk = DBField::GAME_MOD_DATA_SHEET_ID;

    public static $fields = [
        DBField::GAME_MOD_DATA_SHEET_ID,
        DBField::GAME_MOD_DATA_ID,
        DBField::GAME_MOD_DATA_SHEET_COLUMN_ID,
        DBField::NAME,
        DBField::IS_ACTIVE,
        DBField::CREATED_BY,
        DBField::MODIFIED_BY,
        DBField::DELETED_BY
    ];

    /**
     * @param GameModDataSheetEntity $data
     * @param Request $request
     * @return GameModDataSheetEntity
     */
    public function processVFields(DBManagerEntity $data, Request $request)
    {
        if (!$data->hasField(VField::GAME_MOD_DATA_SHEET_ROWS))
            $data->updateField(VField::GAME_MOD_DATA_SHEET_ROWS, []);
    }


    /**
     * @param Request $request
     * @param $gameModDataId
     * @param $name
     * @return GameModDataSheetEntity
     */
    public function createNewGameModDataSheet(Request $request, $gameModDataId, $name)
    {
        $data = [
            DBField::GAME_MOD_DATA_ID => $gameModDataId,
            DBField::NAME => $name,
            DBField::GAME_MOD_DATA_SHEET_COLUMN_ID => null,
            DBField::IS_ACTIVE => 1,
            DBField::CREATED_BY => $request->requestId
        ];

        /** @var GameModDataSheetEntity $gameModDataSheet */
        $gameModDataSheet = $this->query($request->db)->createNewEntity($request, $data);

        return $gameModDataSheet;
    }

    /**
     * @param Request $request
     * @param GameModDataSheetEntity|GameModDataSheetEntity[] $gameModDataSheets
     */
    public function postProcessGameModDataSheets(Request $request, $gameModDataSheets)
    {
        $gameModDataSheetsColumnsManager = $request->managers->gamesModsDataSheetsColumns();
        $gameModDataSheetsRowsManager = $request->managers->gamesModsDataSheetsRows();

        if ($gameModDataSheets) {
            if ($gameModDataSheets instanceof GameModDataSheetEntity)
                $gameModDataSheets = [$gameModDataSheets];

            /** @var GameModDataSheetEntity[] $gameModDataSheets */
            $gameModDataSheets = array_index($gameModDataSheets, $this->getPkField());

            $sheetIds = array_keys($gameModDataSheets);

            $gameModDataSheetColumns = $gameModDataSheetsColumnsManager->getGameModDataSheetColumnsByGameModDataSheetIds($request, $sheetIds);
            foreach ($gameModDataSheetColumns as $gameModDataSheetColumn) {
                $gameModDataSheets[$gameModDataSheetColumn->getGameModDataSheetId()]->setGameModDataSheetColumn($gameModDataSheetColumn);
            }

            $gameModDataSheetRows = $gameModDataSheetsRowsManager->getGameModDataSheetRowsBySheetIds($request, $sheetIds);
            foreach ($gameModDataSheetRows as $gameModDataSheetRow) {
                $gameModDataSheets[$gameModDataSheetRow->getGameModDataSheetId()]->setGameModDataSheetRow($gameModDataSheetRow);
            }
        }
    }

    /**
     * @param Request $request
     * @param $gameModDataIds
     * @param bool $expand
     * @return GameModDataSheetEntity[]
     */
    public function getGameModDataSheetsByDataIds(Request $request, $gameModDataIds, $expand = true)
    {
        /** @var GameModDataSheetEntity[] $gameModDataSheets */
        $gameModDataSheets = $this->query($request)
            ->filter($this->filters->byGameModDataId($gameModDataIds))
            ->filter($this->filters->isActive())
            ->get_entities($request);

        if ($expand)
            $this->postProcessGameModDataSheets($request, $gameModDataSheets);

        return $gameModDataSheets;
    }

    /**
     * @param Request $request
     * @param GameModDataSheetEntity $gameDataSheet
     */
    public function updateGameModDataSheetRowsIndexKeys(Request $request, GameModDataSheetEntity $gameDataSheet)
    {
        foreach ($gameDataSheet->getGameModDataSheetRows() as $gameDataSheetRow) {
            $indexKey = $gameDataSheetRow->getProcessedValueByKey($gameDataSheet->getIndexColumn()->getName());
            $gameDataSheetRow->updateField(DBField::INDEX_KEY, $indexKey);
            $gameDataSheetRow->saveEntityToDb($request);
        }
    }

    /**
     * @param Request $request
     * @param GameModDataSheetEntity $gameModDataSheet
     */
    public function flushSheetDb(Request $request, GameModDataSheetEntity $gameModDataSheet)
    {
        $gamesModsDataSheetsColumnsManager = $request->managers->gamesModsDataSheetsColumns();
        $gamesModsDataSheetsRowsManager = $request->managers->gamesModsDataSheetsRows();

        foreach ($gameModDataSheet->getGameModDataSheetColumns() as $gameModDataSheetColumn) {
            $gamesModsDataSheetsColumnsManager->deactivateEntity($request, $gameModDataSheetColumn);
        }

        foreach ($gameModDataSheet->getGameModDataSheetRows() as $gameModDataSheetRow) {
            $gamesModsDataSheetsRowsManager->deactivateEntity($request, $gameModDataSheetRow);
        }
    }

}

class GamesModsDataSheetsColumnsManager extends BaseEntityManager
{
    protected $entityClass = GameModDataSheetColumnEntity::class;
    protected $table = Table::GameModDataSheetColumn;
    protected $table_alias = TableAlias::GameModDataSheetColumn;
    protected $pk = DBField::GAME_MOD_DATA_SHEET_COLUMN_ID;

    public static $fields = [
        DBField::GAME_MOD_DATA_SHEET_COLUMN_ID,
        DBField::GAME_MOD_DATA_SHEET_ID,
        DBField::NAME,
        DBField::DISPLAY_ORDER,
        DBField::IS_ACTIVE,
        DBField::CREATED_BY,
        DBField::MODIFIED_BY,
        DBField::DELETED_BY
    ];

    /**
     * @param GameModDataSheetColumnEntity $data
     * @param Request $request
     */
    public function processVFields(DBManagerEntity $data, Request $request)
    {

    }

    /**
     * @param Request $request
     * @param $gameModDataSheetId
     * @param $name
     * @param int $displayOrder
     * @return GameModDataSheetColumnEntity
     */
    public function createNewGameModDataSheetColumn(Request $request, $gameModDataSheetId, $name, $displayOrder = 0)
    {
        $data = [
            DBField::GAME_MOD_DATA_SHEET_ID => $gameModDataSheetId,
            DBField::NAME => $name,
            DBField::DISPLAY_ORDER => $displayOrder,
            DBField::IS_ACTIVE => 1,
            DBField::CREATED_BY => $request->requestId
        ];

        /** @var GameModDataSheetColumnEntity $gameModDataSheetColumn */
        $gameModDataSheetColumn = $this->query($request->db)->createNewEntity($request, $data);

        return $gameModDataSheetColumn;
    }

    /**
     * @param Request $request
     * @param $gameModDataSheetIds
     * @return GameModDataSheetColumnEntity[]
     */
    public function getGameModDataSheetColumnsByGameModDataSheetIds(Request $request, $gameModDataSheetIds)
    {
        /** @var GameModDataSheetColumnEntity[] $gameModDataSheetColumns */
        $gameModDataSheetColumns = $this->query($request->db)
            ->filter($this->filters->byGameModDataSheetId($gameModDataSheetIds))
            ->filter($this->filters->isActive())
            ->sort_asc(DBField::DISPLAY_ORDER)
            ->get_entities($request);

        return $gameModDataSheetColumns;
    }
}

class GamesModsDataSheetsRowsManager extends BaseEntityManager
{
    protected $entityClass = GameModDataSheetRowEntity::class;
    protected $table = Table::GameModDataSheetRow;
    protected $table_alias = TableAlias::GameModDataSheetRow;
    protected $pk = DBField::GAME_MOD_DATA_SHEET_ROW_ID;

    public static $fields = [
        DBField::GAME_MOD_DATA_SHEET_ROW_ID,
        DBField::GAME_MOD_DATA_SHEET_ID,
        DBField::DISPLAY_ORDER,
        DBField::VALUE,
        DBField::INDEX_KEY,
        DBField::IS_ACTIVE,
        DBField::CREATED_BY,
        DBField::MODIFIED_BY,
        DBField::DELETED_BY
    ];

    /**
     * @param GameModDataSheetRowEntity $data
     * @param Request $request
     * @return GameModDataSheetRowEntity
     */
    public function processVFields(DBManagerEntity $data, Request $request)
    {
        $data->updateField(VField::PROCESSED_VALUES, unserialize($data->getValue()));
    }

    /**
     * @param Request $request
     * @param $gameModId
     * @param $gameSlug
     * @param $updateChannel
     * @param $key
     * @param $sheetName
     * @return SQLQuery
     */
    protected function queryJoinGamesActiveModsDataSheets(Request $request, $gameModId, $gameSlug, $updateChannel, $key, $sheetName)
    {

        $gamesModsManager = $request->managers->gamesMods();
        $joinGameModsFilter = $this->filters->And_(
            $gamesModsManager->filters->byPk($gameModId),
            $gamesModsManager->filters->isActive()
        );

        $gamesManager = $request->managers->games();
        $joinGamesFilter = $this->filters->And_(
            $gamesManager->filters->bySlug($gameSlug),
            $gamesManager->filters->byPk($gamesModsManager->field(DBField::GAME_ID)),
            $gamesManager->filters->isActive()
        );

        $gamesModsActiveBuildsManager = $request->managers->gamesModsActiveBuilds();
        $joinGamesModsActiveBuildsFilter = $this->filters->And_(
            $gamesModsActiveBuildsManager->filters->byGameModId($gamesModsManager->createPkField()),
            $gamesModsActiveBuildsManager->filters->byUpdateChannel($updateChannel),
            $gamesModsActiveBuildsManager->filters->isActive()
        );

        $gamesModsDataManager = $request->managers->gamesModsData();
        $joinGameModDataFilter = $this->filters->And_(
            $gamesModsDataManager->filters->byGameModBuildId($gamesModsActiveBuildsManager->field(DBField::GAME_MOD_BUILD_ID)),
            $gamesModsDataManager->filters->byKey($key),
            $gamesModsDataManager->filters->isActive()
        );

        $gamesModsDataSheetsManager = $request->managers->gamesModsDataSheets();
        $joinGameModDataSheetsFilter = $this->filters->And_(
            $gamesModsDataSheetsManager->filters->byGameModDataId($gamesModsDataManager->createPkField()),
            $gamesModsDataSheetsManager->filters->byName($sheetName),
            $gamesModsDataSheetsManager->filters->isActive()
        );

        return $this->query($request->db)
            ->inner_join($gamesModsManager, $joinGameModsFilter)
            ->inner_join($gamesManager, $joinGamesFilter)
            ->inner_join($gamesModsActiveBuildsManager, $joinGamesModsActiveBuildsFilter)
            ->inner_join($gamesModsDataManager, $joinGameModDataFilter)
            ->inner_join($gamesModsDataSheetsManager, $joinGameModDataSheetsFilter);
    }

    /**
     * @param Request $request
     * @param $gameModDataSheetId
     * @param $value
     * @param int $displayOrder
     * @param null $indexKey
     * @return GameModDataSheetRowEntity
     */
    public function createNewGameModDataSheetRow(Request $request, $gameModDataSheetId, $value, $displayOrder = 0, $indexKey = null)
    {
        $data = [
            DBField::GAME_MOD_DATA_SHEET_ID => $gameModDataSheetId,
            DBField::DISPLAY_ORDER => $displayOrder,
            DBField::VALUE => serialize($value),
            DBField::INDEX_KEY => $indexKey,
            DBField::IS_ACTIVE => 1,
            DBField::CREATED_BY => $request->requestId
        ];

        /** @var GameModDataSheetRowEntity $gameDataSheetRow */
        $gameDataSheetRow = $this->query($request->db)->createNewEntity($request, $data);

        return $gameDataSheetRow;
    }

    /**
     * @param Request $request
     * @param GameModDataSheetRowEntity|GameModDataSheetRowEntity[] $gameModDataSheetRows
     */
    public function postProcessGameModDataSheetRows(Request $request, $gameModDataSheetRows)
    {
        if ($gameModDataSheetRows) {
            if ($gameModDataSheetRows instanceof GameModDataSheetRowEntity)
                $gameModDataSheetRows = [$gameModDataSheetRows];

            /** @var GameModDataSheetRowEntity[] $gameModDataSheetRows */
            $gameModDataSheetRows = array_index($gameModDataSheetRows, $this->getPkField());
        }
    }

    /**
     * @param Request $request
     * @param $sheetIds
     * @return GameModDataSheetRowEntity[]
     */
    public function getGameModDataSheetRowsBySheetIds(Request $request, $sheetIds)
    {
        return $this->query($request->db)
            ->filter($this->filters->byGameModDataSheetId($sheetIds))
            ->filter($this->filters->isActive())
            ->sort_asc(DBField::DISPLAY_ORDER)
            ->get_entities($request);
    }

    /**
     * @param Request $request
     * @param $gameSlug
     * @param $gameModId
     * @param $updateChannel
     * @param $key
     * @param $sheetName
     * @param null $offset
     * @param int $count
     * @return GameModDataSheetRowEntity[]
     */
    public function getGameModDataSheetRowsByContext(Request $request, $gameSlug, $gameModId, $updateChannel, $key, $sheetName, $offset = null, $count = 40)
    {
        $gamesModsDataSheetsManager = $request->managers->gamesModsDataSheets();
        $queryBuilder = $this->queryJoinGamesActiveModsDataSheets($request, $gameModId, $gameSlug, $updateChannel, $key, $sheetName)
            ->filter($this->filters->byGameModDataSheetId($gamesModsDataSheetsManager->createPkField()))
            ->filter($this->filters->isActive())
            ->sort_asc($this->field(DBField::DISPLAY_ORDER))
            ->limit($count);

        if (!is_null($offset) && $offset > 0) {
            $queryBuilder->offset($offset)->limit($count);
        }

        /** @var GameModDataSheetRowEntity[] $gameDataSheetRows */
        $gameDataSheetRows = $queryBuilder->get_entities($request);

        return $gameDataSheetRows;
    }

    /**
     * @param Request $request
     * @param $gameSlug
     * @param $gameModId
     * @param $updateChannel
     * @param $key
     * @param $sheetName
     * @return int
     */
    public function getGameModDataSheetRowCountByContext(Request $request, $gameSlug, $gameModId, $updateChannel, $key, $sheetName)
    {
        $gamesModsDataSheetsManager = $request->managers->gamesModsDataSheets();

        try {
            $count = $this->queryJoinGamesActiveModsDataSheets($request, $gameModId, $gameSlug, $updateChannel, $key, $sheetName)
                ->filter($this->filters->byGameModDataSheetId($gamesModsDataSheetsManager->createPkField()))
                ->filter($this->filters->isActive())
                ->count();
        } catch (ObjectNotFound $e) {
            $count = 0;
        }

        return $count;
    }
}

class GamesModsActiveBuildsManager extends BaseEntityManager
{
    protected $entityClass = GameModActiveBuildEntity::class;
    protected $table = Table::GameModActiveBuild;
    protected $table_alias = TableAlias::GameModActiveBuild;
    protected $pk = DBField::GAME_MOD_ACTIVE_BUILD_ID;

    public static $fields = [
        DBField::GAME_MOD_ACTIVE_BUILD_ID,
        DBField::GAME_MOD_ID,
        DBField::UPDATE_CHANNEL,
        DBField::GAME_MOD_BUILD_ID,
        DBField::CREATE_TIME,

        DBField::IS_ACTIVE,
        DBField::CREATED_BY,
        DBField::MODIFIED_BY,
        DBField::DELETED_BY
    ];

    protected $foreign_managers = [
        GamesModsBuildsManager::class => DBField::GAME_MOD_BUILD_ID
    ];

    /**
     * @param GameModActiveBuildEntity $data
     * @param Request $request
     * @return GameModActiveBuildEntity
     */
    public function processVFields(DBManagerEntity $data, Request $request)
    {

    }

    /**
     * @param Request $request
     * @param $gameModId
     * @param $updateChannel
     * @param $gameModBuildId
     * @return GameModActiveBuildEntity
     */
    public function createUpdateGameModActiveBuild(Request $request, $gameModId, $updateChannel, $gameModBuildId)
    {
        if ($gameModActiveBuild = $this->getGameModActiveBuildByUpdateChannel($request, $gameModId, $updateChannel)) {

            if (!$gameModActiveBuild->is_active())
                $gameModActiveBuild->updateField(DBField::IS_ACTIVE, 1);

            $gameModActiveBuild->updateField(DBField::GAME_MOD_BUILD_ID, $gameModBuildId)->saveEntityToDb($request);

        } else {
            $data = [
                DBField::GAME_MOD_ID => $gameModId,
                DBField::UPDATE_CHANNEL => $updateChannel,
                DBField::GAME_MOD_BUILD_ID => $gameModBuildId,
                DBField::IS_ACTIVE => 1
            ];

            /** @var GameModActiveBuildEntity $gameModActiveBuild */
            $gameModActiveBuild = $this->query($request->db)->createNewEntity($request, $data);
        }

        return $gameModActiveBuild;
    }

    /**
     * @param Request $request
     * @param $gameModId
     * @param $updateChannel
     * @return array|GameModActiveBuildEntity
     */
    public function getGameModActiveBuildByUpdateChannel(Request $request, $gameModId, $updateChannel)
    {
        return $this->query($request)
            ->filter($this->filters->byGameModId($gameModId))
            ->filter($this->filters->byUpdateChannel($updateChannel))
            ->get_entity($request);
    }

    /**
     * @param Request $request
     * @param $gameModId
     * @param $updateChannel
     * @return array
     */
    public function getGameActiveBuildVersionSummaryByUpdateChannel(Request $request, $gameModId, $updateChannel)
    {
        $gamesModsBuildsManager = $request->managers->gamesModsBuilds();

        $fields = [
            $gamesModsBuildsManager->createPkField(),
            $gamesModsBuildsManager->field(DBField::BUILD_VERSION)
        ];

        try {
            $activeBuildVersionSummary = $this->query($request->db)
                ->fields($fields)
                ->inner_join($gamesModsBuildsManager)
                ->filter($this->filters->byGameModId($gameModId))
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
     * @param $gameModId
     * @return array
     */
    public function getGameModActiveBuildVersionSummaries(Request $request, $gameModId)
    {
        $gamesModsBuildsManager = $request->managers->gamesModsBuilds();

        $fields = [
            $gamesModsBuildsManager->createPkField(),
            $gamesModsBuildsManager->field(DBField::UPDATE_CHANNEL),
            $gamesModsBuildsManager->field(DBField::BUILD_VERSION)
        ];

        $activeBuildVersionSummaries = $this->query($request->db)
            ->fields($fields)
            ->inner_join($gamesModsBuildsManager)
            ->filter($this->filters->byGameModId($gameModId))
            ->filter($this->filters->isActive())
            ->get_list();

        if ($activeBuildVersionSummaries)
            $activeBuildVersionSummaries = array_index($activeBuildVersionSummaries, DBField::UPDATE_CHANNEL);

        return $activeBuildVersionSummaries;
    }
}

class GamesModsActiveCustomAssetsManager extends BaseEntityManager
{
    protected $entityClass = GameModActiveCustomAssetEntity::class;
    protected $table = Table::GameModActiveCustomAsset;
    protected $table_alias = TableAlias::GameModActiveCustomAsset;
    protected $pk = DBField::GAME_MOD_ACTIVE_CUSTOM_ASSET_ID;

    public static $fields = [
        DBField::GAME_MOD_ACTIVE_CUSTOM_ASSET_ID,
        DBField::GAME_MOD_ID,
        DBField::GAME_MOD_BUILD_ID,
        DBField::UPDATE_CHANNEL,
        DBField::SLUG,
        DBField::CONTEXT_X_GAME_ASSET_ID,
        DBField::FIRST_ACTIVE_GAME_BUILD_ID,
        DBField::IS_PUBLIC,
        DBField::IS_ACTIVE,
        DBField::CREATED_BY,
        DBField::MODIFIED_BY,
        DBField::DELETED_BY
    ];

    /**
     * @param GameModActiveCustomAssetEntity $data
     * @param Request $request
     * @return GameModActiveCustomAssetEntity
     */
    public function processVFields(DBManagerEntity $data, Request $request)
    {

    }

    /**
     * @param Request $request
     * @param $gameModId
     * @param $updateChannel
     * @param $gameModBuildId
     * @param $slug
     * @param $contextXGameAssetId
     * @param $isPublic
     * @return GameModActiveCustomAssetEntity
     */
    public function createNewGameModActiveCustomAsset(Request $request, $gameModId, $updateChannel, $gameModBuildId, $slug,
                                                   $contextXGameAssetId, $isPublic)
    {
        if (!$isPublic)
            $isPublic = 0;
        else
            $isPublic = 1;

        $data = [
            DBField::GAME_MOD_ID => $gameModId,
            DBField::UPDATE_CHANNEL => $updateChannel,
            DBField::GAME_MOD_BUILD_ID => $gameModBuildId,
            DBField::SLUG => $slug,
            DBField::CONTEXT_X_GAME_ASSET_ID => $contextXGameAssetId,
            DBField::IS_PUBLIC => $isPublic,
            DBField::IS_ACTIVE => 1
        ];

        return $this->query($request->db)->createNewEntity($request, $data);

    }

    /**
     * @param Request $request
     * @param $gameModId
     * @param $updateChannel
     * @param $gameModBuildId
     * @param $slug
     * @param null $contextXGameAssetId
     * @param int $isPublic
     * @return GameModActiveCustomAssetEntity
     */
    public function createUpdateGameModActiveCustomAsset(Request $request, $gameModId, $updateChannel, $gameModBuildId, $slug,
                                                      $contextXGameAssetId = null, $isPublic = 0)
    {
        if ($isPublic)
            $isPublic = 1;

        $gameActiveCustomAsset = $this->getGameModActiveCustomAssetLinkByGameModBuildIdAndSlug($request, $gameModBuildId, $slug);

        if ($gameActiveCustomAsset) {

            if (!$gameActiveCustomAsset->is_active())
                $gameActiveCustomAsset->updateField(DBField::IS_ACTIVE, 1);

            $gameActiveCustomAsset->updateField(DBField::IS_PUBLIC, $isPublic);
            $gameActiveCustomAsset->updateField(DBField::CONTEXT_X_GAME_ASSET_ID, $contextXGameAssetId);
            $gameActiveCustomAsset->saveEntityToDb($request);

        } else {
            $gameActiveCustomAsset = $this->createNewGameModActiveCustomAsset(
                $request,
                $gameModId,
                $updateChannel,
                $gameModBuildId,
                $slug,
                $contextXGameAssetId,
                $isPublic
            );
        }

        return $gameActiveCustomAsset;
    }

    /**
     * @param Request $request
     * @param $gameModIds
     * @param $updateChannel
     * @param null $gameModBuildId
     * @return GameModActiveCustomAssetEntity[]
     */
    public function getGameModActiveCustomAssetLinksByGameModBuildIds(Request $request, $gameModBuildIds)
    {
        return $this->query($request->db)
            ->filter($this->filters->byGameModBuildId($gameModBuildIds))
            ->filter($this->filters->isActive())
            ->sort_asc(DBField::SLUG)
            ->get_entities($request);
    }

    /**
     * @param Request $request
     * @param $gameModBuildId
     * @param $slug
     * @return GameModActiveCustomAssetEntity
     */
    public function getGameModActiveCustomAssetLinkByGameModBuildIdAndSlug(Request $request, $gameModBuildId , $slug)
    {
        return $this->query($request->db)
            ->filter($this->filters->byGameModBuildId($gameModBuildId))
            ->filter($this->filters->bySlug($slug))
            ->get_entity($request);
    }

    /**
     * @param Request $request
     * @param $gameModBuildId
     * @param $slug
     * @return array|GameModActiveCustomAssetEntity
     */
    public function getActiveGameModActiveCustomAssetLinkByGameModBuildIdAndSlug(Request $request, $gameModBuildId, $slug)
    {
        return $this->query($request->db)
            ->filter($this->filters->byGameModBuildId($gameModBuildId))
            ->filter($this->filters->bySlug($slug))
            ->filter($this->filters->isActive())
            ->get_entity($request);
    }

    /**
     * @param Request $request
     * @param GameModActiveCustomAssetEntity $oldGameModActiveCustomAsset
     * @param $newSlug
     */
    public function renameCustomGameModAssetSlug(Request $request, GameModActiveCustomAssetEntity $oldGameModActiveCustomAsset, $newSlug)
    {
        $gamesAssetsManager = $request->managers->gamesAssets();

        $contextXGamesAssetsManager = $request->managers->contextXGamesAssets();

        // Deactivate Old Asset
        $oldGameModActiveCustomAsset->updateField(DBField::IS_ACTIVE, 0)->saveEntityToDb($request);

        // Get all the old game asset history -- we need this to create new links.
        $oldCustomGameAssets = $gamesAssetsManager->getCustomGameModBuildAssetsHistoryBySlug(
            $request,
            $oldGameModActiveCustomAsset->getGameModBuildId(),
            $oldGameModActiveCustomAsset->getSlug()
        );

        /** @var CustomGameModBuildAssetEntity[] $oldCustomGameAssets */
        $oldCustomGameAssets = array_index(array_reverse($oldCustomGameAssets), VField::CUSTOM_GAME_ASSET_ID);

        $newCustomGameAssets = [];
        $newActiveCustomGameAssetId = null;

        foreach ($oldCustomGameAssets as $oldCustomGameAsset) {
            $customGameAsset = $contextXGamesAssetsManager->linkGameModAssetToGameModBuild(
                $request,
                $oldCustomGameAsset->getPk(),
                $oldCustomGameAsset->getGameModBuildId(),
                $oldCustomGameAsset->getFolderPath(),
                $oldCustomGameAsset->getFileName(),
                $oldCustomGameAsset->getUpdateChannel(),
                $newSlug,
                $oldCustomGameAsset->getCreateTime()
            );
            if ($oldCustomGameAsset->getCustomGameAssetId() == $oldGameModActiveCustomAsset->getContextXGameAssetId())
                $newActiveCustomGameAssetId = $customGameAsset->getCustomGameAssetId();

            $newCustomGameAssets[$customGameAsset->getCustomGameAssetId()] = $customGameAsset;
        }

        $gameActiveCustomAsset = $this->createUpdateGameModActiveCustomAsset(
            $request,
            $oldGameModActiveCustomAsset->getGameModId(),
            $oldGameModActiveCustomAsset->getUpdateChannel(),
            $oldGameModActiveCustomAsset->getGameModBuildId(),
            $newSlug,
            $newActiveCustomGameAssetId,
            $oldGameModActiveCustomAsset->getIsPublic()
        );
    }

    /**
     * @param Request $request
     * @param $gameModBuildId
     * @param $slug
     */
    public function deleteGameModBuildActiveCustomAssetLink(Request $request, $gameModBuildId, $slug)
    {

        $deactivationData = [
            DBField::IS_ACTIVE => 0,
            DBField::MODIFIED_BY => $request->requestId,
            DBField::DELETED_BY => $request->requestId,
        ];

        $this->query($request->db)
            ->filter($this->filters->byGameModBuildId($gameModBuildId))
            ->filter($this->filters->bySlug($slug))
            ->update($deactivationData);
    }

    /**
     * @param Request $request
     * @param $gameModBuildId
     * @return GameModActiveCustomAssetEntity[]
     */
    public function getActiveGameModActiveCustomAssetLinksByGameModBuildId(Request $request, $gameModBuildId)
    {
        $gamesAssetsManager = $request->managers->gamesAssets();

        /** @var GameModActiveCustomAssetEntity[] $gameModActiveCustomAssets */
        $gameModActiveCustomAssets = $this->query($request->db)
            ->filter($this->filters->byGameModBuildId($gameModBuildId))
            ->filter($this->filters->isActive())
            ->get_entities($request);

        if ($gameModActiveCustomAssets) {
            $customGameAssetIds = array_extract(DBField::CONTEXT_X_GAME_ASSET_ID, $gameModActiveCustomAssets);

            if ($customGameAssetIds) {

                $customGameAssets = $gamesAssetsManager->getCustomGameModBuildAssetsByCustomGameAssetIds($request, $gameModBuildId, $customGameAssetIds);

                if ($customGameAssets)
                    $customGameAssets = array_index($customGameAssets, VField::CUSTOM_GAME_ASSET_ID);

                foreach ($gameModActiveCustomAssets as $gameActiveCustomAsset) {
                    $customGameAssetId = $gameActiveCustomAsset->getContextXGameAssetId() ;

                    if ($customGameAssetId && array_key_exists($customGameAssetId, $customGameAssets))
                        $gameActiveCustomAsset->updateField(VField::GAME_ASSET, $customGameAssets[$customGameAssetId]);
                }
            }
        }

        return $gameModActiveCustomAssets;
    }

    /**
     * @param Request $request
     * @param GameModBuildEntity $oldGameModBuild
     * @param GameModBuildEntity $newGameModBuild
     */
    public function cloneCustomGameModBuildAssets(Request $request, GameModBuildEntity $oldGameModBuild, GameModBuildEntity $newGameModBuild)
    {
        $gamesAssetsManager = $request->managers->gamesAssets();
        $contextXGameAssetsManager = $request->managers->contextXGamesAssets();

        $customGameModAssetLinks = $this->getActiveGameModActiveCustomAssetLinksByGameModBuildId($request, $oldGameModBuild->getPk());

        if ($customGameModAssetLinks) {

            $customGameAssetIds = array_extract(DBField::CONTEXT_X_GAME_ASSET_ID, $customGameModAssetLinks);

            $customGameAssets = $gamesAssetsManager->getCustomGameModBuildAssetsByCustomGameAssetIds(
                $request,
                $oldGameModBuild->getPk(),
                $customGameAssetIds
            );
            if ($customGameAssets)
                $customGameAssets = array_index($customGameAssets, VField::CUSTOM_GAME_ASSET_ID);
        } else {
            $customGameAssets = [];
        }


        $liveCustomGameModAssets = [];
        foreach ($customGameModAssetLinks as $customGameAssetLink) {

            if (array_key_exists($customGameAssetLink->getContextXGameAssetId(), $customGameAssets)) {

                $customGameModAsset = $customGameAssets[$customGameAssetLink->getContextXGameAssetId()];

                $newCustomGameModAsset = $contextXGameAssetsManager->linkGameModAssetToGameModBuild(
                    $request,
                    $customGameModAsset->getPk(),
                    $newGameModBuild->getPk(),
                    $customGameModAsset->getFolderPath(),
                    $customGameModAsset->getFileName(),
                    $newGameModBuild->getUpdateChannel(),
                    $customGameModAsset->getSlug(),
                    $customGameModAsset->getCreateTime()
                );

                $liveCustomGameModAssetLink = $this->createNewGameModActiveCustomAsset(
                    $request,
                    $newGameModBuild->getGameModId(),
                    $newGameModBuild->getUpdateChannel(),
                    $newGameModBuild->getPk(),
                    $customGameAssetLink->getSlug(),
                    $newCustomGameModAsset->getCustomGameAssetId(),
                    $customGameAssetLink->getIsPublic()
                );
                $liveCustomGameModAssets[] = $liveCustomGameModAssetLink;
            }
        }
    }


    /**
     * @param Request $request
     * @param $gameModBuildId
     * @param $slug
     * @return array|GameModActiveCustomAssetEntity
     */
    public function getPublicGameModActiveCustomAssetLinkByGameModBuildIdAndSlug(Request $request, $gameModBuildId, $slug)
    {
        return $this->query($request->db)
            ->filter($this->filters->byGameModBuildId($gameModBuildId))
            ->filter($this->filters->bySlug($slug))
            ->filter($this->filters->isActive())
            ->filter($this->filters->isPublic())
            ->get_entity($request);
    }
}

class GamesModsLicensesManager extends BaseEntityManager
{
    protected $entityClass = GameModLicenseEntity::class;
    protected $table = Table::GameModLicense;
    protected $table_alias = TableAlias::GameModLicense;
    protected $pk = DBField::GAME_MOD_LICENSE_ID;

    public static $fields = [
        DBField::GAME_MOD_LICENSE_ID,
        DBField::GAME_MOD_ID,
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
     * @param GameModLicenseEntity $data
     * @param Request $request
     */
    public function processVFields(DBManagerEntity $data, Request $request)
    {
        if (!$data->hasField(VField::USER))
            $data->updateField(VField::USER, []);
    }

    /**
     * @param Request $request
     * @param GameModLicenseEntity[]|GameModLicenseEntity $gameModLicenses
     */
    public function postProcessGameModLicenses(Request $request, $gameModLicenses)
    {
        $usersManager = $request->managers->users();

        if ($gameModLicenses) {
            if ($gameModLicenses instanceof GameModLicenseEntity)
                $gameModLicenses = [$gameModLicenses];

            /** @var GameModLicenseEntity[] $gameModLicenses */
            $gameModLicenses = $this->index($gameModLicenses);

            $userIds = unique_array_extract(DBField::USER_ID, $gameModLicenses);
            $users = $usersManager->getUsersByIds($request, $userIds);
            $users = $usersManager->index($users);

            foreach ($gameModLicenses as $gameModLicense) {
                $user = $users[$gameModLicense->getUserId()];
                $gameModLicense->setUser($user);
            }

        }
    }

    /**
     * @param Request $request
     * @param $gameModId
     * @param $userId
     * @param $updateChannel
     * @param $startTime
     * @param null $endTime
     * @return GameModLicenseEntity
     */
    public function createNewGameModLicense(Request $request, $gameModId, $userId, $updateChannel, $startTime, $endTime = null)
    {
        $data = [
            DBField::GAME_MOD_ID => $gameModId,
            DBField::USER_ID => $userId,
            DBField::UPDATE_CHANNEL => $updateChannel,
            DBField::START_TIME => $startTime,
            DBField::END_TIME => $endTime,
            DBField::CREATE_TIME => $request->getCurrentSqlTime(),
            DBField::IS_ACTIVE => 1,
            DBField::CREATED_BY => $request->requestId
        ];

        /** @var GameModLicenseEntity $gameModLicense */
        $gameModLicense = $this->query($request->db)->createNewEntity($request, $data);

        return $gameModLicense;
    }

    /**
     * @param Request $request
     * @param $gameModLicenseId
     * @return GameModLicenseEntity
     */
    public function getGameModLicenseById(Request $request, $gameModLicenseId, $gameModId = null, $expand = true)
    {
        /** @var GameModLicenseEntity $gameModLicense */
        $gameModLicense = $this->query($request->db)
            ->filter($this->filters->byPk($gameModLicenseId))
            ->filter($this->filters->byGameModId($gameModId))
            ->get_entity($request);

        if ($expand)
            $this->postProcessGameModLicenses($request, $gameModLicense);

        return $gameModLicense;
    }

    /**
     * @param Request $request
     * @param $gameModId
     * @param bool $expand
     * @return GameModLicenseEntity[]
     */
    public function getActiveGameModLicensesByGameModId(Request $request, $gameModId, $updateChannel = null, $expand = true)
    {
        /** @var GameModLicenseEntity[] $gameModLicenses */
        $gameModLicenses = $this->query($request->db)
            ->filter($this->filters->byGameModId($gameModId))
            ->filter($this->filters->byUpdateChannel($updateChannel))
            ->filter($this->filters->isActive())
            ->get_entities($request);

        if ($expand)
            $this->postProcessGameModLicenses($request, $gameModLicenses);

        return $gameModLicenses;
    }

    /**
     * @param Request $request
     * @param $gameModId
     * @param null $updateChannel
     * @param bool $expand
     * @return UserEntity[]
     */
    public function getActiveGameModLicenseUsersByGameModId(Request $request, $gameModId, $updateChannel = null, $expand = true)
    {
        $gameModLicenses = $this->getActiveGameModLicensesByGameModId($request, $gameModId, $updateChannel, $expand);

        /** @var UserEntity[] $users */
        $users = array_extract(VField::USER, $gameModLicenses);

        $licenses = [];

        foreach ($gameModLicenses as $gameModLicense) {
            if (!array_key_exists($gameModLicense->getUserId(), $licenses))
                $licenses[$gameModLicense->getUserId()] = [];

            $licenses[$gameModLicense->getUserId()][] = $gameModLicense;
        }

        foreach ($users as $user) {
            $user->updateField(VField::GAME_MOD_LICENSES, $licenses[$user->getPk()]);
        }

        return $users;
    }

    /**
     * @param Request $request
     * @param $userId
     * @param bool $expand
     * @return GameModLicenseEntity[]
     */
    public function getActiveGameModsLicensesByUserId(Request $request, $userId, $expand = true)
    {
        /** @var GameModLicenseEntity[] $gameModLicenses */
        $gameModLicenses = $this->query($request->db)
            ->filter($this->filters->byUserId($userId))
            ->filter($this->filters->isActive())
            ->get_entities($request);

        if ($expand)
            $this->postProcessGameModLicenses($request, $gameModLicenses);

        return $gameModLicenses;
    }

    /**
     * @param Request $request
     * @param $userId
     * @param $gameModId
     * @param bool $expand
     * @return GameModLicenseEntity[]
     */
    public function getActiveGameModLicensesByUserId(Request $request, $userId, $gameModId, $updateChannel = null, $expand = true)
    {
        /** @var GameModLicenseEntity[] $gameModLicenses */
        $gameModLicenses = $this->query($request->db)
            ->filter($this->filters->byUserId($userId))
            ->filter($this->filters->byGameModId($gameModId))
            ->filter($this->filters->byUpdateChannel($updateChannel))
            ->filter($this->filters->isActive())
            ->get_entities($request);

        if ($expand)
            $this->postProcessGameModLicenses($request, $gameModLicenses);

        return $gameModLicenses;
    }
}
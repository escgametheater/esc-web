<?php
/**
 * Created by PhpStorm.
 * User: christophercarter
 * Date: 6/7/18
 * Time: 11:32 AM
 */

class GamesApiV1Controller extends BaseApiV1Controller implements BaseApiControllerV1CRUDInterface {

    const REQUIRES_POST = true;
    const REQUIRES_AUTH = true;

    /** @var GamesManager $manager */
    protected $manager;
    /** @var GamesBuildsManager $gamesBuildsManager */
    protected $gamesBuildsManager;
    /** @var GamesDataManager $gamesDataManager */
    protected $gamesDataManager;
    /** @var GamesModsDataManager $gamesModsDataManager */
    protected $gamesModsDataManager;
    /** @var GamesAssetsManager $gamesAssetsManager */
    protected $gamesAssetsManager;
    /** @var GamesModsActiveBuildsManager $gamesModsActiveBuildsManager */
    protected $gamesModsActiveBuildsManager;
    /** @var GamesModsManager $gamesModsManager */
    protected $gamesModsManager;
    /** @var OrganizationsManager */
    protected $organizationsManager;

    /** @var HostsManager $hostsManager */
    protected $hostsManager;
    /** @var ActivationsManager $activationsManager */
    protected $activationsManager;
    /** @var ActivityManager $activityManager */
    protected $activityManager;
    /** @var OrganizationsActivityManager $organizationsActivityManager */
    protected $organizationsActivityManager;

    protected $pages = [

        // Index Page
        '' => 'handle_index',

        // CRUD Endpoints
        'get' => 'handle_get',
        'create' => 'handle_create',
        'update' => 'handle_update',
        'delete' => 'handle_delete',

        // List
        'list' => 'handle_list',
        'list-dev' => 'handle_list_for_development',

        // Game Data
        'get-data' => 'handle_get_data',

        // Cloud Games
        'list-running-cloud-games' => 'handle_list_running_cloud_games',

        // Game Assets
        'download-custom-game-asset' => 'handle_download_custom_game_asset',
        'download-custom-game-mod-asset' => 'handle_download_custom_game_mod_asset',
    ];

    /**
     * @param Request $request
     */
    protected function pre_handle(Request $request)
    {
        $this->gamesModsManager = $request->managers->gamesMods();
        $this->gamesModsActiveBuildsManager = $request->managers->gamesModsActiveBuilds();
        $this->gamesDataManager = $request->managers->gamesData();
        $this->gamesModsDataManager = $request->managers->gamesModsData();
        $this->gamesAssetsManager = $request->managers->gamesAssets();

        $this->hostsManager = $request->managers->hosts();
        $this->activationsManager = $request->managers->activations();

        $this->organizationsManager = $request->managers->organizations();

        $this->activityManager = $request->managers->activity();
        $this->organizationsActivityManager = $request->managers->organizationsActivities();
    }

    /**
     * @param Request $request
     * @return HttpResponse
     */
    public function handle_index(Request $request) : HttpResponse
    {
        $request->user->sendFlashMessage('Index Not Implemented Yet');
        return $this->redirect(HOMEPAGE);
    }

    /**
     * @param Request $request
     * @return ApiV1Response
     */
    public function handle_get(Request $request) : ApiV1Response
    {
        $this->form = $this->buildGetEntityForm($request);

        if ($this->form->is_valid()) {
            $game = $this->manager->getGameById($request, $this->form->getPkValue());
            $this->setResults($game);
        }

        return $this->renderApiV1Response($request);
    }

    /**
     * @param Request $request
     * @return ApiV1Response
     */
    public function handle_create(Request $request): ApiV1Response
    {
        $fields = $this->manager->getFormFields($request, false);
        $fields[] = new SlugField(VField::ORGANIZATION_SLUG, "Organization Slug", 0, false);
        $defaults = [ DBField::GAME_TYPE_ID =>GamesTypesManager::ID_MULTI_PLAYER ];

        $this->form = new ApiV1PostForm($fields, $request, $defaults);

        if ($this->form->is_valid()) {

            $displayName = $this->form->getCleanedValue(DBField::DISPLAY_NAME);

            $organizations = $this->organizationsManager->getOrganizationsByUserId($request, $request->user->id);

            if ($organizations) {

                $gameType = $this->form->getCleanedValue(DBField::GAME_TYPE_ID);
                $gameCategoryId = $this->form->getCleanedValue(DBField::GAME_CATEGORY_ID);
                $organizationSlug = $this->form->getCleanedValue(VField::ORGANIZATION_SLUG);

                $activeOrganization = $organizations[0];

                if ($organizationSlug) {
                    foreach ($organizations as $organization)
                        if ($organization->getSlug() == $organizationSlug)
                            $activeOrganization = $organization;
                }

                $activeOrganization->expandOrganization($request);

                $right = OrganizationsBaseRightsManager::RIGHT_ORG_GAMES_PROFILE;
                $accessLevel = Rights::MODERATE;

                if ($activeOrganization->permissions->has($right, $accessLevel)) {

                    $game = $this->manager->createNewGame(
                        $request,
                        null,
                        $displayName,
                        EntityType::ORGANIZATION,
                        $activeOrganization->getPk(),
                        $gameCategoryId,
                        $gameType,
                        GamesEnginesManager::ID_UNITY,
                        0,
                        $activeOrganization->getSlug()
                    );

                    $user = $request->user->getEntity();

                    $activity = $this->activityManager->trackActivity(
                        $request,
                        ActivityTypesManager::ACTIVITY_TYPE_USER_CREATE_GAME,
                        $user->getPk(),
                        $game->getPk(),
                        $user->getUiLanguageId(),
                        $user
                    );

                    $this->organizationsActivityManager->trackOrganizationActivity(
                        $request,
                        $activity,
                        $activeOrganization,
                        $activeOrganization->getOrganizationUserByUserId($user->getPk())
                    );

                } else {
                    $this->form->set_error("Permission Denied: {$right}<->{$accessLevel}");
                }


            } else {
                $this->form->set_error('No Organizations Found For User');
            }


            $this->setResults($game);
        }

        return $this->renderApiV1Response($request);
    }

    /**
     * @param Request $request
     * @return ApiV1Response
     */
    public function handle_update(Request $request): ApiV1Response
    {
        $getEntityForm = $this->buildGetEntityForm($request);

        if ($getEntityForm->is_valid()) {

            $game = $this->manager->getGameById($request, $getEntityForm->getPkValue());

            $fields = $this->manager->getFormFields($request);
            $this->form = new ApiV1PostForm($fields, $request, $game);

            if ($this->form->is_valid()) {
                $game->assignByForm($this->form)->saveEntityToDb($request);
                $this->setResults($game);
            }

        } else {
            $this->form = $getEntityForm;
        }

        return $this->renderApiV1Response($request);
    }

    /**
     * @param Request $request
     * @return ApiV1Response
     */
    public function handle_delete(Request $request): ApiV1Response
    {
        $this->form = $this->buildGetEntityForm($request);

        if ($this->form->is_valid()) {
            $game = $this->manager->getGameById($request, $this->form->getPkValue());

            $this->manager->deactivateEntity($request, $game);
            $this->setResults($game);
        }

        return $this->renderApiV1Response($request);
    }

    /**
     * @param Request $request
     * @return ApiV1Response
     */
    public function handle_list(Request $request) : ApiV1Response
    {
        $updateChannels = $this->manager->getUpdateChannelOptions();

        $fields = [
            new SelectField(DBField::UPDATE_CHANNEL, 'Update Channel', $updateChannels, false)
        ];

        $this->form = new ApiV1PostForm($fields, $request);

        if ($this->form->is_valid()) {
            $updateChannel = $this->form->getCleanedValue(DBField::UPDATE_CHANNEL, null);

            $games = $this->manager->getPlayableGamesByUserId($request, $request->user->id, $updateChannel, GamesTypesManager::ID_MULTI_PLAYER);

            $this->setResults($games);
        }

        return $this->renderApiV1Response($request);
    }
    /**
     * List games for a game developer
     * @param Request $request
     * @return ApiV1Response
     */
    public function handle_list_for_development(Request $request) : ApiV1Response
    {
        $fields = [];

        $this->form = new ApiV1PostForm($fields, $request);

        if ($this->form->is_valid()) {
            $games = $this->manager->getDevGamesByUserId($request, $request->user->id);

            $this->setResults($games);
        }

        return $this->renderApiV1Response($request);
    }

    /**
     * @param Request $request
     * @return ApiV1Response
     */
    public function handle_get_data(Request $request) : HttpResponse
    {
        $updateChannels = $this->manager->getUpdateChannelOptions();

        $fields = [
            new CharField(DBField::SLUG, 'Game Slug', 0, false),
            new CharField(VField::GAME_SLUG, 'Game Slug', 0, false),
            new IntegerField(DBField::GAME_ID, 'Game Id', false),
            new SelectField(DBField::UPDATE_CHANNEL, 'Update Channel', $updateChannels, false),
            new CharField(DBField::KEY, 'Key', 128, true),
            new IntegerField(DBField::GAME_MOD_BUILD_ID, 'Game Mod Build Id', false)
        ];

        $this->form = $this->buildGetEntityForm($request, $fields);

        if ($this->form->is_valid()) {

            $slug = $this->form->getCleanedValue(DBField::SLUG);
            if (!$slug)
                $slug = $this->form->getCleanedValue(VField::GAME_SLUG);

            $gameModBuildId = $this->form->getCleanedValue(DBField::GAME_MOD_BUILD_ID);

            $updateChannel = $this->form->getCleanedValue(DBField::UPDATE_CHANNEL, GamesManager::UPDATE_CHANNEL_DEV);

            if ($slug) {
                $game = $this->manager->getGameBySlug($request, $slug);
                $gameId = $game->getPk();
            } else {
                $gameId = $this->form->getCleanedValue(DBField::GAME_ID);
            }

            $key = $this->form->getCleanedValue(DBField::KEY);

            if ($gameId) {
                if ($gameData = $this->gamesDataManager->getActiveGameDataByChannelAndKey($request, $gameId, $updateChannel, $key)) {

                    $gameModData = [];

                    $results = [];

                    if ($gameModBuildId) {
                        $gameModData = $this->gamesModsDataManager->getModdedGameData($request, $gameId, $gameModBuildId, $key);
                    }

                    if ($gameData) {
                        if ($gameId == GamesManager::ID_BOROUGH_GODS && $key == GamesDataManager::KEY_TICKER_TEXT) {
                            $results = $gameData->getGameDataSheetByName(GamesDataSheetsManager::SINGLE_SHEET_NAME)->getProcessedRows();
                        } else {
                            $results = $gameData->getSheetDataArrays();
                        }
                    }

                    if ($gameModData) {
                        if ($gameData) {
                            if ($gameId == GamesManager::ID_BOROUGH_GODS && $key == GamesDataManager::KEY_TICKER_TEXT) {
                                if ($sheet = $gameData->getGameDataSheetByName(GamesDataSheetsManager::SINGLE_SHEET_NAME)) {
                                    if ($sheet->can_mod() && $modSheet = $gameModData->getGameModDataSheetByName(GamesDataSheetsManager::SINGLE_SHEET_NAME)) {
                                        if ($sheet->is_mod_replace())
                                            $results = $modSheet->getProcessedRows();
                                        else
                                            $results = array_merge($results, $modSheet->getProcessedRows());
                                    }
                                }
                            } else {
                                foreach ($gameData->getGameDataSheets() as $gameDataSheet) {
                                    if ($gameDataSheet->can_mod() && $modSheet = $gameModData->getGameModDataSheetByName($gameDataSheet->getName())) {
                                        if ($gameDataSheet->is_mod_replace())
                                            $results[$gameDataSheet->getName()] = $modSheet->getProcessedRows();
                                        else
                                            $results[$gameDataSheet->getName()] = array_merge($results[$gameDataSheet->getName()], $modSheet->getProcessedRows());
                                    }
                                }
                            }
                        } else {
                            if ($gameId == GamesManager::ID_BOROUGH_GODS && $key == GamesDataManager::KEY_TICKER_TEXT) {
                                $results = $gameModData->getGameModDataSheetByName(GamesDataSheetsManager::SINGLE_SHEET_NAME)->getProcessedRowValues();
                            } else {
                                $results = $gameModData->getSheetDataArrays();
                            }
                        }
                    }

                    $this->setResults([$results]);
                }
            }

        }


        return $this->renderApiV1Response($request);
    }

    /**
     * @param Request $request
     * @return ApiV1Response
     */
    public function handle_list_running_cloud_games(Request $request): ApiV1Response
    {
        $this->form = new ApiV1PostForm([], $request);

        if ($this->form->is_valid()) {
            $results = [];
            $activeHostIds = [];
            $games = [];

            $activations = $this->activationsManager->getLiveRunningCloudActivationsForWan($request, true, "+2 minute");

            foreach ($activations as $activation) {

                if (!array_key_exists($activation->getGameId(), $games))
                    $games[$activation->getGameId()] = $activation->getGame();

                if (!in_array($activation->getHostId(), $activeHostIds)) {
                    $activeHostIds[] = $activation->getHostId();

                    // Todo: remove presumption of live build on activations and cloud games
                    $gameBuild = $activation->getGame()->getGameBuildByUpdateChannel(GamesManager::UPDATE_CHANNEL_LIVE);
                    $gameBuildId = $gameBuild ? $gameBuild->getPk() : null;

                    $gameModBuildId = null;
                    if ($activation->getGameModId()) {
                        $gameModActiveBuild = $this->gamesModsActiveBuildsManager->getGameModActiveBuildByUpdateChannel($request, $activation->getGameModId(), GamesManager::UPDATE_CHANNEL_LIVE);
                        $gameModBuildId = $gameModActiveBuild->getGameModBuildId();
                    }

                    $results[] = $this->formatCloudGameResult(
                        $activation->getGame()->getSlug(),
                        $activation->getHost()->getSlug(),
                        $activation->getHostId(),
                        $activation->getGameId(),
                        $activation->getGameModId(),
                        $gameBuildId,
                        $gameModBuildId,
                        $activation->getPk(),
                        $activation->getStartTime(),
                        $activation->getEndTime(),
                        $activation->getLocalStartTime(),
                        $activation->getLocalEndTime()
                    );
                }
            }

            $hosts = $this->hostsManager->getHostsWithOfflineGamesActive($request, $activeHostIds);

            if ($hosts) {
                $offlineGameIds = array_extract(DBField::OFFLINE_GAME_ID, $hosts);
                foreach ($offlineGameIds as $key => $gameId) {
                    if (array_key_exists($gameId, $games))
                        unset($offlineGameIds[$key]);
                }

                if ($offlineGameIds) {
                    $offlineGames = $this->manager->getGamesByIds($request, $offlineGameIds, true);
                    foreach ($offlineGames as $offlineGame)
                        $games[$offlineGame->getPk()] = $offlineGame;
                }

                foreach ($hosts as $host) {

                    if (!in_array($host->getPk(), $activeHostIds)) {
                        /** @var GameEntity $game */
                        $game = $games[$host->getOfflineGameId()];
                        $gameBuild = $game->getGameBuildByUpdateChannel(GamesManager::UPDATE_CHANNEL_LIVE);
                        $gameBuildId = $gameBuild ? $gameBuild->getPk() : null;
                        $gameModBuildId = null;
                        if ($host->getOfflineGameModId()) {
                            $gameModActiveBuild = $this->gamesModsActiveBuildsManager->getGameModActiveBuildByUpdateChannel($request, $host->getOfflineGameModId(), GamesManager::UPDATE_CHANNEL_LIVE);
                            $gameModBuildId = $gameModActiveBuild->getGameModBuildId();
                        }
                        $results[] = $this->formatCloudGameResult(
                            $game->getSlug(),
                            $host->getSlug(),
                            $host->getPk(),
                            $host->getOfflineGameId(),
                            $host->getOfflineGameModId(),
                            $gameBuildId,
                            $gameModBuildId
                        );
                    }
                }
            }

            if ($results)
                $this->setResults($results);
        }

        return $this->renderApiV1Response($request);

    }

    /**
     * @param $gameSlug
     * @param $hostSlug
     * @param $gameModId
     * @param $startTime
     * @param $endTime
     * @return array
     */
    private function formatCloudGameResult($gameSlug, $hostSlug, $hostId, $gameId, $gameModId, $gameBuildId, $gameModBuildId = null, $activationId = null, $startTime = null, $endTime = null, $localStartTime = null, $localEndTime = null)
    {
        return [
            VField::GAME_SLUG => $gameSlug,
            VField::HOST_SLUG => $hostSlug,
            DBField::HOST_ID => $hostId,
            DBField::GAME_ID => $gameId,
            DBField::GAME_MOD_ID => $gameModId,
            DBField::GAME_BUILD_ID => $gameBuildId,
            DBField::GAME_MOD_BUILD_ID => $gameModBuildId,
            DBField::ACTIVATION_ID => $activationId,
            DBField::START_TIME => $startTime,
            DBField::END_TIME => $endTime,
            VField::LOCAL_START_TIME => $localStartTime,
            VField::LOCAL_END_TIME => $localEndTime,
        ];
    }

    /**
     * @param Request $request
     * @return HttpResponse
     * @throws Exception
     */
    public function handle_download_custom_game_asset(Request $request): HttpResponse
    {
        if (!$updateChannel = $request->getSlugForEntity($this->url_key+1))
            throw new Http404();

        if (!$customGameAssetId = $request->getIdForEntity($this->url_key+2))
            throw new Http404();

        $gameAsset = $this->gamesAssetsManager->getSecureCustomGameAssetByCustomGameAssetId(
            $request,
            $customGameAssetId,
            $request->user->id,
            $updateChannel
        );

        if (!$gameAsset)
            throw new Http404();

        $customGameAssetFile = $request->s3->readIntoMemory($gameAsset->getBucket(), $gameAsset->getBucketKey());

        return new GameAssetResponse($gameAsset, $customGameAssetFile);
    }

    /**
     * @param Request $request
     * @return HttpResponse
     */
    public function handle_download_custom_game_mod_asset(Request $request): HttpResponse
    {
        if (!$updateChannel = $request->getSlugForEntity($this->url_key+1))
            throw new Http404();

        if (!$customGameAssetId = $request->getIdForEntity($this->url_key+2))
            throw new Http404();

        $gameAsset = $this->gamesAssetsManager->getSecureCustomGameModBuildAssetByCustomGameAssetId(
            $request,
            $customGameAssetId,
            $request->user->id,
            $updateChannel
        );

        if (!$gameAsset)
            throw new Http404();

        $customGameAssetFile = $request->s3->readIntoMemory($gameAsset->getBucket(), $gameAsset->getBucketKey());

        return new GameAssetResponse($gameAsset, $customGameAssetFile);
    }

}
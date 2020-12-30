<?php
/**
 * Content default class.
 *
 */
require "../../core/domain/controllers/base.php";

class Main extends BaseContent
{
    protected $url_key = 1;

    /** @var HostsManager $hostsManager */
    protected $hostsManager;
    /** @var HostsInstancesManager $hostsInstancesManager */
    protected $hostsInstancesManager;

    protected $pages = [
        // Pages
        '' => 'index',

        // Play
        'cga' => 'handle_custom_game_assets',
        'cgma' => 'handle_custom_game_mod_assets',
        'host-controller' => 'handle_host_controller',
        'game-controller' => 'handle_game_controller',
        '__node-ping' => 'node_ping',
        'api' => 'handle_api',
        'sdk-asset' => 'handle_sdk_asset',

        // Sitemaps
    ];

    /**
     * We are overriding the normal render method here to have custom handling for urls that are not part of our pages
     * as a fallback. Either the explicit url_key page route exists (e.g. /h/* or /i/*), or we check for there being
     * a host with the slug that matches the url key requested.
     *
     * If there's no slug matching at that point, we render 404, or handle specifically the cases of active host instances.
     *
     * @param Request $request
     * @param null $url_key
     * @param null $pages
     * @param null $render_default
     * @param null $root
     * @return HtmlResponse|HttpResponseRedirect|JSONResponse
     */
    public function render(Request $request, $url_key = null, $pages = null, $render_default = null, $root = null)
    {
        $this->hostsManager = $request->managers->hosts();
        $this->hostsInstancesManager = $request->managers->hostsInstances();

        $pageSlug = $request->getSlugForEntity($this->url_key);
        $func = array_get($this->pages, $pageSlug, null);

        if ($func !== null) {
            return $this->$func($request, ...$this->controllerArgs);
        } else {

            return $this->handle_host_slug($request, $pageSlug);
        }
    }

    /**
     * @param Request $request
     * @return HttpResponse
     */
    public function handle_api(Request $request)
    {
        require "api/api-controller.php";
        $controller = new PlayApiController($this->templateFactory);
        return $controller->render($request, $this->url_key+1);
    }


    /**
     * @param Request $request
     * @param HostEntity $host
     * @return HtmlResponse|JSONResponse|HttpResponseRedirect
     * @throws ObjectNotFound
     * @throws PermissionsException
     */
    protected function handle_host_slug(Request $request, $pageSlug)
    {
        $gamesManager = $request->managers->games();
        $gamesModsManager = $request->managers->gamesMods();
        $gamesBuildsManager = $request->managers->gamesBuilds();
        $gamesAssetsManager = $request->managers->gamesAssets();
        $gamesInstancesManager = $request->managers->gamesInstances();
        $hostInstancesManager = $request->managers->hostsInstances();
        $activationsManager = $request->managers->activations();
        $gamesInstancesRoundsManager = $request->managers->gamesInstancesRounds();
        $gamesInstancesRoundsPlayersManager = $request->managers->gamesInstancesRoundsPlayers();
        $userCoinsManager = $request->managers->userCoins();
        $guestCoinsManager = $request->managers->guestCoins();
        $organizationsMetaManager = $request->managers->organizationsMeta();
        $organizationsManager = $request->managers->organizations();

        if (!$host = $this->hostsManager->getHostBySlug($request, $pageSlug)) {
            return $this->redirect($request->getPlayUrl(HOMEPAGE));
        }

        $controllerTypeSlug = $request->getSlugForEntity($this->url_key + 1);


        $baseHref = $request->getPlayUrl($request->path);

        $userIsHostAdmin = false;

        if ($host->getOwnerTypeId() == EntityType::ORGANIZATION) {
            $gaIdMeta = $organizationsMetaManager->getOrganizationMetaByKey($request, $host->getOwnerId(), OrganizationsMetaManager::KEY_GOOGLE_ANALYTICS_ID);

            if ($gaIdMeta && $gaId = $gaIdMeta->getValue()) {
                $this->addExternalGaTracker($host->getSlug(), $gaId);
            }

            if (!$userIsHostAdmin) {
                $organization = $organizationsManager->getOrganizationById($request, $host->getOwnerId(), false, true);
                if ($organization->permissions->has(OrganizationsBaseRightsManager::RIGHT_ORG_HOSTS, Rights::USE)) {
                    $userIsHostAdmin = true;
                }
            }
        } else {
            if ($host->getOwnerId() == $request->user->id)
                $userIsHostAdmin = true;
        }


        $gameInstance = [];
        $gameInstanceRound = [];

        $activeHostInstance = [];

        $hostInstances = $this->hostsInstancesManager->getActivePrioritizedHostInstancesByHostId($request, $host->getPk(), true);

        foreach ($hostInstances as $hostInstance) {

            if (!$userIsHostAdmin)
                $userIsHostAdmin = $request->user->id == $hostInstance->getUserId();

            if ($activeGameInstance = $hostInstance->getActiveGameInstance()) {

                $activeGameInstanceRound = $gamesInstancesRoundsManager->getActiveGameInstanceRoundByGameInstanceId($request, $activeGameInstance->getPk());

                if ($activeGameInstanceRound || $controllerTypeSlug == GamesControllersTypesManager::SLUG_ADMIN) {
                    $gameInstance = $activeGameInstance;
                    $activeHostInstance = $hostInstance;
                    $gameInstanceRound = $activeGameInstanceRound;
                    break;
                }
            }
        }

        $pageDescription = 'ESC Controller';

        $pubNubKeys = [
            TemplateVars::PUBLISH_KEY => $request->config['pubnub']['publish_key'],
            TemplateVars::SUBSCRIBE_KEY => $request->config['pubnub']['subscribe_key'],
            TemplateVars::SSL => true
        ];

        $game = [];
        $gameBuild = [];
        $gameMod = [];
        $gameModBuild = [];

        $gameId = null;
        $gameBuildId = null;
        $gameModBuildId = null;
        $activationId = null;
        $controller = [];

        if (!$gameInstance) {

            $gameUpdateChannel = $request->get->readParam('gch', GamesManager::UPDATE_CHANNEL_LIVE);
            if (!$gamesManager->isValidUpdateChannel($gameUpdateChannel) || !$userIsHostAdmin)
                $gameUpdateChannel = GamesManager::UPDATE_CHANNEL_LIVE;

            $modUpdateChannel = $request->get->readParam('mch', GamesManager::UPDATE_CHANNEL_LIVE);
            if (!$gamesManager->isValidUpdateChannel($modUpdateChannel) || !$userIsHostAdmin)
                $modUpdateChannel = GamesManager::UPDATE_CHANNEL_LIVE;

            if ($activation = $activationsManager->getLiveRunningCloudActivationForHost($request, $host->getPk())) {

                $activationId = $activation->getPk();

                $game = $activation->getGame();
                $gameId = $game->getPk();

                if ($gameBuild = $game->getGameBuildByUpdateChannel($gameUpdateChannel)) {

                    $gameBuildId = $gameBuild->getPk();
                    if ($activation->getGameMod() && $gameModBuild = $activation->getGameMod()->getGameModBuildByUpdateChannel($modUpdateChannel)) {
                        $gameModBuildId = $gameModBuild->getPk();
                        $gameMod = $activation->getGameMod();
                    }
                }

            } else {
                if ($host->getOfflineGameId() && $game = $gamesManager->getGameById($request, $host->getOfflineGameId(), true)) {

                    $gameId = $game->getPk();

                    if ($gameBuild = $gamesBuildsManager->getActiveGameBuildByGameAndUpdateChannel($request, $game->getPk(), $gameUpdateChannel, true)) {

                        $gameBuildId = $gameBuild->getPk();

                        if ($host->getOfflineGameModId() && $gameMod = $gamesModsManager->getGameModByGameSlugAndPk($request, $game->getSlug(), $host->getOfflineGameModId())) {
                            if ($gameModBuild = $gameMod->getGameModBuildByUpdateChannel($modUpdateChannel)) {
                                $gameModBuildId = $gameModBuild->getPk();
                            }
                        }
                    }
                }
            }

            if ($gameBuild) {

                $gameInstance = $gamesInstancesManager->generateOfflineGameInstance(
                    $request,
                    $gameId,
                    $gameBuildId,
                    $gameModBuildId,
                    $host->getPubSubChannel(),
                    $activationId
                );

                $gameInstance->setGame($game);
                $gameInstance->setGameBuild($gameBuild);


                if ($gameMod) {
                    $gameInstance->setGameMod($gameMod);
                }

                if ($gameModBuild) {
                    $gameInstance->setGameModBuild($gameModBuild);
                }

            }
        }

        $gameInstancePlayerChannel = null;

        if ($gameInstance) {

            if (!$activeHostInstance)
                $activeHostInstance = $hostInstancesManager->generateOfflineHostInstance($request, $host);

            $customGameAssets = [];
            $customAssets = [];

            $gameBuild = $gameInstance->getGameBuild();


            // if there is a urlKey+1 e.g. https://play.esc.games/pigs/player or https://play.esc.games/pigs/spectator
            // evaluate rules and serve the appropriate controllerType
            if($controllerTypeSlug) {
                switch ($controllerTypeSlug) {
                    case GamesControllersTypesManager::SLUG_PLAYER : {
                        $controller = $gameBuild->getPlayerController();
                        break;
                    }
                    case GamesControllersTypesManager::SLUG_ADMIN : {
                        if(!$userIsHostAdmin) {
                            return $this->redirect($request->getPlayUrl(HOMEPAGE . $pageSlug));
                        }
                        $controller = $gameBuild->getAdminController();
                        break;
                    }
                    case GamesControllersTypesManager::SLUG_JOIN : {
                        $controller = $gameBuild->getJoinController();
                        break;
                    }
                    case GamesControllersTypesManager::SLUG_SPECTATOR : {
                        $controller = $gameBuild->getSpectatorController();
                        break;
                    }
                    case GamesControllersTypesManager::SLUG_CUSTOM : {
                        $controller = $gameBuild->getCustomController();
                        break;
                    }
                }

                if(!$controller) {
                    return $this->redirect($request->getPlayUrl(HOMEPAGE . $pageSlug));
                }
            }
            else {
                $controller = $gameBuild->getPlayerController();
            }

            if ($controller) {
                $baseHref = $controller->getUrl();
                if($request->get->readParam("latestSdk")) {
                    $sdkBuildsManager = $request->managers->sdkBuilds();
                    $sdkBuild = $sdkBuildsManager->getLatestSdkBuildBySdkPlatform($request, "unity" );
                    if($sdkBuild) {
                        $controller->replaceSdkAssets($sdkBuild);
                    }
                }
            }




            foreach ($gamesAssetsManager->getActivePublicCustomGameAssetsByGameBuild($request, $gameBuild->getGameId(), $gameBuild->getUpdateChannel(), $gameBuild->getPk()) as $customGameAsset) {
                $customGameAssets[$customGameAsset->getSlug()] = $customGameAsset->getPublicUrl();
                $customAssets[$customGameAsset->getSlug()] = [
                    DBField::ID => $customGameAsset->getCustomGameAssetId(),
                    DBField::URL => $customGameAsset->getPublicUrl(),
                    DBField::TYPE => $customGameAsset->getClass()
                ];
            }

            if ($gameModBuild = $gameInstance->getGameModBuild()) {
                // $gamesAssetsManager->getActivePublicCustomGameModAssetsByGameModBuildId($request, $gameModBuild->getPk(), $gameModBuild->getUpdateChannel()
                foreach ($gameModBuild->getCustomGameModBuildAssets() as $customGameModBuildAsset) {
                    if ($customGameModBuildAsset->is_public()) {
                        $customGameAssets[$customGameModBuildAsset->getSlug()] = $customGameModBuildAsset->getPublicUrl();
                        $customAssets[$customGameModBuildAsset->getSlug()] = [
                            DBField::ID => $customGameModBuildAsset->getCustomGameAssetId(),
                            DBField::URL => $customGameModBuildAsset->getPublicUrl(),
                            DBField::TYPE => $customGameModBuildAsset->getClass()
                        ];
                    }
                }
            }

            Modules::load_helper(Helpers::PUBNUB);
            $pubNubHelper = new PubNubHelper($request->user, $activeHostInstance, $gameInstance, $controllerTypeSlug);

            $activeHostInstance->updateField(TemplateVars::PUB_NUB_CONFIG, $pubNubKeys);

            $gameInstanceData = [
                VField::CUSTOM_GAME_ASSETS => $customGameAssets,
                VField::CUSTOM_ASSETS => $customAssets,
                VField::PUB_NUB_CONFIG => $pubNubKeys,
                VField::PUB_NUB_CHANNELS => $pubNubHelper->getJsonPlayerGameInstancePubNubChannels()
            ];

            $gameInstance->assign($gameInstanceData);

            $game = $gameInstance->getGame();
            $pageDescription = "{$game->getDisplayName()} Game Controller | {$request->settings()->getWebsiteName()}";

            $isAggregateGame = $game->is_aggregate_game();

            if (!$isAggregateGame && $gameBuild->is_aggregate_game())
                $isAggregateGame = true;

            if ($isAggregateGame && !$gameInstance->isShimmedInstance() && !$request->get->hasParam('disable-tracking')) {

                if ($gameInstanceRound) {
                    $gameInstanceRoundPlayer = $gamesInstancesRoundsPlayersManager->checkTrackGameInstanceRoundPlayer(
                        $request,
                        $gameInstanceRound->getPk(),
                        $request->user->session->getSessionId()
                    );
                }
            }
        }

        if ($request->user->is_authenticated()) {

            // Migrate unclaimed guest coins to user.
            if ($guestCoinsManager->checkGuestHasUnclaimedCoins($request, $request->user->guest->getGuestId())) {

                $conn = $request->db->get_connection(SQLN_SITE);

                $conn->begin();
                $userCoinsManager->migrateUnclaimedGuestCoins($request, $conn, $request->user->guest->getGuestId(), $request->user->id);
                $conn->commit();

            }

            $totalCoins = $userCoinsManager->getTotalUserCoins($request, $request->user->id, $host->getPk());

        } else {

            $totalCoins = $guestCoinsManager->getTotalGuestCoins($request, $request->user->guest->getGuestId(), $host->getPk());
        }

        $pageJsData = [
            TemplateVars::HOST_INSTANCE => $activeHostInstance ? $activeHostInstance->getJSONData(true) : [],
            TemplateVars::GAME_INSTANCE => $gameInstance ? $gameInstance->getJSONData() : [],
            TemplateVars::USER_IS_HOST_ADMIN => $userIsHostAdmin,
            TemplateVars::INVITE_HASH => $request->get->readParam(GetRequest::PARAM_INVITATION),
            TemplateVars::PUB_NUB_CONFIG => $pubNubKeys,
            TemplateVars::HOST => $host->getJSONData(),
            TemplateVars::GAME_CONTROLLER => $controller ? $controller->getJSONData() : null,
            TemplateVars::USER_STATS => [
                TemplateVars::TOTAL_COINS => $totalCoins
            ]
        ];


        $viewData = [
            TemplateVars::PAGE_DESCRIPTION => $pageDescription,
            TemplateVars::PAGE_TITLE => $pageDescription,
            TemplateVars::PAGE_IDENTIFIER => 'play',
            TemplateVars::PAGE_CANONICAL => $request->path,
            TemplateVars::CONTROLLER => [],
            TemplateVars::USER_IS_HOST_ADMIN => $userIsHostAdmin,
            TemplateVars::BASE_HREF => $baseHref,
            TemplateVars::CONTROLLER => $controller,
        ];

        return $this->assignPageJsViewData($pageJsData)->renderAjaxResponse($request, $viewData, 'controller.twig');
    }

    /**
     * Homepage.
     * 
     * @param Request $request
     * @return HtmlResponse|HttpResponseRedirect|JSONResponse
     */
    protected function index(Request $request)
    {
        $hostsManager = $request->managers->hosts();
        $hostsInstancesManager = $request->managers->hostsInstances();

        $fields = [
            new SlugField(DBField::SLUG, '', 32, true, null, null, null, true)
        ];

        $form = new PostForm($fields, $request);

        $form->setTemplateFile('play-form.twig');

        if ($isValid = $form->is_valid()) {
            $slug = trim(strtolower($form->getCleanedValue(DBField::SLUG)));

            $host = $hostsManager->getHostBySlug($request, $slug);

            if (!$host) {
                $form->set_error('Code Not Found', DBField::SLUG);
                $isValid = false;
            } else {

                $next = $host->getUrl();
            }

            if ($isValid) {
                return $form->handleRenderJsonSuccessResponse($next);
            } else {
                return $form->handleRenderJsonErrorResponse();
            }

        } else {
            if ($request->is_post())
                return $form->handleRenderJsonErrorResponse();
        }

        $viewData = [
            TemplateVars::PAGE_TITLE => 'Play ESC Games',
            TemplateVars::PAGE_IDENTIFIER => 'play-homepage',
            TemplateVars::FORM => $form,
        ];


        return $this->renderPageResponse($request, $viewData, 'index.twig');
    }

    /**
     * @param Request $request
     * @return GameControllerAssetResponse|HtmlResponse
     */
    protected function handle_game_controller(Request $request)
    {
        $gamesControllersManager = $request->managers->gamesControllers();

        $userCoinsManager = $request->managers->userCoins();
        $guestCoinsManager = $request->managers->guestCoins();

        $gamesManager = $request->managers->games();
        $gamesModsManager = $request->managers->gamesMods();
        $gamesBuildsManager = $request->managers->gamesBuilds();
        $gamesAssetsManager = $request->managers->gamesAssets();
        $gamesInstancesManager = $request->managers->gamesInstances();
        $hostInstancesManager = $request->managers->hostsInstances();
        $activationsManager = $request->managers->activations();
        $gamesInstancesRoundsManager = $request->managers->gamesInstancesRounds();
        $gamesInstancesRoundsPlayersManager = $request->managers->gamesInstancesRoundsPlayers();

        if (!$gameControllerId = $request->getIdForEntity($this->url_key+1))
            return $this->render_404($request, 'Controller ID Not Found');

        if (!$gameController = $gamesControllersManager->getGameControllerById($request, $gameControllerId))
            return $this->render_404($request, 'Controller Not Found');

        if (!$gameController->getGameControllerAssets())
            return $this->render_404($request, 'No Controller Assets');

        // If this is a controller asset request, handle that response separately.
        if ($request->getSlugForEntity($this->url_key+2))
            return $this->handle_game_controller_asset($request, $gameController);

        // Controller Preview only works for logged in users
        if (!$request->user->is_authenticated())
            return $this->render_404($request, 'Controller Preview Not Available');

        $bootstrapHostData = [
            DBField::HOST_ID => -1,
            DBField::OWNER_TYPE_ID => EntityType::USER,
            DBField::OWNER_ID => $request->user->id,
            DBField::DISPLAY_NAME => "Preview Controller Host - {$request->user->username}",
            DBField::IS_PROD => false,
            DBField::SLUG => "userId-{$request->user->id}.".uuidV4(),
            DBField::HAS_CUSTOM_SLUG => false,
            DBField::OFFLINE_GAME_ID => null,
            DBField::IS_ACTIVE => 1,
        ];

        /** @var HostEntity $host */
        $host = $this->hostsManager->createEntity($bootstrapHostData, $request);

        $gameBuild = $gamesBuildsManager->getGameBuildById($request, $gameController->getGameBuildId(), $gameController->getGameId(), true);
        $game = $gamesManager->getGameById($request, $gameBuild->getGameId());

        $game->updateField(DBField::IS_WAN_ENABLED, 0)->updateField(DBField::IS_AGGREGATE_GAME, 0);

        $userIsHostAdmin = true;

        $hostInstance = $hostInstancesManager->generateOfflineHostInstance($request, $host);

        $pubNubKeys = [
            TemplateVars::PUBLISH_KEY => $request->config['pubnub']['publish_key'],
            TemplateVars::SUBSCRIBE_KEY => $request->config['pubnub']['subscribe_key'],
            TemplateVars::SSL => true
        ];

        $gameInstance = $gamesInstancesManager->generateOfflineGameInstance(
            $request,
            $gameController->getGameId(),
            $gameController->getGameBuildId(),
            null, // Mod Build Id
            $host->getPubSubChannel(),
            null // Activation Id
        );

        $gameInstance->setGame($game);
        $gameInstance->setGameBuild($gameBuild);

        $customGameAssets = [];
        $customAssets = [];

        foreach ($gamesAssetsManager->getActivePublicCustomGameAssetsByGameBuild($request, $gameBuild->getGameId(), $gameBuild->getUpdateChannel(), $gameBuild->getPk()) as $customGameAsset) {
            $customGameAssets[$customGameAsset->getSlug()] = $customGameAsset->getPublicUrl();
            $customAssets[$customGameAsset->getSlug()] = [
                DBField::ID => $customGameAsset->getCustomGameAssetId(),
                DBField::URL => $customGameAsset->getPublicUrl(),
                DBField::TYPE => $customGameAsset->getClass()
            ];
        }

        Modules::load_helper(Helpers::PUBNUB);
        $pubNubHelper = new PubNubHelper($request->user, $hostInstance, $gameInstance, $gameController->getGameControllerType()->getSlug());

        $hostInstance->updateField(TemplateVars::PUB_NUB_CONFIG, $pubNubKeys);

        $gameInstanceData = [
            VField::CUSTOM_GAME_ASSETS => $customGameAssets,
            VField::CUSTOM_ASSETS => $customAssets,
            VField::PUB_NUB_CONFIG => $pubNubKeys,
            VField::PUB_NUB_CHANNELS => $pubNubHelper->getJsonPlayerGameInstancePubNubChannels()
        ];

        $gameInstance->assign($gameInstanceData);

        $pageDescription = "{$game->getDisplayName()} Game Controller | {$request->settings()->getWebsiteName()}";

        // Migrate unclaimed guest coins to user.
        if ($guestCoinsManager->checkGuestHasUnclaimedCoins($request, $request->user->guest->getGuestId())) {

            $conn = $request->db->get_connection(SQLN_SITE);

            $conn->begin();
            $userCoinsManager->migrateUnclaimedGuestCoins($request, $conn, $request->user->guest->getGuestId(), $request->user->id);
            $conn->commit();

        }

        $totalCoins = $userCoinsManager->getTotalUserCoins($request, $request->user->id, $host->getPk());

        $pageJsData = [
            TemplateVars::HOST_INSTANCE => $hostInstance->getJSONData(true),
            TemplateVars::GAME_INSTANCE => $gameInstance->getJSONData(),
            TemplateVars::USER_IS_HOST_ADMIN => $userIsHostAdmin,
            TemplateVars::INVITE_HASH => $request->get->readParam(GetRequest::PARAM_INVITATION),
            TemplateVars::PUB_NUB_CONFIG => $pubNubKeys,
            TemplateVars::HOST => $host->getJSONData(),
            TemplateVars::GAME_CONTROLLER => $gameController->getJSONData(),
            TemplateVars::USER_STATS => [
                TemplateVars::TOTAL_COINS => $totalCoins
            ]
        ];

        $viewData = [
            TemplateVars::PAGE_DESCRIPTION => $pageDescription,
            TemplateVars::PAGE_TITLE => $pageDescription,
            TemplateVars::PAGE_IDENTIFIER => 'preview-controller',
            TemplateVars::PAGE_CANONICAL => $request->path,
            TemplateVars::CONTROLLER => [],
            TemplateVars::USER_IS_HOST_ADMIN => $userIsHostAdmin,
            TemplateVars::BASE_HREF => $gameController->getUrl(),
            TemplateVars::CONTROLLER => $gameController,
        ];

        return $this->assignPageJsViewData($pageJsData)->renderAjaxResponse($request, $viewData, 'controller.twig');
    }

    /**
     * @param Request $request
     * @param GameControllerEntity $gameController
     * @return GameControllerAssetResponse|HtmlResponse
     */
    protected function handle_game_controller_asset(Request $request, GameControllerEntity $gameController)
    {
        $urlParts = $request->url;
        unset($urlParts[0]);
        unset($urlParts[1]);
        unset($urlParts[2]);

        // Hack to make CSS relative images work
        if ($urlParts[3] == 'css' && $urlParts[4] == 'images')
            unset($urlParts[3]);

        $assetPath = join('/', $urlParts);

        $assetPath = rtrim($assetPath, '/');

        if ($gameAsset = $gameController->getGameAssetByPath($assetPath)) {

            if ($request->readHeader('if-none-match') == $gameAsset->getMd5()) {
                return new GameControllerAssetResponse($gameAsset, '', HttpResponse::HTTP_NOT_MODIFIED, $gameAsset->getMd5());
            }

            $gameAssetFile = $request->s3->readIntoMemory($gameAsset->getBucket(), $gameAsset->getBucketKey());

            return new GameControllerAssetResponse($gameAsset, $gameAssetFile, HttpResponse::HTTP_OK, $gameAsset->getMd5());
        }

        return $this->render_404($request, 'Asset Not Found');
    }

    /**
     * @param Request $request
     * @param HostControllerEntity $hostController
     * @return HtmlResponse|HostControllerAssetResponse
     * @throws BaseEntityException
     */
    protected function handle_host_asset(Request $request, HostControllerEntity $hostController)
    {
        $urlParts = $request->url;
        unset($urlParts[0]);
        unset($urlParts[1]);
        unset($urlParts[2]);

        // If GameInstanceId is part of the route, remove it from the route parts.
        $urlKey = 3;

        // Hack to make CSS relative images work
        if ($urlParts[$urlKey] == 'css' && $urlParts[$urlKey+1] == 'images')
            unset($urlParts[$urlKey]);

        $assetPath = join('/', $urlParts);

        $assetPath = rtrim($assetPath, '/');

        if ($hostControllerAsset = $hostController->getHostAssetByPath($assetPath)) {

            // Set ETag for Cache Validation with CloudFlare, and return empty 304 if ETag is passed in request header.
            if ($request->readHeader('if-none-match') == $hostControllerAsset->getMd5()) {
                return new HostControllerAssetResponse($hostControllerAsset, '', HttpResponse::HTTP_NOT_MODIFIED, $hostControllerAsset->getMd5());
            }


            $hostAssetFile = $request->s3->readIntoMemory($hostControllerAsset->getBucket(), $hostControllerAsset->getBucketKey());

            return new HostControllerAssetResponse($hostControllerAsset, $hostAssetFile, HttpResponse::HTTP_OK, $hostControllerAsset->getMd5());
        }

        return $this->render_404($request);
    }

    /**
     * @param Request $request
     * @return GameAssetResponse|HtmlResponse
     */
    protected function handle_custom_game_assets(Request $request)
    {
        $gamesManager = $request->managers->games();

        $gamesAssetsManager = $request->managers->gamesAssets();
        $gamesActiveCustomAssetsManager = $request->managers->gamesActiveCustomAssets();

        $gameId = $request->getIdForEntity($this->url_key + 1);
        $updateChannel = $request->getSlugForEntity($this->url_key + 2);
        $customGameAssetSlug = $request->getSlugForEntity($this->url_key + 3);
        $customGameAssetId = $request->getIdForEntity($this->url_key + 4);
        $gameBuildId = $request->getIdForEntity($this->url_key + 5);

        if (!$gameId || !$updateChannel || !$customGameAssetSlug || !$customGameAssetId || !$gameBuildId)
            return $this->render_404($request, 'Missing Parameters');

        if (!$gamesManager->isValidUpdateChannel($updateChannel))
            return $this->render_404($request, 'Update Channel Invalid');

        $activeCustomGameAsset = $gamesActiveCustomAssetsManager->getPublicGameActiveCustomAssetLinkByGameIdAndSlug(
            $request,
            $gameId,
            $updateChannel,
            $customGameAssetSlug,
            $gameBuildId
        );

        if (!$activeCustomGameAsset)
            return $this->render_404($request, 'Active Asset Not Found');

        $customGameAsset = $gamesAssetsManager->getCustomGameAssetByCustomGameAssetId(
            $request,
            $gameId,
            $customGameAssetId,
            $updateChannel
        );

        if (!$customGameAsset)
            return $this->render_404($request, 'Asset Not Found');

        // Set ETag for Cache Validation with CloudFlare, and return empty 304 if ETag is passed in request header.
        if ($request->readHeader('if-none-match') == $customGameAsset->getMd5()) {
            return new GameAssetResponse($customGameAsset, '', HttpResponse::HTTP_NOT_MODIFIED, false, $customGameAsset->getMd5());
        }

        // Read file from AWS.
        try {
            $customGameAssetFile = $request->s3->readIntoMemory($customGameAsset->getBucket(), $customGameAsset->getBucketKey());

            return new GameAssetResponse($customGameAsset, $customGameAssetFile, HttpResponse::HTTP_OK, false, $customGameAsset->getMd5());
        } catch (Exception $e) {
            return $this->render_404($request);
        }

    }

    /**
     * @param Request $request
     * @return GameAssetResponse|HtmlResponse
     */
    protected function handle_custom_game_mod_assets(Request $request)
    {
        $gamesManager = $request->managers->games();

        $gamesAssetsManager = $request->managers->gamesAssets();
        $gamesModsActiveCustomAssetsManager = $request->managers->gamesModsActiveCustomAssets();

        $gameId = $request->getIdForEntity($this->url_key + 1);
        $updateChannel = $request->getSlugForEntity($this->url_key + 2);
        $customGameAssetSlug = $request->getSlugForEntity($this->url_key + 3);
        $customGameAssetId = $request->getIdForEntity($this->url_key + 4);
        $gameModBuildId = $request->getIdForEntity($this->url_key + 5);

        if (!$gameId || !$updateChannel || !$customGameAssetSlug || !$customGameAssetId || !$gameModBuildId)
            return $this->render_404($request, 'Missing Parameters');

        if (!$gamesManager->isValidUpdateChannel($updateChannel))
            return $this->render_404($request, 'Update Channel Invalid');

        $activeCustomGameAsset = $gamesModsActiveCustomAssetsManager->getPublicGameModActiveCustomAssetLinkByGameModBuildIdAndSlug(
            $request,
            $gameModBuildId,
            $customGameAssetSlug
        );

        if (!$activeCustomGameAsset)
            return $this->render_404($request, 'Active Asset Not Found');

        $customGameAsset = $gamesAssetsManager->getCustomGameModBuildAssetByCustomGameAssetId(
            $request,
            $gameModBuildId,
            $customGameAssetId
        );

        if (!$customGameAsset)
            return $this->render_404($request, 'Asset Not Found');

        // Set ETag for Cache Validation with CloudFlare, and return empty 304 if ETag is passed in request header.
        if ($request->readHeader('if-none-match') == $customGameAsset->getMd5()) {
            return new GameAssetResponse($customGameAsset, '', HttpResponse::HTTP_NOT_MODIFIED, false, $customGameAsset->getMd5());
        }

        // Read file from AWS.
        try {
            $customGameAssetFile = $request->s3->readIntoMemory($customGameAsset->getBucket(), $customGameAsset->getBucketKey());

            return new GameAssetResponse($customGameAsset, $customGameAssetFile, HttpResponse::HTTP_OK, false, $customGameAsset->getMd5());
        } catch (Exception $e) {
            return $this->render_404($request);
        }
    }

    /**
     * @param Request $request
     * @return HtmlResponse|SdkAssetResponse
     */
    public function handle_sdk_asset(Request $request)
    {
        $sdkBuildManager = $request->managers->sdkBuilds();

        if (!$sdkBuildId = $request->getIdForEntity($this->url_key+1))
            return $this->render_404($request, 'SDK Build ID Not Found');

        $sdkBuild = $sdkBuildManager->getSdkBuildById($request, $sdkBuildId, true);

        if (!$sdkBuild)
            return $this->render_404($request, 'SDK Build Not Found');

        if (!$sdkFileName = $request->getSlugForEntity($this->url_key+2))
            return $this->render_404($request, 'SDK File Name Not Found');

        $sdkBuildAsset = null;

        foreach ($sdkBuild->getSdkAssets() as $sdkAsset) {
            if($sdkAsset->getFileName() == $sdkFileName) {
                $sdkBuildAsset = $sdkAsset;
            }
        }

        if(!$sdkBuildAsset) {
            return $this->render_404($request, 'SDK Build Asset Not Found');
        }

        // Set ETag for Cache Validation with CloudFlare, and return empty 304 if ETag is passed in request header.
        if ($request->readHeader('if-none-match') == $sdkBuildAsset->getMd5()) {
            return new SdkAssetResponse($sdkBuildAsset, '', false, HttpResponse::HTTP_NOT_MODIFIED);
        }

        try {

            $sdkAssetFile = $request->s3->readIntoMemory($sdkBuildAsset->getBucket(), $sdkBuildAsset->getBucketKey());

            return new SdkAssetResponse($sdkBuildAsset, $sdkAssetFile, false);

        } catch (\Aws\Exception\AwsException $e) {
            return $this->render_404($request);
        }
    }



    /**
     * @param Request $request
     * @return HtmlResponse
     */
    public function node_ping(Request $request)
    {
        $viewData = [
            TemplateVars::PAGE_IDENTIFIER => 'node-ping',
            TemplateVars::PAGE_TITLE => 'Ping Test',
            TemplateVars::PAGE_DESCRIPTION => 'Testing things!'
        ];

        return $this->renderPageResponse($request, $viewData, 'node-ping.twig');
    }
}

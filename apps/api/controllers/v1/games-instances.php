<?php
/**
 * Created by PhpStorm.
 * User: christophercarter
 * Date: 6/7/18
 * Time: 12:35 PM
 */

class GamesInstancesApiV1Controller extends BaseApiV1Controller implements BaseApiControllerV1CRUDInterface {

    const REQUIRES_POST = true;
    const REQUIRES_AUTH = true;

    /** @var GamesInstancesManager $manager */
    protected $manager;
    /** @var GamesManager $gamesManager */
    protected $gamesManager;
    /** @var ActivityManager $activityManager */
    protected $activityManager;
    /** @var GamesBuildsManager $gamesBuildsManager */
    protected $gamesBuildsManager;
    /** @var GamesInstancesRoundsManager $gamesInstancesRoundsManager */
    protected $gamesInstancesRoundsManager;
    /** @var GamesInstancesRoundsPlayersManager $gamesInstancesRoundsPlayersManager */
    protected $gamesInstancesRoundsPlayersManager;
    /** @var GamesInstancesRoundsEventsManager $gamesInstancesRoundsEventsManager */
    protected $gamesInstancesRoundsEventsManager;
    /** @var HostsManager $hostsManager */
    protected $hostsManager;
    /** @var HostsInstancesManager $hostsInstancesManager */
    protected $hostsInstancesManager;
    /** @var SmsManager $smsManager */
    protected $smsManager;
    /** @var UsersManager $usersManager */
    protected $usersManager;
    /** @var ShortUrlsManager $shortUrlsManager */
    protected $shortUrlsManager;
    /** @var SessionTrackingManager $sessionsTrackingManager */
    protected $sessionsTrackingManager;

    /** @var OrganizationsManager $organizationsManager*/
    protected $organizationsManager;
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

        // Actions
        'ping' => 'handle_ping',
        'stop' => 'handle_stop',
        'send-sms-admin' => 'handle_send_sms_admin',

        // Rounds
        'start-game-round' => 'handle_start_game_round',
        'stop-game-round' => 'handle_stop_game_round',

        // Players
        'add-game-round-player' => 'handle_add_game_round_player',
        'remove-game-round-player' => 'handle_remove_game_round_player',

        // Events
        'track-event' => 'handle_track_event'
    ];

    /**
     * @param Request $request
     */
    protected function pre_handle(Request $request)
    {
        $this->activityManager = $request->managers->activity();
        $this->gamesManager = $request->managers->games();
        $this->gamesBuildsManager = $request->managers->gamesBuilds();
        $this->gamesInstancesRoundsManager = $request->managers->gamesInstancesRounds();
        $this->gamesInstancesRoundsEventsManager = $request->managers->gamesInstancesRoundsEvents();
        $this->gamesInstancesRoundsPlayersManager = $request->managers->gamesInstancesRoundsPlayers();
        $this->hostsManager = $request->managers->hosts();
        $this->hostsInstancesManager = $request->managers->hostsInstances();
        $this->smsManager = $request->managers->sms();
        $this->usersManager = $request->managers->users();
        $this->shortUrlsManager = $request->managers->shortUrls();
        $this->sessionsTrackingManager = $request->managers->sessionTracking();
        $this->organizationsActivityManager = $request->managers->organizationsActivities();
        $this->organizationsManager = $request->managers->organizations();
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
            $gameInstance = $this->manager->getGameInstanceById($request, $this->form->getPkValue(), $this->form->getExpand());
            $this->setResults($gameInstance);
        }

        return $this->renderApiV1Response($request);
    }

    /**
     * @param Request $request
     * @return ApiV1Response
     */
    public function handle_create(Request $request): ApiV1Response
    {
        $fields = $this->manager->getFormFields();
        $defaults = [];

        $this->form = new ApiV1PostForm($fields, $request, $defaults);

        if ($this->form->is_valid()) {

            $gameId = $this->form->getCleanedValue(DBField::GAME_ID);

            $game = $this->gamesManager->getGameById($request, $gameId);

            $gameBuildId = $this->form->getCleanedValue(DBField::GAME_BUILD_ID);
            $gameModBuildId = $this->form->getCleanedValue(DBField::GAME_MOD_BUILD_ID);
            $activationId = $this->form->getCleanedValue(DBField::ACTIVATION_ID);
            $hostInstanceId = $this->form->getCleanedValue(DBField::HOST_INSTANCE_ID);
            $startTime = $request->getCurrentSqlTime();
            $notifySms = $this->form->getCleanedValue(VField::NOTIFY_SMS, 0);

            $gameBuild = $this->gamesBuildsManager->getGameBuildById($request, $gameBuildId, $gameId);

            $gameInstance = $this->manager->createNewGameInstance(
                $request,
                $gameId,
                $gameBuildId,
                $hostInstanceId,
                1,
                20,
                $gameModBuildId,
                $activationId,
                $startTime
            );
            $game->setGameBuild($gameBuild);
            $gameInstance->setGame($game);
            $gameInstance->setGameBuild($gameBuild);

            $pubNubConfig = [
                TemplateVars::PUBLISH_KEY => $request->config['pubnub']['publish_key'],
                TemplateVars::SUBSCRIBE_KEY => $request->config['pubnub']['subscribe_key'],
                TemplateVars::SSL => true
            ];

            $gameInstance->updateField(TemplateVars::PUB_NUB_CONFIG, $pubNubConfig);

            $hostInstance = $this->hostsInstancesManager->getSlimHostInstanceById($request, $hostInstanceId);
            $host = $hostInstance->getHost();

            $gameInstance->updateField(VField::URL, $host->getUrl());
            $hostInstance->updateField(VField::URL, $host->getUrl());

            $activity = $this->activityManager->trackActivity(
                $request,
                ActivityTypesManager::ACTIVITY_TYPE_USER_START_GAME_INSTANCE,
                $gameId,
                $gameInstance->getPk(),
                $request->user->getEntity()->getUiLanguageId(),
                $request->user->getEntity()
            );

            if ($host->getOwnerTypeId() == EntityType::ORGANIZATION) {
                $activeOrganization = $this->organizationsManager->getOrganizationById($request, $host->getOwnerId(), true, true);
                $this->organizationsActivityManager->trackOrganizationActivity(
                    $request,
                    $activity,
                    $activeOrganization,
                    $activeOrganization->getOrganizationUserByUserId($request->user->getId())
                );
            }

            $user = $request->user->getEntity();

            $hostAdminWindowExpiration = strtotime($request->getCurrentSqlTime()) + (ONE_HOUR * 5);

            $hostAdminWindowLoginUrlParams = $this->usersManager->generateMagicLoginUrlParamsForUser($request, $user, $hostAdminWindowExpiration, $gameInstance->getAdminUrl());

            $hostAdminWindowTrackingUrlParams = [
                GetRequest::PARAM_UTM_MEDIUM => 'host-app',
                GetRequest::PARAM_UTM_SOURCE => 'game-instance',
                GetRequest::PARAM_UTM_CAMPAIGN => $gameInstance->getPk(),
                GetRequest::PARAM_UTM_TERM => 'admin-controller'
            ];

            $hostAdminWindowQueryParamString = $request->buildQuery(array_merge($hostAdminWindowLoginUrlParams, $hostAdminWindowTrackingUrlParams));

            $gameInstance->updateField(VField::ADMIN_URL, $gameInstance->getAdminUrl() . $hostAdminWindowQueryParamString);

            if ($game->is_wan_enabled()) {

                $hostInstance->setGameInstance($gameInstance);

                Modules::load_helper(Helpers::PUBNUB);

                $pubNubHelper = new PubNubHelper($request->user, $hostInstance, $gameInstance);

                $gameInstance->updateField(VField::PUB_NUB_CHANNELS, $pubNubHelper->getHostGameInstancePubNubChannels());
            }

            if ($user->getPhoneNumber() && $notifySms) {

                $expiration = strtotime($request->getCurrentSqlTime()) + FIFTEEN_MINUTES;

                $loginUrlParams = $this->usersManager->generateMagicLoginUrlParamsForUser($request, $user, $expiration, $gameInstance->getUrl());

                $trackingUrlParams = [
                    GetRequest::PARAM_UTM_MEDIUM => 'sms',
                    GetRequest::PARAM_UTM_SOURCE => 'game-instance',
                    GetRequest::PARAM_UTM_CAMPAIGN => $gameInstance->getPk(),
                    GetRequest::PARAM_UTM_TERM => 'admin-login'
                ];

                $queryParamString = $request->buildQuery(array_merge($loginUrlParams, $trackingUrlParams));

                $shortUrl = $this->shortUrlsManager->createNewShortUrl($request, $host->getUrl($queryParamString));

                $gameInstanceMessage = "To play {$game->getDisplayName()}, please click this link: {$shortUrl->getUrl()}";

                try {
                    $this->smsManager->scheduleSms(
                        $request,
                        SmsTypesManager::ID_GAME_INSTANCE_ADMIN,
                        $user->getPhoneNumber(),
                        $gameInstanceMessage,
                        null,
                        $request->user->id
                    );

                } catch (Net_Gearman_Exception $e) {
                    // We have to catch this error to prevent the endpoint from failing if the tasksworker is not
                    // available to handle an sms job at current.

                    // It will pick up the job when it comes back online automatically.
                }
            }

            /**
             * Board Members Hack
             */

//            if ($user->getPk() == 7) {
//                Modules::load_helper('boardmeeting');
//                $boardMeetingHelper = new BoardMeetingHelper($game, $gameInstance, $user);
//                $boardMeetingHelper->sendGameInvitations($request);
//            }

            $this->setResults($gameInstance);
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

            $fields = $this->manager->getFormFields();

            $gameInstance = $this->manager->getGameInstanceById($request, $getEntityForm->getPkValue());

            $this->form = new ApiV1PostForm($fields, $request, $gameInstance);

            if ($this->form->is_valid()) {
                $gameInstance->assignByForm($this->form)->saveEntityToDb($request);
                $this->setResults($gameInstance);
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
        $getEntityForm = $this->buildGetEntityForm($request);

        if ($getEntityForm->is_valid()) {

            $gameInstance = $this->manager->getGameInstanceById($request, $getEntityForm->getPkValue());

            $this->manager->deactivateEntity($request, $gameInstance);

            $this->setResults($gameInstance);

        } else {
            $this->form = $getEntityForm;
        }

        return $this->renderApiV1Response($request);
    }

    /**
     * @param Request $request
     * @return ApiV1Response
     */
    public function handle_ping(Request $request) : ApiV1Response
    {
        $this->form = $this->buildGetEntityForm($request);

        if ($this->form->is_valid()) {
            $gameInstance = $this->manager->getGameInstanceById($request, $this->form->getPkValue());

            if ($gameInstance) {
                $gameInstance->updateField(DBField::LAST_PING_TIME, $request->getCurrentSqlTime())->saveEntityToDb($request);
            }
            $this->setResults($gameInstance);
        }

        return $this->renderApiV1Response($request);
    }

    /**
     * @param Request $request
     * @return ApiV1Response
     */
    public function handle_stop(Request $request) : ApiV1Response
    {
        $this->form = $this->buildGetEntityForm($request);

        if ($this->form->is_valid()) {
            $gameInstance = $this->manager->getGameInstanceById($request, $this->form->getPkValue());

            if ($gameInstance && !$gameInstance->has_ended()) {

                $this->manager->stopGameInstance($request, $gameInstance);
            }

            $this->setResults($gameInstance);
        }

        return $this->renderApiV1Response($request);
    }

    /**
     * Sends an SMS with magic link to the game instance to the admin of a game
     * @param Request $request
     * @return ApiV1Response
     */
    public function handle_send_sms_admin(Request $request) : ApiV1Response
    {
        $this->form = $this->buildGetEntityForm($request);

        if ($this->form->is_valid()) {
            $user = $request->user->getEntity();
            $gameInstance = $this->manager->getGameInstanceById($request, $this->form->getPkValue(), true);
            $hostInstance = $this->hostsInstancesManager->getHostInstanceById($request, $gameInstance->getHostInstanceId());
            $host = $hostInstance->getHost();
            $game = $gameInstance->getGame();

            if ($user->getPhoneNumber()) {
                $expiration = strtotime($request->getCurrentSqlTime()) + FIFTEEN_MINUTES;

                $loginUrlParams = $this->usersManager->generateMagicLoginUrlParamsForUser($request, $user, $expiration, $host->getUrl());

                $trackingUrlParams = [
                    GetRequest::PARAM_UTM_MEDIUM => 'sms',
                    GetRequest::PARAM_UTM_SOURCE => 'game-instance',
                    GetRequest::PARAM_UTM_CAMPAIGN => $gameInstance->getPk(),
                    GetRequest::PARAM_UTM_TERM => 'admin-controller-sms-request'
                ];

                $queryParamString = $request->buildQuery(array_merge($loginUrlParams, $trackingUrlParams));

                $shortUrl = $this->shortUrlsManager->createNewShortUrl($request, $host->getUrl($queryParamString));

                $gameInstanceMessage = "To play {$game->getDisplayName()}, please click this link: {$shortUrl->getUrl()}";

                try {
                    $sms = $this->smsManager->scheduleSms(
                        $request,
                        SmsTypesManager::ID_GAME_INSTANCE_ADMIN,
                        $user->getPhoneNumber(),
                        $gameInstanceMessage,
                        null,
                        $request->user->id
                    );

                    $this->setResults($sms);
                } catch (Net_Gearman_Exception $e) {
                    $this->setResults([]);
                    // We have to catch this error to prevent the endpoint from failing if the tasksworker is not
                    // available to handle an sms job at current.

                    // It will pick up the job when it comes back online automatically.
                }
            }
        }

        return $this->renderApiV1Response($request);
    }

    /**
     * @param Request $request
     * @return ApiV1Response
     */
    public function handle_start_game_round(Request $request) : ApiV1Response
    {
        $fields = $this->gamesInstancesRoundsManager->getFormFields(false);

        $this->form = $this->buildGetEntityForm($request, $fields);

        if ($this->form->is_valid()) {

            $gameInstanceId = $this->form->getCleanedValue(DBField::GAME_INSTANCE_ID);
            $startTime = $this->form->getCleanedValue(DBField::START_TIME, $request->getCurrentSqlTime());

            $gameInstanceRound = $this->gamesInstancesRoundsManager->getActiveGameInstanceRoundByGameInstanceId($request, $gameInstanceId, true);
            if ($gameInstanceRound) {
                $this->gamesInstancesRoundsManager->stopGameInstanceRound($request, $gameInstanceRound);
            }

            $newGameInstanceRound = $this->gamesInstancesRoundsManager->createNewGameInstanceRound($request, $gameInstanceId, $startTime);

            $gameInstance = $this->manager->getGameInstanceById($request, $newGameInstanceRound->getGameInstanceId());
            $game = $this->gamesManager->getGameById($request, $gameInstance->getGameId());
            $gameBuild = $this->gamesBuildsManager->getGameBuildById($request, $gameInstance->getGameBuildId(), $gameInstance->getGameId());
            $gameInstance->setGameBuild($gameBuild);
            $game->setGameBuild($gameBuild);
            $gameInstance->setGame($game);

            if ($game->is_wan_enabled()) {

                $hostInstance = $this->hostsInstancesManager->getSlimHostInstanceById($request, $gameInstance->getHostInstanceId(), false, false);
                $hostInstance->setGameInstance($gameInstance);

                Modules::load_helper(Helpers::PUBNUB);

                $pubNubHelper = new PubNubHelper($request->user, $hostInstance, $gameInstance);

                $gameInstance->updateField(VField::PUB_NUB_CHANNELS, $pubNubHelper->getHostGameInstancePubNubChannels());

                $pubnub = PubNubLoader::getPubNubInstance($request);
                $pubNubChannel = $pubNubHelper->getHostGameInstancePubNubChannels()[PubNubHelper::CHANNEL_OFFLINE_BROADCAST];

                $hostInstances = $this->hostsInstancesManager->getActivePrioritizedHostInstancesByHostId($request, $hostInstance->getHostId(), true);

                $lbeHostInstance = [];
                $cloudHostInstance = [];

                foreach ($hostInstances as $activeHostInstance) {
                    if ($activeHostInstance->is_type_esc_host_app()) {
                        $lbeHostInstance = $activeHostInstance;
                    } else {
                        $cloudHostInstance = $activeHostInstance;
                    }
                }

                // If there's a LBE host instance running and this is a cloud round, don't publish offline data.
                if ($lbeHostInstance && $cloudHostInstance && ($cloudHostInstance->getPk() == $hostInstance->getPk())) {
                    $shouldUpdateOfflineData = false;
                } else {
                    $shouldUpdateOfflineData = true;
                }

                if ($shouldUpdateOfflineData) {

                    $hostAndGameStatus = [
                        'host_running' => true,
                        'game_running' => true,
                        'next_url' => $hostInstance->getHost()->getUrl(),
                        'game_instance_id' => $gameInstance->getPk(),
                        'game_instance_round_id' => $newGameInstanceRound->getPk()
                    ];
                    PubNubApiHelper::updateOfflineGameData($pubnub, $pubNubChannel->getChannelName(), $hostAndGameStatus);
                }

                // Commented out for now until we're ready to use on production.
                $issueGrants = false;
                if ($issueGrants) {
                    $pubnub = PubNubLoader::getPubNubInstance($request);

                    $channelsAuthed = true;

                    foreach ($pubNubHelper->getHostGameInstancePubNubChannels() as $channelKey => $pubNubChannel) {
                        if ($pubNubChannel && $channelKey != PubNubHelper::CHANNEL_PLAYER_DIRECT) {
                            $channelsAuthed = $pubNubHelper->issueGrant($pubnub, $pubNubChannel);
                            if (!$channelsAuthed)
                                break;
                        }
                    }

                    if ($channelsAuthed) {
                        foreach ($pubNubHelper->getPlayerGameInstancePubNubChannels() as $channelKey => $pubNubChannel) {
                            if ($channelKey != PubNubHelper::CHANNEL_PLAYER_DIRECT) {
                                $channelsAuthed = $pubNubHelper->issueGrant($pubnub, $pubNubChannel);
                                if (!$channelsAuthed)
                                    break;
                            }
                        }
                    }
                }
            }

            $this->setResults($newGameInstanceRound);
        }

        return $this->renderApiV1Response($request);
    }

    /**
     * @param Request $request
     * @return ApiV1Response
     */
    public function handle_stop_game_round(Request $request) : ApiV1Response
    {
        $fields = [
            new IntegerField(DBField::ID, 'Primary Key'),
            new IntegerField(DBField::GAME_INSTANCE_ID, 'Game Instance ID'),
            new DateTimeField(DBField::END_TIME, 'End Time', false),
        ];

        $this->form = $this->buildGetEntityForm($request, $fields);

        if ($this->form->is_valid()) {
            $gameInstanceRoundId = $this->form->getCleanedValue(DBField::ID);
            $gameInstanceId = $this->form->getCleanedValue(DBField::GAME_INSTANCE_ID);
            $endTime = $this->form->getCleanedValue(DBField::END_TIME, $request->getCurrentSqlTime());

            $gameInstanceRound = $this->gamesInstancesRoundsManager->getGameInstanceRoundById($request, $gameInstanceRoundId);
            $gameInstance = $this->manager->getGameInstanceById($request, $gameInstanceId);
            $game = $this->gamesManager->getGameById($request, $gameInstance->getGameId());
            $gameBuild = $this->gamesBuildsManager->getGameBuildById($request, $gameInstance->getGameBuildId(), $gameInstance->getGameId());
            $gameInstance->setGameBuild($gameBuild);
            $game->setGameBuild($gameBuild);
            $gameInstance->setGame($game);

            if ($gameInstanceRound) {
                if ($gameInstanceRound->getGameInstanceId() != $gameInstanceId) {
                    $this->form->set_error("Provided game_instance_id ({$gameInstanceId}) does not match value associated with this GameInstanceRound.", DBField::GAME_INSTANCE_ID);
                } else if (!$gameInstanceRound->getEndTime()) {
                    $this->gamesInstancesRoundsManager->stopGameInstanceRound($request, $gameInstanceRound);
                }
            } else {
                $this->form->set_error("GameInstanceRound not found: {$gameInstanceRoundId}", DBField::ID);
            }

            if ($game->is_wan_enabled()) {

                $hostInstance = $this->hostsInstancesManager->getSlimHostInstanceById($request, $gameInstance->getHostInstanceId(), false, false);
                $hostInstance->setGameInstance($gameInstance);

                Modules::load_helper(Helpers::PUBNUB);

                $pubNubHelper = new PubNubHelper($request->user, $hostInstance, $gameInstance);

                $gameInstance->updateField(VField::PUB_NUB_CHANNELS, $pubNubHelper->getHostGameInstancePubNubChannels());

                $pubnub = PubNubLoader::getPubNubInstance($request);
                $pubNubChannel = $pubNubHelper->getHostGameInstancePubNubChannels()[PubNubHelper::CHANNEL_OFFLINE_BROADCAST];

                $hostInstances = $this->hostsInstancesManager->getActivePrioritizedHostInstancesByHostId($request, $hostInstance->getHostId(), true);
                $activeGameInstanceId = $gameInstance->getPk();
                $activeGameInstanceRoundId = null;

                $lbeHostInstance = [];
                $cloudHostInstance = [];

                foreach ($hostInstances as $activeHostInstance) {
                    if ($activeHostInstance->is_type_esc_host_app()) {
                        $lbeHostInstance = $activeHostInstance;
                    } else {
                        $cloudHostInstance = $activeHostInstance;
                    }

                    if ($activeGameInstance = $activeHostInstance->getActiveGameInstance()) {

                        $activeGameInstanceRound = $this->gamesInstancesRoundsManager->getActiveGameInstanceRoundByGameInstanceId($request, $activeGameInstance->getPk());

                        if ($activeGameInstanceRound) {
                            $activeGameInstanceId = $activeGameInstance->getPk();
                            $activeGameInstanceRoundId = $activeGameInstanceRound->getPk();
                            break;
                        }
                    }
                }

                // If there's a LBE host instance running and this is a cloud round, don't publish offline data.
                if ($lbeHostInstance && $cloudHostInstance && ($cloudHostInstance->getPk() == $hostInstance->getPk())) {
                    $shouldUpdateOfflineData = false;
                } else {
                    $shouldUpdateOfflineData = true;
                }

                if ($shouldUpdateOfflineData) {

                    $hostAndGameStatus = [
                        'host_running' => true,
                        'game_running' => false,
                        'next_url' => $hostInstance->getHost()->getUrl(),
                        'game_instance_id' => $activeGameInstanceId,
                        'game_instance_round_id' => $activeGameInstanceRoundId
                    ];
                    PubNubApiHelper::updateOfflineGameData($pubnub, $pubNubChannel->getChannelName(), $hostAndGameStatus);
                }

                // Commented out for now until we're ready to use on production.
                $issueGrants = false;
                if ($issueGrants) {
                    $pubnub = PubNubLoader::getPubNubInstance($request);

                    $channelsAuthed = true;

                    foreach ($pubNubHelper->getHostGameInstancePubNubChannels() as $channelKey => $pubNubChannel) {
                        if ($pubNubChannel && $channelKey != PubNubHelper::CHANNEL_PLAYER_DIRECT) {
                            $channelsAuthed = $pubNubHelper->issueGrant($pubnub, $pubNubChannel);
                            if (!$channelsAuthed)
                                break;
                        }
                    }

                    if ($channelsAuthed) {
                        foreach ($pubNubHelper->getPlayerGameInstancePubNubChannels() as $channelKey => $pubNubChannel) {
                            if ($channelKey != PubNubHelper::CHANNEL_PLAYER_DIRECT) {
                                $channelsAuthed = $pubNubHelper->issueGrant($pubnub, $pubNubChannel);
                                if (!$channelsAuthed)
                                    break;
                            }
                        }
                    }
                }
            }

            $this->setResults($gameInstanceRound);
        }

        return $this->renderApiV1Response($request);
    }

    /**
     * @param Request $request
     * @return ApiV1Response
     */
    public function handle_add_game_round_player(Request $request) : ApiV1Response
    {
        $fields = $this->gamesInstancesRoundsPlayersManager->getFormFields(false);

        $this->form = $this->buildGetEntityForm($request, $fields);

        if ($this->form->is_valid()) {

            $gameInstanceRoundId = $this->form->getCleanedValue(DBField::GAME_INSTANCE_ROUND_ID);
            $sessionHash = $this->form->getCleanedValue(DBField::SESSION_HASH);
            $userId = $this->form->getCleanedValue(DBField::USER_ID);
            $startTime = $this->form->getCleanedValue(DBField::START_TIME, $request->getCurrentSqlTime());
            $playerRequestId = $this->form->getCleanedValue(DBField::PLAYER_REQUEST_ID, null);

            $session = $this->sessionsTrackingManager->getSessionByHash($request, $sessionHash);

            if ($session) {
                $gameInstanceRoundPlayer = $this->gamesInstancesRoundsPlayersManager->createNewGameInstanceRoundPlayer(
                    $request,
                    $gameInstanceRoundId,
                    $session->getPk(),
                    $startTime,
                    $userId,
                    null,
                    null,
                    null,
                    $playerRequestId
                );

                $this->setResults($gameInstanceRoundPlayer);
            }
        }

        return $this->renderApiV1Response($request);
    }

    /**
     * @param Request $request
     * @return ApiV1Response
     */
    public function handle_remove_game_round_player(Request $request) : ApiV1Response
    {
        $exitStatuses = $this->gamesInstancesRoundsPlayersManager->getExitStatuses();

        $fields = [
            new IntegerField(DBField::ID, 'Primary Key'),
            new IntegerField(DBField::GAME_INSTANCE_ROUND_ID, 'Game Instance Round ID'),
            new DateTimeField(DBField::END_TIME, 'End Time', false),
            new DateTimeField(DBField::LAST_PING_TIME, 'Last Ping Time'),
            new SelectField(DBField::EXIT_STATUS, 'Client Exist Status', $exitStatuses, false)
        ];

        $this->form = $this->buildGetEntityForm($request, $fields);

        if ($this->form->is_valid()) {

            $gameInstanceRoundPlayerId = $this->form->getCleanedValue(DBField::ID);
            $gameInstanceRoundId = $this->form->getCleanedValue(DBField::GAME_INSTANCE_ROUND_ID);
            $endTime = $this->form->getCleanedValue(DBField::END_TIME, $request->getCurrentSqlTime());
            $lastPingTime = $this->form->getCleanedValue(DBField::LAST_PING_TIME);
            $exitStatus = $this->form->getCleanedValue(DBField::EXIT_STATUS);

            $gameInstanceRoundPlayer = $this->gamesInstancesRoundsPlayersManager->getGameInstanceRoundPlayerById(
                $request,
                $gameInstanceRoundPlayerId
            );

            if ($gameInstanceRoundPlayer) {

                if ($gameInstanceRoundPlayer->getGameInstanceRoundId() != $gameInstanceRoundId) {
                    $this->form->set_error("Provided game_instance_round_id ({$gameInstanceRoundId}) does not match value associated with this gameInstanceRoundPlayer.", DBField::GAME_INSTANCE_ROUND_ID);
                } else {
                    $gameInstanceRoundPlayer->assign([
                        DBField::END_TIME => $endTime,
                        DBField::LAST_PING_TIME => $lastPingTime,
                        DBField::EXIT_STATUS => $exitStatus
                    ]);

                    $gameInstanceRoundPlayer->saveEntityToDb($request);

                    $this->setResults($gameInstanceRoundPlayer);
                }
            } else {
                $this->form->set_error("GameInstanceRoundPlayer not found: {$gameInstanceRoundPlayerId}}", DBField::GAME_INSTANCE_ROUND_ID);
            }
        }

        return $this->renderApiV1Response($request);
    }

    /**
     * @param Request $request
     * @return ApiV1Response
     */
    public function handle_track_event(Request $request) : ApiV1Response
    {
        $fields = [
            new IntegerField(DBField::GAME_INSTANCE_ROUND_ID, 'Game Instance Round ID'),
            new IntegerField(DBField::GAME_INSTANCE_ID, 'Game Instance ID'),
            new CharField(DBField::EVENT_KEY, 'Event Key', 32),
            new IntegerField(DBField::GAME_INSTANCE_ROUND_PLAYER_ID, 'Game Instance Round Player ID', false),
            new KeyValueArrayField(DBField::VALUE, 'Key Value Pairs', false, 32, 1024),
            new DateTimeField(DBField::CREATE_TIME, 'Create Time', false)
        ];

        $this->form = $this->buildGetEntityForm($request, $fields);

        if ($this->form->is_valid()) {

            $gameInstanceRoundId = $this->form->getCleanedValue(DBField::GAME_INSTANCE_ROUND_ID);
            $gameInstanceId = $this->form->getCleanedValue(DBField::GAME_INSTANCE_ID);
            $eventKey = $this->form->getCleanedValue(DBField::EVENT_KEY);
            $gameInstanceRoundPlayerId = $this->form->getCleanedValue(DBField::GAME_INSTANCE_ROUND_PLAYER_ID);
            $keyValues = $this->form->getCleanedValue(DBField::VALUE);
            $createTime = $this->form->getCleanedValue(DBField::CREATE_TIME, $request->getCurrentSqlTime());

            $gameInstance = $this->manager->getGameInstanceById($request, $gameInstanceId);
            $gameInstanceRound = $this->gamesInstancesRoundsManager->getGameInstanceRoundById($request, $gameInstanceRoundId);

            $isValid = true;

            if (!$gameInstance) {
                $this->form->set_error("GameInstance not found: {$gameInstanceId}}", DBField::GAME_INSTANCE_ID);
                $isValid = false;
            }

            if (!$gameInstanceRound) {
                $this->form->set_error("GameInstanceRound not found: {$gameInstanceRoundId}}", DBField::GAME_INSTANCE_ROUND_ID);
                $isValid = false;
            }

            if ($gameInstanceRound && $gameInstanceRound->getGameInstanceId() != $gameInstanceId) {
                $this->form->set_error("Provided game_instance_id ({$gameInstanceId}) does not match value associated with this gameInstanceRound.", DBField::GAME_INSTANCE_ID);
                $isValid = false;
            }

            if ($isValid && $gameInstanceRoundPlayerId) {
                $gameInstanceRoundPlayer = $this->gamesInstancesRoundsPlayersManager->getGameInstanceRoundPlayerById($request, $gameInstanceRoundPlayerId);

                if (!$gameInstanceRoundPlayer) {
                    $this->form->set_error("GameInstancePlayer not found: {$gameInstanceRoundPlayerId}}", DBField::GAME_INSTANCE_ROUND_PLAYER_ID);
                    $isValid = false;
                }

                if ($gameInstanceRoundPlayer && $gameInstanceRoundPlayer->getGameInstanceRoundId() != $gameInstanceRoundId) {
                    $this->form->set_error("Provided game_instance_round_id ({$gameInstanceRoundId}) does not match value associated with this gameInstanceRoundPlayer.", DBField::GAME_INSTANCE_ROUND_PLAYER_ID);
                    $isValid = false;
                }
            }

            if ($isValid) {
                $gameInstanceRoundEvent = $this->gamesInstancesRoundsEventsManager->trackEvent(
                    $request,
                    $gameInstanceRound->getPk(),
                    $gameInstance->getGameId(),
                    $eventKey,
                    $keyValues,
                    $gameInstanceRoundPlayerId,
                    $createTime
                );
                $this->setResults($gameInstanceRoundEvent);
            }
        }

        return $this->renderApiV1Response($request);
    }

}
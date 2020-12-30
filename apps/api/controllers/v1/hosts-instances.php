<?php
/**
 * Created by PhpStorm.
 * User: christophercarter
 * Date: 7/12/18
 * Time: 11:16 AM
 */


class HostsInstancesApiV1Controller extends BaseApiV1Controller implements BaseApiControllerV1CRUDInterface {

    const REQUIRES_POST = true;
    const REQUIRES_AUTH = true;

    /** @var HostsInstancesManager $manager */
    protected $manager;
    /** @var ActivityManager $activityManager */
    protected $activityManager;
    /** @var AddressesManager $addressesManager */
    protected $addressesManager;
    /** @var SmsManager $smsManager */
    protected $smsManager;
    /** @var UsersManager $usersManager */
    protected $usersManager;
    /** @var HostsManager $hostsManager */
    protected $hostsManager;
    /** @var LocationsManager $locationsManager */
    protected $locationsManager;
    /** @var ActivationsManager $activationsManager */
    protected $activationsManager;
    /** @var HostVersionsManager $hostVersionsManager */
    protected $hostVersionsManager;
    /** @var PlatformsManager $platformsManager */
    protected $platformsManager;
    /** @var NetworksManager $networksManager */
    protected $networksManager;
    /** @var ShortUrlsManager $shortUrlsManager */
    protected $shortUrlsManager;
    /** @var GamesManager $gamesManager */
    protected $gamesManager;
    /** @var GamesBuildsManager $gamesBuildsManager */
    protected $gamesBuildsManager;
    /** @var GamesInstancesManager $gamesInstancesManager */
    protected $gamesInstancesManager;
    /** @var HostsInstancesTypesManager $hostsInstancesTypesManager */
    protected $hostsInstancesTypesManager;
    /** @var HostsInstancesInvitesManager $hostsInstancesInvitesManager */
    protected $hostsInstancesInvitesManager;
    /** @var GamesInstancesRoundsManager $gamesInstancesRoundsManager */
    protected $gamesInstancesRoundsManager;

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

        // Invites
        'invite-users' => 'handle_invite_users',

    ];

    /**
     * @param Request $request
     */
    protected function pre_handle(Request $request)
    {
        $this->smsManager = $request->managers->sms();
        $this->gamesManager = $request->managers->games();
        $this->usersManager = $request->managers->users();
        $this->hostsManager = $request->managers->hosts();
        $this->networksManager = $request->managers->networks();
        $this->activityManager = $request->managers->activity();
        $this->locationsManager = $request->managers->locations();
        $this->addressesManager = $request->managers->addresses();
        $this->platformsManager = $request->managers->platforms();
        $this->shortUrlsManager = $request->managers->shortUrls();
        $this->gamesBuildsManager = $request->managers->gamesBuilds();
        $this->activationsManager = $request->managers->activations();
        $this->hostVersionsManager = $request->managers->hostVersions();
        $this->organizationsManager = $request->managers->organizations();
        $this->gamesInstancesManager = $request->managers->gamesInstances();
        $this->hostsInstancesTypesManager = $request->managers->hostsInstancesTypes();
        $this->gamesInstancesRoundsManager = $request->managers->gamesInstancesRounds();
        $this->hostsInstancesInvitesManager = $request->managers->hostsInstancesInvites();
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
            $gameInstance = $this->manager->getHostInstanceById($request, $this->form->getPkValue(), $this->form->getExpand());
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
        $hosts = $this->hostsManager->getHostsByUserId($request, $request->user->id);
        $hostInstanceTypes = $this->hostsInstancesTypesManager->getAllActiveHostInstanceTypes($request);

        if ($hosts)
            $hosts = array_index($hosts, $this->hostsManager->getPkField());

        $hostIds = array_keys($hosts);

        $networks = $this->networksManager->getNetworksByHostId($request, $hostIds);
        $locations = $this->locationsManager->getLocationsByHostIds($request, $hostIds);

        $fields = $this->manager->getFormFields($hostInstanceTypes, $hosts, $networks, $locations);
        $defaults = [];

        $this->form = new ApiV1PostForm($fields, $request, $defaults);

        if ($this->form->is_valid()) {

            $hostInstanceTypeId = $this->form->getCleanedValue(DBField::HOST_INSTANCE_TYPE_ID, HostsInstancesTypesManager::ID_HOST_APP);

            $hostId = $this->form->getCleanedValue(DBField::HOST_ID);
            $locationId = $this->form->getCleanedValue(DBField::LOCATION_ID);
            $networkId = $this->form->getCleanedValue(DBField::NETWORK_ID);
            $localIp = $this->form->getCleanedValue(DBField::LOCAL_IP_ADDRESS);
            $localPort = $this->form->getCleanedValue(DBField::LOCAL_PORT);
            $hostDeviceId = $this->form->getCleanedValue(DBField::HOST_DEVICE_ID);

            $isPublic = 1;

            $hostVersionId = null;
            $platformId = null;
            $addressId = null;

            if (!$locationId) {

                $latitude = $this->form->getCleanedValue(DBField::LATITUDE);
                $longitude = $this->form->getCleanedValue(DBField::LONGITUDE);

                if ($latitude && $longitude) {

                    $city = $this->form->getCleanedValue(DBField::CITY);
                    $countryId = $this->form->getCleanedValue(DBField::COUNTRY_ID);
                    $state = $this->form->getCleanedValue(DBField::STATE);

                    $zipCode = $this->form->getCleanedValue(DBField::ZIP_CODE);

                    $streetName = $this->form->getCleanedValue(DBField::STREET_NAME);
                    $streetNumber = $this->form->getCleanedValue(DBField::STREET_NUMBER);
                    $addressLine1 = null;

                    if ($streetName && $streetNumber)
                        $addressLine1 = "{$streetNumber} {$streetName}";
                    elseif ($streetName)
                        $addressLine1 = $streetName;

                    $location = $this->locationsManager->getLocationByLatitudeLongitude($request, $hostId, $latitude, $longitude);
                    $address = [];

                    if ($location) {
                        $address = $this->addressesManager->getAddressByContextOwnerAndValues(
                            $request,
                            EntityType::LOCATION,
                            $location->getPk(),
                            $countryId,
                            $state,
                            $zipCode,
                            $city,
                            $addressLine1
                        );
                    } else {
                        $location = $this->locationsManager->createNewLocation($request, $hostId, $latitude, $longitude);
                    }

                    $locationId = $location->getPk();

                    if (!$address) {
                        $user = $request->user->getEntity();
                        $address = $this->addressesManager->createNewAddress(
                            $request,
                            EntityType::LOCATION,
                            $location->getPk(),
                            $countryId,
                            AddressesTypesManager::ID_BUSINESS,
                            1,
                            null,
                            $user->getFirstName(),
                            $user->getLastName(),
                            $user->getPhoneNumber(),
                            $addressLine1,
                            null,
                            null,
                            $city,
                            $state,
                            $zipCode
                        );
                    }

                    $addressId = $address->getPk();
                }
            }

            // Todo: Implement this when host app sends those fields.
            $hostVersionNumber = $this->form->getCleanedValue(DBField::HOST_VERSION);
            $platformSlug = $this->form->getCleanedValue(DBField::PLATFORM_SLUG);

            if ($hostVersionNumber && $platformSlug) {

                if ($platform = $this->platformsManager->getPlatformBySlug($request, $platformSlug)) {
                    $platformId = $platform->getPk();

                    $hostVersion = $this->hostVersionsManager->getHostVersionByHostVersionNumberAndPlatformId($request, $hostVersionNumber, $platformId);
                    if ($hostVersion)
                        $hostVersionId = $hostVersion->getPk();
                }
            }

            $startTime = $request->getCurrentSqlTime();

            $hostInstance = $this->manager->createNewHostInstance(
                $request,
                $hostInstanceTypeId,
                $request->user->id,
                $hostId,
                $locationId,
                $addressId,
                $networkId,
                $localIp,
                $localPort,
                $isPublic,
                $startTime,
                null,
                $hostVersionId,
                $platformId,
                $hostDeviceId
            );

            $host = $this->hostsManager->getSlimHostById($request, $hostId);

            $hostInstance->setHost($host);
            $hostInstance->updateField(VField::URL, $host->getUrl());

            $activations = $this->activationsManager->getLiveActivationsByTypeAndHostId(
                $request,
                $hostInstance->getHostId(),
                ActivationsTypesManager::ID_LOCATION_BASED,
                $request->getCurrentSqlTime()
            );

            foreach ($activations as $activation)
                $hostInstance->setActivation($activation);

            $pubNubConfig = [
                TemplateVars::PUBLISH_KEY => $request->config['pubnub']['publish_key'],
                TemplateVars::SUBSCRIBE_KEY => $request->config['pubnub']['subscribe_key'],
                TemplateVars::SSL => true
            ];

            $hostInstance->updateField(TemplateVars::PUB_NUB_CONFIG, $pubNubConfig);

            $activity = $this->activityManager->trackActivity(
                $request,
                ActivityTypesManager::ACTIVITY_TYPE_USER_START_HOST_INSTANCE,
                $hostVersionId,
                $hostInstance->getPk(),
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

            if ($hostInstance->is_type_esc_host_app())
                $this->manager->createCloudFlareHostInstanceDNSRecord($request, $hostInstance);

            $this->setResults($hostInstance);
        }

        return $this->renderApiV1Response($request);
    }

    /**
     * @param Request $request
     * @return ApiV1Response
     * @note: NOT IN USE
     */
    public function handle_update(Request $request): ApiV1Response
    {
        $getEntityForm = $this->buildGetEntityForm($request);

        if ($getEntityForm->is_valid()) {

            $hosts = $this->hostsManager->getHostsByUserId($request, $request->user->id);
            $hostIds = extract_pks($hosts);

            $networks = $this->networksManager->getNetworksByHostId($request, $hostIds);
            $locations = $this->locationsManager->getLocationsByHostIds($request, $hostIds);

            $fields = $this->manager->getFormFields($hosts, $networks, $locations);

            $hostInstance = $this->manager->getHostInstanceById($request, $getEntityForm->getPkValue());

            $this->form = new ApiV1PostForm($fields, $request, $hostInstance);

            if ($this->form->is_valid()) {
                $hostInstance->assignByForm($this->form)->saveEntityToDb($request);
                $this->setResults($hostInstance);
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

            $hostInstance = $this->manager->getHostInstanceById($request, $this->form->getPkValue());

            $this->manager->deactivateEntity($request, $hostInstance);

            $this->setResults($hostInstance);
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
            $hostInstance = $this->manager->getSlimHostInstanceById($request, $this->form->getPkValue(), false, false);

            if ($hostInstance) {
                $hostInstance->updateField(DBField::LAST_PING_TIME, $request->getCurrentSqlTime())->saveEntityToDb($request);
                $hostInstance->updateField(VField::URL, $hostInstance->getHost()->getUrl());

                $activations = $this->activationsManager->getLiveActivationsByTypeAndHostId(
                    $request,
                    $hostInstance->getHostId(),
                    ActivationsTypesManager::ID_LOCATION_BASED,
                    $request->getCurrentSqlTime()
                );

                $gameInstance = $this->gamesInstancesManager->getRunningGameInstanceByHostInstanceId($request, $hostInstance->getPk(), false, false);

                if ($gameInstance) {

                    $game = $this->gamesManager->getGameById($request, $gameInstance->getGameId());
                    $gameBuild = $this->gamesBuildsManager->getGameBuildById($request, $gameInstance->getGameBuildId(), $gameInstance->getGameId());
                    $gameInstance->setGameBuild($gameBuild);
                    $game->setGameBuild($gameBuild);

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

                    $gameInstance->setGame($game);

                    if ($game->is_wan_enabled()) {

                        $hostInstance->setGameInstance($gameInstance);

                        Modules::load_helper(Helpers::PUBNUB);

                        $pubNubHelper = new PubNubHelper($request->user, $hostInstance, $gameInstance);

                        $gameInstance->updateField(VField::PUB_NUB_CHANNELS, $pubNubHelper->getHostGameInstancePubNubChannels());
                    }

                    $gameInstanceRound = $this->gamesInstancesRoundsManager->getActiveGameInstanceRoundByGameInstanceId($request, $gameInstance->getPk());

                    if ($gameInstanceRound)
                        $gameInstance->setGameInstanceRound($gameInstanceRound);

                    $hostInstance->setGameInstance($gameInstance);
                }

                foreach ($activations as $activation)
                    $hostInstance->setActivation($activation);
            }

            $this->setResults($hostInstance);
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
            $hostInstance = $this->manager->getSlimHostInstanceById($request, $this->form->getPkValue(), false);

            if ($hostInstance) {
                $this->manager->stopHostInstance($request, $hostInstance);
            }

            $this->setResults($hostInstance);
        }

        return $this->renderApiV1Response($request);
    }

    /**
     * @param Request $request
     * @return ApiV1Response
     */
    public function handle_invite_users(Request $request) : ApiV1Response
    {
        $fields = [
            new IntegerField(DBField::HOST_INSTANCE_ID, 'Host Instance'),
            new IntegerField(DBField::GAME_INSTANCE_ID, 'Game Instance', false),
            new PhoneNumberArrayField(VField::PHONE_NUMBERS, 'Phone Numbers', $request->geoIpMapping->getCountryId(), true),
            new EmailArrayField(VField::EMAIL_ADDRESSES, 'Email Addresses', false)
        ];

        $this->form = $this->buildGetEntityForm($request, $fields);

        if ($isValid = $this->form->is_valid()) {
            $hostInstanceId = $this->form->getCleanedValue(DBField::HOST_INSTANCE_ID);
            $gameInstanceId = $this->form->getCleanedValue(DBField::GAME_INSTANCE_ID);

            $phoneNumbers = $this->form->getCleanedValue(VField::PHONE_NUMBERS);
            $emailAddresses = $this->form->getCleanedValue(VField::EMAIL_ADDRESSES);

            $gameInstance = [];

            if ($hostInstance = $this->manager->getSlimHostInstanceById($request, $hostInstanceId, true)) {

                if ($gameInstanceId) {
                    $gameInstance = $hostInstance->getGameInstanceById($gameInstanceId);

                    if (!$gameInstance) {
                        $this->form->set_error('Game Instance Not Found', DBField::GAME_INSTANCE_ID);
                        $isValid = false;
                    }
                }
            } else {
                $this->form->set_error('Host Instance Not Found', DBField::HOST_INSTANCE_ID);
                $isValid = false;
            }

            if ($isValid) {

                $host = $hostInstance->getHost();

                $smsMessages = [];
                $hostInstanceInvites  = [];

                foreach ($phoneNumbers as $phoneNumber) {

                    $userId = null;
                    $userLoginParams = [];
                    $inviteHash = md5(mt_rand()+time()+microtime(true));

                    $user = $this->usersManager->getUserByPhoneNumber($request, $phoneNumber);
                    if ($user) {
                        $userId = $user->getPk();

                        $expiration = strtotime($request->getCurrentSqlTime()) + FIFTEEN_MINUTES;

                        $userLoginParams = $this->usersManager->generateMagicLoginUrlParamsForUser(
                            $request,
                            $user,
                            $expiration,
                            $host->getUrl()
                        );
                    }

                    if ($gameInstance) {

                        $smsTypeId = SmsTypesManager::ID_GAME_INSTANCE_PLAYER;

                        $params = [
                            GetRequest::PARAM_INVITATION => $inviteHash,
                            GetRequest::PARAM_UTM_MEDIUM => 'sms',
                            GetRequest::PARAM_UTM_SOURCE => 'host-app',
                            GetRequest::PARAM_UTM_CAMPAIGN => 'player-invite',
                            GetRequest::PARAM_UTM_TERM => "game-instance-{$gameInstance->getPk()}",
                        ];

                        $fullUrl = $host->getUrl($request->buildQuery(array_merge($userLoginParams, $params)));

                        $shortUrl = $this->shortUrlsManager->createNewShortUrl($request, $fullUrl);
                        $inviteMessage = "[ESC GAMES] You've been invited to join {$gameInstance->getGame()->getDisplayName()}: {$shortUrl->getUrl()}";

                    } else {

                        $smsTypeId = SmsTypesManager::ID_HOST_INSTANCE_PLAYER;

                        $params = [
                            GetRequest::PARAM_INVITATION => $inviteHash,
                            GetRequest::PARAM_UTM_MEDIUM => 'sms',
                            GetRequest::PARAM_UTM_SOURCE => 'host-app',
                            GetRequest::PARAM_UTM_CAMPAIGN => 'player-invite',
                            GetRequest::PARAM_UTM_TERM => "host-instance-{$hostInstance->getPk()}",
                        ];

                        $fullUrl = $host->getUrl($request->buildQuery(array_merge($userLoginParams, $params)));

                        $shortUrl = $this->shortUrlsManager->createNewShortUrl($request, $fullUrl);
                        $inviteMessage = "[ESC GAMES] You've been invited to join host: {$shortUrl->getUrl()}";
                    }

                    $smsMessages[] = $sms = $this->smsManager->scheduleSms($request, $smsTypeId, $phoneNumber, $inviteMessage, null, $userId);

                    $hostInstanceInvite = $this->hostsInstancesInvitesManager->createNewHostInstanceInvite(
                        $request,
                        HostsInstancesInvitesTypesManager::ID_PHONE,
                        $hostInstance->getPk(),
                        $phoneNumber,
                        $inviteHash,
                        $gameInstanceId,
                        $shortUrl->getPk(),
                        $userId,
                        $sms->getPk()
                    );

                    if (!$user) {
                        $user = $this->usersManager->generateAnonymousUser($request);
                        $user->updateField(DBField::PHONE_NUMBER, $phoneNumber);
                    }

                    $hostInstanceInvite->updateField(VField::USER, $user);

                    $hostInstanceInvites[] = $hostInstanceInvite;
                }

                if ($gameInstance) {
                    $activityTypeId = ActivityTypesManager::ACTIVITY_TYPE_USER_INVITE_GAME_PLAYERS;
                    $contextEntityId = $gameInstance->getGameId();
                    $entityId = $gameInstance->getPk();
                } else {
                    $activityTypeId = ActivityTypesManager::ACTIVITY_TYPE_USER_INVITE_HOST_PLAYERS;
                    $contextEntityId = $hostInstance->getHostId();
                    $entityId = $hostInstance->getPk();
                }

                $requestUser = $request->user->getEntity();

                $this->activityManager->trackActivity(
                    $request,
                    $activityTypeId,
                    $contextEntityId,
                    $entityId,
                    $requestUser->getUiLanguageId(),
                    $requestUser
                );

                $this->setResults($hostInstanceInvites);

            }

        }

        return $this->renderApiV1Response($request);
    }

}
<?php
/**
 * Created by PhpStorm.
 * User: christophercarter
 * Date: 7/13/18
 * Time: 10:51 AM
 */

class AuthApiV1Controller extends BaseApiV1Controller {

    const REQUIRES_POST = true;
    const REQUIRES_AUTH = false;

    /** @var UsersManager $manager */
    protected $manager;
    /** @var RequestsManager */
    protected $requestsManager;
    /** @var HostsDevicesManager $hostsDevicesManager */
    protected $hostsDevicesManager;
    /** @var HostsDevicesComponentsManager $hostsDevicesComponentsManager */
    protected $hostsDevicesComponentsManager;
    /** @var UserProfilesManager $usersProfilesManager */
    protected $usersProfilesManager;
    /** @var PlatformsManager $platformsManager */
    protected $platformsManager;
    /** @var LoginAttemptsManager $loginAttemptsManager */
    protected $loginAttemptsManager;
    /** @var ActivityManager $activityTrackingManager */
    protected $activityTrackingManager;
    /** @var HostsManager $hostsManager */
    protected $hostsManager;
    /** @var ApplicationsUsersManager $applicationsUsersManager */
    protected $applicationsUsersManager;
    /** @var ApplicationsUsersAccessTokensManager $applicationUsersAccessTokensManager */
    protected $applicationUsersAccessTokensManager;
    /** @var OrganizationsManager */
    protected $organizationsManager;

    protected $pages = [

        // Index Page
        '' => 'handle_index',
        'register-device' => 'handle_register_device',
        'request-token' => 'handle_request_token',
        'validate-auth' => 'handle_validate_auth',
        'logout' => 'handle_logout',
        'logout-all' => 'handle_logout_all',
    ];

    /**
     * @param Request $request
     */
    protected function pre_handle(Request $request)
    {
        $this->requestsManager = $request->managers->requests();
        $this->usersManager = $request->managers->users();
        $this->usersProfilesManager = $request->managers->usersProfiles();
        $this->loginAttemptsManager = $request->managers->loginAttempts();
        $this->activityTrackingManager = $request->managers->activity();
        $this->platformsManager = $request->managers->platforms();
        $this->hostsManager = $request->managers->hosts();
        $this->applicationsUsersManager = $request->managers->applicationsUsers();
        $this->applicationUsersAccessTokensManager = $request->managers->applicationsUsersAccessTokens();
        $this->hostsDevicesManager = $request->managers->hostsDevices();
        $this->hostsDevicesComponentsManager = $request->managers->hostsDevicesComponents();
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
    public function handle_register_device(Request $request) : ApiV1Response
    {
        $platformSlugOptions = $this->platformsManager->getAllActivePlatformSlugOptions($request);

        $fields = [
            new CharField(DBField::UUID, 'Host Device UUID', 64),
            new SelectField(DBField::PLATFORM_SLUG, 'Platform Slug', $platformSlugOptions),
            new CharField(DBField::DISPLAY_NAME, 'Display Name', 0, false),
            new ArrayField(DBField::VALUE, 'Key Value Pairs', true),
        ];

        $this->form = new ApiV1PostForm($fields, $request);

        if ($this->form->is_valid()) {

            $uuid = $this->form->getCleanedValue(DBField::UUID);
            $platformSlug = $this->form->getCleanedValue(DBField::PLATFORM_SLUG);
            $displayName = $this->form->getCleanedValue(DBField::DISPLAY_NAME);
            $values = $this->form->getCleanedValue(DBField::VALUE);

            if (!$hostDevice = $this->hostsDevicesManager->getHostDeviceByUuid($request, $uuid)) {

                $platform = $this->platformsManager->getPlatformBySlug($request, $platformSlug);

                $hostDevice = $this->hostsDevicesManager->createNewHostDevice(
                    $request,
                    $uuid,
                    $platform->getPk(),
                    $displayName
                );

                foreach ($values as $hostDeviceComponentGroup => $properties) {
                    $this->hostsDevicesComponentsManager->createNewHostDeviceComponent(
                        $request,
                        $hostDevice->getPk(),
                        $hostDeviceComponentGroup,
                        $properties
                    );
                }
            }

            $this->setResults($hostDevice);
        }

        return $this->renderApiV1Response($request);
    }


    /**
     * @param Request $request
     * @return ApiV1Response
     */
    public function handle_request_token(Request $request) : ApiV1Response
    {
        $fields = $this->manager->getLoginFields($request, true);

        $this->form = $this->buildGetEntityForm($request, $fields);

        if ($this->form->is_valid()) {

            $email = strtolower($this->form->getCleanedValue(DBField::EMAIL));
            $jsPasswordHash = $this->form->getCleanedValue(DBField::PASSWORD_HASH);

            $loginAllowed = $this->loginAttemptsManager->checkLoginAllowed($request, $email);

            if (!$loginAllowed) {
                $message = $request->translations->lookup(T::AUTH_LOGIN_TOO_MANY_ATTEMPTS);
                $this->form->set_error($message);
            } else {
                try {
                    $user = $this->manager->getUserLoginDataByEmail($email);

                    $passwordHash = $user[DBField::PASSWORD];

                    // Verify Password Matches
                    $passwordMatches = $request->auth->checkPassword($jsPasswordHash, $passwordHash);

                    if (!$passwordMatches) {

                        $this->loginAttemptsManager->recordFailedLogin($request, $email);
                        $message = $request->translations->lookup(T::AUTH_LOGIN_INCORRECT_CREDENTIALS);
                        $this->form->set_error($message);

                    } else {

                        $user = $this->manager->getUserById($request, $user[DBField::USER_ID]);

                        // Wipe out incorrect login attempts
                        $this->loginAttemptsManager->cleanLoginAttempts($request, $email);

                        $canLogin = true;

                        $info = [
                            'ident' => true,
                            'userid' => $user->getPk()
                        ];

                        $newUser = new User($request, $info);

                        if (!$applicationUser = $this->applicationsUsersManager->getApplicationUser($request, ApplicationsManager::ID_ESC_API, $user->getPk())) {
                            $applicationUser = $this->applicationsUsersManager->createNewApplicationUser($request, ApplicationsManager::ID_ESC_API, $user->getPk());
                        }

                        if (!$applicationUser->is_active()) {
                            $this->form->set_error('API Access has been disabled for your account - please contact customer service.');
                            $canLogin = false;
                        } else {
                            $applicationUserAccessToken = $this->applicationUsersAccessTokensManager->createNewAccessToken($request, $applicationUser);
                        }

                        if (!$newUser->permissions->has(RightsManager::RIGHT_HOST_APP, Rights::USE)) {
                            $this->form->set_error('You lack sufficient privileges to log in.');
                            $canLogin = false;
                        }


                        if ($canLogin) {

                            $request->user->id = $newUser->id;
                            $request->user->username = $newUser->username;

                            if ($this->form->getExpand()) {

                                //$hosts = $this->hostsManager->getHostsByUserId($request, $user->getPk());

                                $hosts = $this->hostsManager->getAllAvailableHostsToUserId($request, $user->getPk());

                                $user->updateField(VField::HOSTS, $hosts);

                                $applicationUserAccessToken->updateField(VField::USER, $user);

                                $organizations = $this->organizationsManager->getOrganizationsByUserId($request, $user->getPk());

                                $user->updateField(VField::ORGANIZATIONS, $organizations);

                            }

                            $this->activityTrackingManager->trackActivity(
                                $request,
                                ActivityTypesManager::ACTIVITY_TYPE_USER_API_LOGIN_REQUEST_TOKEN,
                                $applicationUserAccessToken->getPk(),
                                $user->getPk(),
                                $request->getUiLang(),
                                $user
                            );

                            $this->setResults($applicationUserAccessToken);
                        }
                    }

                } catch (ObjectNotFound $e) {
                    $this->loginAttemptsManager->recordFailedLogin($request, $email);
                    $message = $request->translations->lookup(T::AUTH_LOGIN_INCORRECT_CREDENTIALS);
                    $this->form->set_error($message);
                }
            }

        }

        return $this->renderApiV1Response($request);
    }

    /**
     * @param Request $request
     * @return ApiV1Response
     */
    protected function handle_validate_auth(Request $request) : ApiV1Response
    {
        $fields = [
            new CharField(DBField::REQUEST_ID, 'Request ID'),
            new IntegerField(DBField::USER_ID, 'User Id'),
        ];

        $this->form = new ApiV1PostForm($fields, $request);

        if ($this->form->is_valid()) {

            $requestId = $this->form->getCleanedValue(DBField::REQUEST_ID);
            $userId = $this->form->getCleanedValue(DBField::USER_ID);

            // Todo: Remove dependency on request tracking here

            $isValidRequest = $this->requestsManager->validateRequestByIdAndUserId($request, $requestId, $userId);

            if ($userId && $isValidRequest) {
                $user = $this->manager->getUserById($request, $userId);
            } else {
                $user = $this->manager->generateAnonymousUser($request);
            }

            $this->setResults($user);
        }

        return $this->renderApiV1Response($request);
    }

    /**
     * @param Request $request
     * @return ApiV1Response
     */
    public function handle_logout(Request $request) : ApiV1Response
    {
        $this->form = new ApiV1PostForm([], $request);

        if ($request->user->is_authenticated() && $this->form->is_valid()) {
            $accessTokenId = $request->user->getApplicationUserAccessTokenId();
            $applicationUserAccessToken = $this->applicationUsersAccessTokensManager->getApplicationUserAccessTokenById($request, $accessTokenId);

            $dt = new DateTime();

            $dt->modify("-1 second");

            $applicationUserAccessToken->updateField(DBField::EXPIRES_ON, $dt->format(SQL_DATETIME))->saveEntityToDb($request);

            $this->setResults($applicationUserAccessToken);
        }

        return $this->renderApiV1Response($request);
    }

    /**
     * @param Request $request
     * @return ApiV1Response|HttpResponse
     */
    public function handle_logout_all(Request $request) : ApiV1Response
    {
        if (!$request->user->is_authenticated())
            return $this->redirect(HOMEPAGE);

        $this->form = new ApiV1PostForm([], $request);

        if ($this->form->is_valid()) {

            $this->applicationUsersAccessTokensManager->deactivateAllTokensForUserApplication(
                $request,
                ApplicationsManager::ID_ESC_API,
                $request->user->id
            );
            $this->setResults(true);
        }

        return $this->renderApiV1Response($request);
    }


}
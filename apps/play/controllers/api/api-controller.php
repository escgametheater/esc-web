<?php
/**
 * Created by PhpStorm.
 * User: christophercarter
 * Date: 3/4/19
 * Time: 3:13 PM
 */

class PlayApiController extends BaseContent
{
    protected $url_key = 3;

    protected $pages = [
        'leaderboard' => 'handle_get_highscores',
        'track-highscore' => 'handle_track_highscore',
        'register' => 'handle_register',
        'login' => 'handle_login'
    ];

    /**
     * @param Request $request
     * @param null $url_key
     * @param null $pages
     * @param null $render_default
     * @param null $root
     * @return HtmlResponse
     */
    public function render(Request $request, $url_key = null, $pages = null, $render_default = null, $root = null)
    {
        if ($request->getSlugForEntity($url_key) != 'v1') {
            return $this->render_404($request);
        }

        $url_key++;

        $func = $this->resolve($request, $url_key, $pages, $render_default, $root);

        if ($func === null)
            throw new Http404();

        return $this->$func($request, ...$this->controllerArgs);

    }

    /**
     * @param Request $request
     * @return JSONResponse
     */
    protected function handle_register(Request $request)
    {
        $usersManager = $request->managers->users();
        $activityManager = $request->managers->activity();
        $sessionsManager = $request->managers->sessionTracking();
        $userCoinsManager = $request->managers->userCoins();
        $guestCoinsManager = $request->managers->guestCoins();
        $emailTrackingManager = $request->managers->emailTracking();

        $fields = [
            new CharField(DBField::NAME, 'Full Name', 70, true, 'Your first and last names.', 'Ex: John Smith'),
            new EmailField(DBField::EMAIL_ADDRESS, 'Email Address', true, 'What is your email address?', 'Ex: johndoe@example.com'),
            new PhoneNumberField(DBField::PHONE_NUMBER, 'Cell Phone Number', 'us', true, 'Your cell phone number.', 'Ex: 555-545-5555'),
            new HiddenField(DBField::HOST_ID, 'Host Id', 0, false, null),
            new HiddenField(DBField::GAME_ID, 'Game ID', 0, false, null),
            new HiddenField(DBField::GAME_BUILD_ID, 'Game Build Id', 0, false, null),
            new HiddenField(DBField::HOST_INSTANCE_ID, 'Host Instance Id', 0, false, null),
            new HiddenField(DBField::GAME_INSTANCE_ID, 'Game Instance Id', 0, false, null),
        ];

        $form = new PostForm($fields, $request);

        if ($isValid = $form->is_valid()) {

            $name = $form->getCleanedValue(DBField::NAME);
            $emailAddress = $form->getCleanedValue(DBField::EMAIL_ADDRESS);
            $phoneNumber = $form->getCleanedValue(DBField::PHONE_NUMBER);
            $hostId = $form->getCleanedValue(DBField::HOST_ID);
            $gameId = $form->getCleanedValue(DBField::GAME_ID);
            $gameBuildId = $form->getCleanedValue(DBField::GAME_BUILD_ID);
            $hostInstanceId = $form->getCleanedValue(DBField::HOST_INSTANCE_ID);
            $gameInstanceId = $form->getCleanedValue(DBField::GAME_INSTANCE_ID);

            if ($usersManager->getUserByEmailAddress($request, $emailAddress)) {
                $isValid = false;
                $form->set_error('Email address already in use!', DBField::EMAIL_ADDRESS);
            }

            if ($usersManager->getUserByPhoneNumber($request, $phoneNumber)) {
                $isValid = false;
                $form->set_error('Phone number already in use!', DBField::PHONE_NUMBER);
            }

            $firstName = null;
            $lastName = null;
            $nameParts = explode(' ', $name);
            if (count($nameParts) < 2) {
                $form->set_error('First and last names are required', DBField::NAME);
                $isValid = false;
            } else {
                $firstName = array_shift($nameParts);
                $lastNames = join(' ', $nameParts);

                $lastName = array_pop($nameParts);

                $middle = '';
                foreach ($nameParts as $namePart)
                    $middle .= $namePart[0];

            }

            if ($isValid) {

                $conn = $request->db->get_connection();

                $password = generate_random_string(14);
                $jsPasswordHash = $request->auth->compute_password_js_hash($password);
                $passwordHash = $request->auth->compute_password_hash($jsPasswordHash);

                $conn->begin();

                $userId = $usersManager->createNewUser(
                    $request,
                    $emailAddress,
                    $phoneNumber,
                    $passwordHash, // PasswordHash
                    null, // Zip Code
                    $firstName,
                    $lastName,
                    $name, // Display Name
                    null, // Birthday
                    1, // Gender Id
                    0, // Beta Access
                    0, // Verified,
                    null, // UserName
                    null // Host Slug
                );

                $conn->commit();

                $user = $usersManager->getUserById($request, $userId);

                $info = [
                    Auth::INFO_IDENT => true,
                    Auth::INFO_USERID => $user->getPk()
                ];

                // Hold onto anon user if necessary
                $anonUser = $request->user;

                // Create new authenticated user object and insert this into the request object so we can generate a correct
                // window.esc, as well as ensure response middleware fires with the correct context.
                $newUser = new User($request, $info);
                $session = $anonUser->session;
                $guest = $anonUser->guest;

                // Update first user if required
                if (!$session->getEntity()->getFirstUserId()) {
                    $sessionsManager->updateSessionRecordFirstUserId($request, $session->getEntity(), $user->getPk());
                }
                if (!$guest->getEntity()->getFirstUserId()) {
                    $guest->getEntity()->updateGuestRecordFirstUserId($request, $user->getPk());
                }

                // Set the required details
                $newUser->setSession($session);
                $newUser->setGuest($guest);

                $request->setUser($newUser);

                $request->auth->makeCookies($request, $userId);

                if ($gameInstanceId)
                    $activityTypeId = ActivityTypesManager::ACTIVITY_TYPE_USER_REGISTRATION_GAME_INSTANCE;
                elseif ($hostInstanceId)
                    $activityTypeId = ActivityTypesManager::ACTIVITY_TYPE_USER_REGISTRATION_HOST_INSTANCE;
                elseif ($gameBuildId)
                    $activityTypeId = ActivityTypesManager::ACTIVITY_TYPE_USER_REGISTRATION_GAME_BUILD;
                elseif ($gameId)
                    $activityTypeId = ActivityTypesManager::ACTIVITY_TYPE_USER_REGISTRATION_GAME;
                elseif ($hostId)
                    $activityTypeId = ActivityTypesManager::ACTIVITY_TYPE_USER_REGISTRATION_HOST;
                else
                    $activityTypeId = ActivityTypesManager::ACTIVITY_TYPE_USER_REGISTRATION_PLAY;

                $contextEntityId = $gameInstanceId ?? $hostInstanceId ?? $gameBuildId ?? $gameId ?? $hostId ?? null;

                $registrationActivity = $activityManager->trackActivity(
                    $request,
                    $activityTypeId,
                    $contextEntityId,
                    $user->getPk(),
                    $user->getUiLanguageId(),
                    $user
                );

                $totalPlayerScore = $guestCoinsManager->getTotalGuestCoins($request, $guest->getGuestId(), $hostId);

                // Migrate unclaimed guest coins to user.
                if ($guestCoinsManager->checkGuestHasUnclaimedCoins($request, $guest->getGuestId())) {

                    $conn->begin();
                    $userCoinsManager->migrateUnclaimedGuestCoins($request, $conn, $guest->getGuestId(), $newUser->getId());
                    $conn->commit();

                }

                // Send Registration Email
                Modules::load_helper(Helpers::EMAIL);
                $emailGenerator = new EmailGenerator(
                    $request,
                    $emailAddress,
                    EmailTypesManager::TYPE_SYSTEM_REGISTRATION_CONFIRMATION_PLAY,
                    $emailTrackingManager->generateChecksum(),
                    $registrationActivity->getPk()
                );

                $emailViewData = [
                    'hashed_email' => base64_encode($user->getEmailAddress()),
                    'new_password' => $password,
                    'recipient' => $user,
                    'total_score' => $totalPlayerScore
                ];

                $emailGenerator->setRecipientUser($user)
                    ->assignViewData($emailViewData)
                    ->sendEmail();

                $session = $request->user->session->getEntity();
                if (!$session->getFirstUserId()) {
                    $session->updateField(DBField::FIRST_USER_ID, $user->getPk())->saveEntityToDb($request);
                }

                // Render Form Response
                return $this->renderJsonResponse($form->renderJson($user->getDataArray(), $this->renderJsonModel($request)));
            } else {
                return $this->renderJsonResponse($form->renderJson([], $this->renderJsonModel($request)), HttpResponse::HTTP_BAD_REQUEST);
            }

        } else {
            if ($request->is_post())
                return $this->renderJsonResponse($form->renderJson([], $this->renderJsonModel($request)), HttpResponse::HTTP_BAD_REQUEST);
        }

        return $this->renderJsonResponse($form->renderJson());
    }

    /**
     * @param Request $request
     * @return HttpResponseRedirect|JSONResponse
     */
    protected function handle_login(Request $request)
    {
        if ($request->user->is_authenticated() || !$request->is_post())
            return $this->redirect($request->getPlayUrl());

        $loginAttemptsManager = $request->managers->loginAttempts();
        $usersManager = $request->managers->users();
        $sessionsManager = $request->managers->sessionTracking();
        $userCoinsManager = $request->managers->userCoins();
        $guestCoinsManager = $request->managers->guestCoins();

        $user = [];

        $emailAddress = strtolower($request->readPostParam(FormField::EMAIL));

        $loginAllowed = $loginAttemptsManager->checkLoginAllowed($request, $emailAddress);

        $fields = $usersManager->getLoginFields($request, true);

        $form = new PostForm($fields, $request);

        if ($isValid = $form->is_valid()) {

            if (!$loginAllowed) {
                $message = $request->translations->lookup(T::AUTH_LOGIN_TOO_MANY_ATTEMPTS);
                $isValid = false;
                $form->set_error($message);
            }

            if ($isValid) {
                $email = $form->getCleanedValue(FormField::EMAIL);

                try {
                    $user = $usersManager->getUserLoginDataByEmail($email);

                    $passwordHash = $user[DBField::PASSWORD];
                    $jsHashedPassword = $form->cleaned_data[FormField::PASSWORD_HASH];

                    // Verify Password Matches
                    $password_matches = $request->auth->checkPassword($jsHashedPassword, $passwordHash);

                    if (!$password_matches) {
                        $loginAttemptsManager->recordFailedLogin($request, $email);
                        $message = $request->translations->lookup(T::AUTH_LOGIN_INCORRECT_CREDENTIALS);
                        $form->set_error($message);
                    } else {
                        $user = $usersManager->getUserById($request, $user[DBField::USER_ID]);

                        $info = [
                            Auth::INFO_IDENT => true,
                            Auth::INFO_USERID => $user->getPk()
                        ];

                        // Hold onto anon user if necessary
                        $anonUser = $request->user;

                        // Create new authenticated user object and insert this into the request object so we can generate a correct
                        // window.esc, as well as ensure response middleware fires with the correct context.
                        $newUser = new User($request, $info);
                        $session = $anonUser->session;
                        $guest = $anonUser->guest;

                        // Update first user if required
                        if (!$session->getEntity()->getFirstUserId()) {
                            $sessionsManager->updateSessionRecordFirstUserId($request, $session->getEntity(), $user->getPk());
                        }
                        if (!$guest->getEntity()->getFirstUserId()) {
                            $guest->getEntity()->updateGuestRecordFirstUserId($request, $user->getPk());
                        }

                        // Set the required details
                        $newUser->setSession($session);
                        $newUser->setGuest($guest);

                        $request->setUser($newUser);

                        $request->auth->makeCookies($request, $user->getPk(), true);

                        // Wipe out incorrect login attempts
                        $loginAttemptsManager->cleanLoginAttempts($request, $email);

                        $request->managers->activity()->trackActivity(
                            $request,
                            ActivityTypesManager::ACTIVITY_TYPE_USER_LOGIN,
                            null,
                            $user->getPk(),
                            $user->getUiLanguageId(),
                            $user
                        );

                        // Migrate unclaimed guest coins to user.
                        if ($guestCoinsManager->checkGuestHasUnclaimedCoins($request, $guest->getGuestId())) {

                            $conn = $request->db->get_connection(SQLN_SITE);

                            $conn->begin();
                            $userCoinsManager->migrateUnclaimedGuestCoins($request, $conn, $guest->getGuestId(), $newUser->getId());
                            $conn->commit();

                        }

                        return $this->renderJsonResponse($form->renderJson($user, $this->renderJsonModel($request)));
                    }
                } catch (ObjectNotFound $e) {
                    $loginAttemptsManager->recordFailedLogin($request, $email);
                    $message = $request->translations->lookup(T::AUTH_LOGIN_INCORRECT_CREDENTIALS);
                    $form->set_error($message);
                }
            }
        }

        return $this->renderJsonResponse($form->renderJson($user, $this->renderJsonModel($request)), HttpResponse::HTTP_BAD_REQUEST);
    }

    /**
     * @param Request $request
     * @return HtmlResponse|JSONResponse
     */
    protected function handle_get_highscores(Request $request)
    {
        $hostsManager = $request->managers->hosts();
        $gamesPlayersStatsManager = $request->managers->gamesPlayersStats();

        if (!$hostSlug = $request->getSlugForEntity($this->url_key+1))
            return $this->render_404($request, 'Host Missing');

        if (!$host = $hostsManager->getHostBySlug($request, $hostSlug))
            return $this->render_404($request, 'Host Not Found');

        if (!$host->getOfflineGameId())
            return $this->render_404($request, 'No Offline Game Set');

        $dt = new DateTime();

        $leaderBoardStats = $gamesPlayersStatsManager->getLeaderboardForGameHost(
            $request,
            $host->getPk(),
            $host->getOfflineGameId(),
            GamesPlayersStatsTypesManager::TYPE_HIGH_SCORE,
            $dt->modify("-1 week")->format(SQL_DATETIME)
        );

        return $this->renderJsonResponse($leaderBoardStats);
    }

    /**
     * @param Request $request
     * @return JSONResponse
     */
    protected function handle_track_highscore(Request $request)
    {
        $gamesPlayersStatsManager = $request->managers->gamesPlayersStats();

        $fields = [
            new IntegerField(DBField::GAME_ID, "Game Id", true),
            new IntegerField(DBField::GAME_BUILD_ID, "Game Build Id", true),
            new IntegerField(DBField::GAME_CONTROLLER_ID, "Game Build Id", false),
            new IntegerField(DBField::HOST_ID, "Host Id", true),
            new CharField(DBField::NAME, "Name", 10, true),
            new FloatField(DBField::VALUE, "Highscore Value", true),
            new IntegerField(VField::CUSTOM_GAME_ASSET_ID, "Custom Game Asset Id", false)
        ];

        $form = new PostForm($fields, $request);

        if ($form->is_valid()) {

            $gameId = $form->getCleanedValue(DBField::GAME_ID);
            $gamePlayerStatTypeId = GamesPlayersStatsTypesManager::TYPE_HIGH_SCORE;
            $gameBuildId = $form->getCleanedValue(DBField::GAME_BUILD_ID);
            $gameControllerId = $form->getCleanedValue(DBField::GAME_CONTROLLER_ID);
            $hostId = $form->getCleanedValue(DBField::HOST_ID);
            $guestId = $request->user->guest->getGuestId();
            $userId = $request->user->is_authenticated() ? $request->user->id : null;
            $name = $form->getCleanedValue(DBField::NAME);
            $name = str_replace('null', '', $name);
            $createTime = $request->getCurrentSqlTime();
            $value = $form->getCleanedValue(DBField::VALUE);
            $gameInstanceRoundPlayerId = $form->getCleanedValue(DBField::GAME_INSTANCE_ROUND_PLAYER_ID);
            $contextXGameAssetId = $form->getCleanedValue(VField::CUSTOM_GAME_ASSET_ID);

            $highScore = $gamesPlayersStatsManager->createNewGamePlayerStat(
                $request,
                $gameId,
                $gameBuildId,
                $gameControllerId,
                $gamePlayerStatTypeId,
                $hostId,
                $guestId,
                $userId,
                $name,
                $value,
                $contextXGameAssetId,
                $gameInstanceRoundPlayerId,
                $createTime
            );

            return $this->renderJsonResponse($form->renderJson($highScore->getJSONData()));

        } else {
            $this->renderJsonResponse($form->renderJson(), HttpResponse::HTTP_BAD_REQUEST);
        }

        return $this->renderJsonResponse($form->renderJson(), HttpResponse::HTTP_OK);
    }
}
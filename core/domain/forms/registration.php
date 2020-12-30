<?php

use \libphonenumber\PhoneNumberUtil;

class RegistrationForm extends PostForm
{
    protected $next;
    /** @var Request  */
    protected $request;

    public function __construct($fields, Request $request, $data = null)
    {
        $this->db = $request->db;
        $this->request = $request;
        $this->translations = $request->translations;
        parent::__construct($fields, $request, $data);
    }

    public function setNextUrl($url)
    {
        $this->next = $url;
    }

    /**
     * @param Request $request
     * @return bool
     */
    public function is_valid()
    {
        if (!$this->is_post)
            return false;

        $request = $this->request;

        // Default form validation
        $is_valid = parent::is_valid();

        $usersManager = $request->managers->users();

//        if ($usersManager->checkUsernameExists($request, $this->getCleanedValue(FormField::USERNAME))) {
//            $this->set_error($this->translations['Username already in use.']);
//            $is_valid = false;
//        }

        if ($usersManager->checkEmailExists($request, $this->getCleanedValue(FormField::EMAIL))) {
            $this->set_error($this->translations['Email Address is already in use.']);
            $is_valid = false;
        }

        return $is_valid;
    }

    /**
     * @param Request $request
     * @param $translations
     * @return mixed
     * @throws Exception
     */
    public function handleValidVerification(Request $request)
    {
        $translations = $request->translations;

        try {
            $activityManager = $request->managers->activity();
            $usersManager = $request->managers->users();
            $userCoinsManager = $request->managers->userCoins();
            $guestCoinsManager = $request->managers->guestCoins();

            $phoneNumber = $this->getCleanedValue(DBField::PHONE_NUMBER);
            $email = strtolower($this->getCleanedValue(FormField::EMAIL));
            $passwordChecksum = $request->auth->compute_password_hash($this->getCleanedValue(FormField::PASSWORD_HASH));

            $conn = $request->db->get_connection(SQLN_SITE);
            $conn->begin();

            $userId = $usersManager->createNewUser(
                $request,
                $email,
                $phoneNumber,
                $passwordChecksum,
                null, // Zip Code
                null, // First Name
                null, // Last Name
                null, // Display Name
                null, // Birthday
                1, // Gender (not set)
                1, // Beta Access
                0, // Is verified
                null, // Username
                null // Host Slug
            );

            $conn->commit();

            $request->auth->makeCookies($request, $userId);
            $request->user->id = $userId;
            $request->user->username = $email;
            $request->user->is_authenticated = true;

            $user = $request->managers->users()->getUserById($request, $userId);

            $request->managers->emailSettings()->registerUserForEmails($request, $user);

            $activity = $activityManager->trackActivity(
                $request,
                ActivityTypesManager::ACTIVITY_TYPE_USER_REGISTRATION,
                null,
                $userId,
                $user->getUiLanguageId(),
                $user
            );

            $checksum = $request->auth->computeActivationChecksum($user->getJoinDate(), $request->settings()->getSecret());

            Modules::load_helper(Helpers::EMAIL);

            $emailGenerator = new EmailGenerator(
                $request,
                $user->getEmailAddress(),
                EmailTypesManager::TYPE_SYSTEM_REGISTRATION_CONFIRMATION,
                $checksum,
                $activity->getPk()
            );

            $emailGenerator->assignViewData(['hashed_email' => base64_encode($user->getEmailAddress())]);

            $emailGenerator->setRecipientUser($user);

            try {

                $emailGenerator->sendEmail();

            } catch (Exception $e) {

                throw $e;
            }

            $guest = $request->user->guest;

            // Migrate unclaimed guest coins to user.
            if ($guestCoinsManager->checkGuestHasUnclaimedCoins($request, $guest->getGuestId())) {

                $conn = $request->db->get_connection(SQLN_SITE);

                $conn->begin();
                $userCoinsManager->migrateUnclaimedGuestCoins($request, $conn, $guest->getGuestId(), $user->getPk());
                $conn->commit();

            }


            if ($this->getCleanedValue(FormField::NEXT))
                $this->next = $next = urldecode(base64_decode($this->getCleanedValue(FormField::NEXT)));

            return true;

        } catch (DBDuplicateKeyException $e) {
            $this->set_error($translations['Username already in use.']);
        }

        return false;
    }

    /**
     * @return mixed
     */
    public function getNextUrl()
    {
        return $this->next;
    }
}

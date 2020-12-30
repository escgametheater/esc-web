<?php
/**
 * Users Manager
 *
 * @package managers
 */

// Core - Users and Accounts
Entities::uses("users");

use \libphonenumber\PhoneNumberUtil;

class UsersManager extends BaseEntityManager
{
    protected $entityClass = UserEntity::class;

    protected $table = Table::Users;
    protected $table_alias = TableAlias::Users;
    protected $pk = DBField::USER_ID;

    const GOOGLE_SSO_PASSWORD_HASH = 'GoogleSSO';

    protected $foreign_managers = [
        UserProfilesManager::class => DBField::USER_ID
    ];

    public static $fields = [
        DBField::USER_ID,
        DBField::USERNAME,
        DBField::EMAIL_ADDRESS,
        DBField::PHONE_NUMBER,
        DBField::JOIN_DATE,
        DBField::LAST_LOGIN,
        DBField::USERGROUP_ID,
        DBField::IS_VERIFIED,
        DBField::GEO_REGION_ID,
        DBField::SALT
    ];


    public $removed_json_fields = [
        DBField::SALT,
        DBField::JOIN_DATE,
        DBField::LAST_LOGIN,
        DBField::USERGROUP_ID,
        DBField::GEO_REGION_ID,
        DBField::FIRST_SESSION_ID,
        DBField::BIRTHDAY,
        DBField::TOTAL_ORDERS,
        DBField::TOTAL_TIME_PLAYED,
    ];

    const ANONYMOUS_USER_ID = 0;
    const ANONYMOUS_USER_NAME = 'Anonymous';

    // User Entity + Settings Cache Times
    const USER_CACHE_TIME = 900; // Fifteen minutes - User Entity

    const GNS_KEY_PREFIX = GNS_ROOT.'.users'; // Used to identify cache key tree value store prefix


    /**
     * @param UserEntity $data
     * @param Request $request
     * @return mixed
     */
    public function processVFields(DBManagerEntity $data, Request $request)
    {
        $avatarPath = $request->settings()->getPlaceHolderAvatarPath();

        $data[VField::AVATAR_URL] = $request->getWwwUrl($avatarPath);
        $data[VField::AVATAR_SMALL_URL] = $request->getWwwUrl($avatarPath);
        $data[VField::AVATAR_TINY_URL] = $request->getWwwUrl($avatarPath);
        $data[VField::HAS_AVATAR] = false;

        if (!$data->hasField(VField::AVATAR))
            $data->updateField(VField::AVATAR, []);

        if ($data->getPk()) {
            $adminEditUrl = $request->getWwwUrl("/admin/users/manage/{$data->getPk()}");

        } else {
            $adminEditUrl = null;
        }

        $data[VField::EDIT_URL] = $request->getWwwUrl('/account/');

        $data[VField::ADMIN_EDIT_URL] = $adminEditUrl;

        return $data;
    }

    /**
     * @param Request $request
     * @param $users
     */
    public function postProcessUsers(Request $request, $users)
    {
        $imagesManager = $request->managers->images();

        if ($users) {
            if ($users instanceof UserEntity)
                $users = [$users];

            /** @var UserEntity[] $users */
            $users = array_index($users, $this->getPkField());
            $userIds = array_keys($users);

            $avatarImages = $imagesManager->getActiveUserAvatarImagesByUserIds($request, $userIds);
            foreach ($avatarImages as $avatarImage) {
                $users[$avatarImage->getContextEntityId()]->setAvatarImage($avatarImage);
            }
        }
    }

    /**
     * @param Request $request
     * @return UserEntity
     */
    public function generateAnonymousUser(Request $request)
    {
        /** @var UserEntity $user */
        $user = $this->createNulledEntity($request);

        $userData = [
            DBField::USER_ID => self::ANONYMOUS_USER_ID,
            DBField::USERNAME => self::ANONYMOUS_USER_NAME,
            DBField::EMAIL_ADDRESS => '',
            DBField::PHONE_NUMBER => '',
            DBField::IS_VERIFIED => 0,
            DBField::DISPLAY_NAME => self::ANONYMOUS_USER_NAME,
            DBField::FIRST_NAME => '',
            DBField::LAST_NAME => '',
            DBField::GENDER_ID => 0,
            DBField::HAS_BETA_ACCESS => 0,
            DBField::UI_LANGUAGE_ID => LanguagesManager::LANGUAGE_ENGLISH,
        ];

        $user->assign($userData);

        return $user;
    }

    /**
     * @param Request $request
     * @return FormField[]
     */
    public function getLoginFields(Request $request, $api = false)
    {
        $fields = [
            new EmailField(FormField::EMAIL, $request->translations['Email'], true, null, $request->translations['Email Address'], 'forms/custom-fields/auth-email-field.twig'),
            new HiddenField(FormField::PASSWORD_HASH, 'Password hash')
        ];

        if (!$api)
            $fields = array_merge($fields, [
                new PasswordField(FormField::PASSWORD, $request->translations['Password'], true, null, $request->translations['Password'], 'forms/custom-fields/auth-password-field.twig'),
                //new BooleanField(FormField::REMEMBER_ME, $request->translations['Keep me signed in']),
                new HiddenField(FormField::NEXT, 'Next', 0, false)
            ]);

        return $fields;
    }

    /**
     * @param Request $request
     * @param UserEntity $user
     * @return PhoneNumberField
     */
    public function getPhoneNumberFormField(Request $request, $countryId)
    {
        return new PhoneNumberField(
            DBField::PHONE_NUMBER,
            $request->translations['Cell Phone'],
            $countryId,
            true,
            'Your phone number is used for notification purposes only.',
            'Ex: 555-555-5555'
        );
    }

    /**
     * @param Request $request
     * @return SlugField
     */
    public function getUserNameFormField(Request $request)
    {
        return new SlugField(
            DBField::USERNAME,
            $request->translations['Username'],
            16,
            true,
            $request->translations->lookup(T::ACCOUNT_HELP_TEXT_USERNAME),
            $request->translations['Username']
        );
    }

    /**
     * @param Request $request
     * @return EmailField
     */
    public function getEmailAddressFormField(Request $request)
    {
        return new EmailField(
            DBField::EMAIL_ADDRESS,
            $request->translations['Email Address'],
            true,
            'The email address is used for account login and communications.',
            'Ex: johndoe01@gmail.com'
        );
    }

    /**
     * @param Request $request
     * @param UserEntity $user
     * @return FormField[]
     */
    public function getRoadBlockFormFields(Request $request, UserEntity $user)
    {
        $fields = [];

        if (!$user->getPhoneNumber())
            $fields[] = $this->getPhoneNumberFormField($request, $user->getCountryId());

        if (!$user->getEmailAddress())
            $fields[] = $this->getEmailAddressFormField($request);

//        if (!$user->getUsername())
//            $fields[] = $this->getUserNameFormField($request);

        return $fields;
    }

    /**
     * @param Request $request
     * @return FormField[]
     */
    public function getFormFields(Request $request, $countryId = null)
    {
        $languagesManager = $request->managers->languages();
        $userGroupsManager = $request->managers->userGroups();

        $uiLanguages = $languagesManager->getActiveI18nLanguages($request);

        $translations = $request->translations;
        $permissions = $request->user->permissions;

        if (!$countryId || $countryId == '-')
            $countryId = CountriesManager::ID_UNITED_STATES;

        $fields = [
            $this->getUserNameFormField($request),
            $this->getPhoneNumberFormField($request, $countryId),

            new CharField(
                DBField::FIRST_NAME,
                $translations['First name'],
                32,
                true,
                'The first name is your given name.',
                $translations['First name']
            ),
            new CharField(
                DBField::LAST_NAME,
                $translations['Last name'],
                40,
                true,
                'The last name is your family name.',
                $translations['Last name']
            ),
        ];

        if (count($uiLanguages) > 1) {
            $fields[] = new SelectField(
                DBField::UI_LANGUAGE_ID,
                $translations->lookup(T::ACCOUNT_DESCRIPTION_UI_LANGUAGE),
                $uiLanguages,
                true,
                $translations->lookup(T::ACCOUNT_HELP_TEXT_UI_LANGUAGE)
            );
        } else {
            $fields[] = new HiddenField(
                DBField::UI_LANGUAGE_ID,
                $translations->lookup(T::ACCOUNT_DESCRIPTION_UI_LANGUAGE),
                2,
                true,
                $translations->lookup(T::ACCOUNT_HELP_TEXT_UI_LANGUAGE)
            );
        }

        // Moderation Rights Fields
        if ($permissions->has(RightsManager::RIGHT_USERS, Rights::MODERATE)) {

            $fields[] = new BooleanField(
                DBField::HAS_BETA_ACCESS,
                $translations->lookup(T::ACCOUNT_DESCRIPTION_BETA),
                false,
                $translations->lookup(T::ACCOUNT_HELP_TEXT_BETA)
            );
        }

        // Admin Rights Fields
        if ($permissions->has(RightsManager::RIGHT_USERS, Rights::ADMINISTER)) {

            $userGroups = $userGroupsManager->getAllActiveUserGroups($request);

            $fields[] = new SelectField(DBField::USERGROUP_ID, 'Primary User Group', $userGroups, true, 'Choose the primary user-group for this account.');
        }

        return $fields;
    }

    /**
     * @param Request $request
     * @return FormField[]
     */
    public function getAdminFormFields(Request $request, $countryId = null)
    {
        $languagesManager = $request->managers->languages();
        $userGroupsManager = $request->managers->userGroups();

        $uiLanguages = $languagesManager->getActiveI18nLanguages($request);

        $translations = $request->translations;
        $permissions = $request->user->permissions;

        if (!$countryId || $countryId == '-')
            $countryId = CountriesManager::ID_UNITED_STATES;

        $fields = [
            $this->getUserNameFormField($request),
            $this->getPhoneNumberFormField($request, $countryId),
            $this->getEmailAddressFormField($request),

            new CharField(
                DBField::FIRST_NAME,
                $translations['First name'],
                32,
                false,
                'The first name is your given name.',
                $translations['First name']
            ),
            new CharField(
                DBField::LAST_NAME,
                $translations['Last name'],
                40,
                false,
                'The last name is your family name.',
                $translations['Last name']
            ),
        ];

        if (count($uiLanguages) > 1) {
            $fields[] = new SelectField(
                DBField::UI_LANGUAGE_ID,
                $translations->lookup(T::ACCOUNT_DESCRIPTION_UI_LANGUAGE),
                $uiLanguages,
                true,
                $translations->lookup(T::ACCOUNT_HELP_TEXT_UI_LANGUAGE)
            );
        } else {
            $fields[] = new HiddenField(
                DBField::UI_LANGUAGE_ID,
                $translations->lookup(T::ACCOUNT_DESCRIPTION_UI_LANGUAGE),
                2,
                true,
                $translations->lookup(T::ACCOUNT_HELP_TEXT_UI_LANGUAGE)
            );
        }

        // Moderation Rights Fields
        if ($permissions->has(RightsManager::RIGHT_USERS, Rights::MODERATE)) {

            $fields[] = new BooleanField(
                DBField::HAS_BETA_ACCESS,
                $translations->lookup(T::ACCOUNT_DESCRIPTION_BETA),
                false,
                $translations->lookup(T::ACCOUNT_HELP_TEXT_BETA)
            );
        }

        // Admin Rights Fields
        if ($permissions->has(RightsManager::RIGHT_USERS, Rights::ADMINISTER)) {

            $userGroups = $userGroupsManager->getAllActivePrimaryUserGroups($request);

            $fields[] = new SelectField(DBField::USERGROUP_ID, 'Primary User Group', $userGroups, true, 'Choose the primary user-group for this account.');
        }

        return $fields;
    }

    /**
     * @param Request $request
     * @return FormField[]
     */
    public function getChangePasswordFormFields(Request $request)
    {
        $translations = $request->translations;

        $fields = [
            new PasswordField(DBField::PASSWORD, $translations['Password'], true),
            new PasswordField(FormField::PASSWORD_HASH, 'Password Hash', true),
            new PasswordConfirmField(DBField::PASSWORD_CONFIRM, $translations['Confirm Password'], DBField::PASSWORD)
        ];

        return $fields;
    }

    /**
     * @param Request $request
     * @return FormField[]
     */
    public function getChangeEmailFormFields(Request $request)
    {
        $translations = $request->translations;

        $fields = [
            new EmailField(DBField::EMAIL_ADDRESS, $translations['Email Address'], true, $translations->lookup(T::ACCOUNT_HELP_TEXT_EMAIL_ADDRESS))
        ];

        return $fields;
    }

    /**
     * @param Request $request
     * @return FormField[]
     */
    public function getShakeRegistrationFormFields(Request $request)
    {
        $translations = $request->translations;
        $fields = [
            new CharField(FormField::NAME, $translations['Name'], 255, true, $translations['What is your full name?'], 'Ex: John Doe', 'forms/custom-fields/auth-username-field.twig'),
            new CharField(VField::ORGANIZATION, $translations['Company'], 255, true, $translations['Which company do you represent?'], 'Ex: Acme Entertainment'),
            new CharField(FormField::TITLE, $translations['Job Title'], 255, true, $translations['What is your role at this company?'], 'Ex: Account Executive'),
            new EmailField(FormField::EMAIL_ADDRESS, $translations['Email Address'], true, $translations['Your email is used to log in to our platform.'], 'Ex: john.doe@example.com', 'forms/custom-fields/auth-email-field.twig'),
            new PhoneNumberField(DBField::PHONE_NUMBER, $translations['Cell Phone'], 'us', true, $translations['Your phone number is only used for system notifications.'], 'Ex: 555-555-5555', 'forms/custom-fields/auth-username-field.twig'),
            new HiddenField(FormField::NEXT, $translations['Next'], 0, false),
        ];

        return $fields;
    }

    /**
     * @param Request $request
     * @param UserEntity $user
     * @param float|int $expiration
     * @return array
     */
    public function generateMagicLoginUrlParamsForUser(Request $request, UserEntity $user, $expiration, $path = null)
    {
        $rawString = "{$request->settings()->getSecret()}<->{$user->getPhoneNumber()}<->{$user->getEmailAddress()}<->{$user->getSalt()}<->{$path}<->{$expiration}";

        $checksum = sha1($rawString);

        $params = [
            GetRequest::PARAM_MAGIC => $checksum,
            GetRequest::PARAM_EXP => $expiration,
            GetRequest::PARAM_IDENT => base64_encode($user->getEmailAddress())
        ];

        if ($path)
            $params[GetRequest::PARAM_PATH] = 1;

        return $params;
    }


    /**
     * @param $user
     * @return array
     */
    public static function bustBasicCache($user)
    {
        $cache_keys = [];
        $cache_keys[] = self::generateEntityIdCacheKey($user);
        $cache_keys[] = self::generateEntityStringCacheKey($user);
        Cache::deleteKeys($cache_keys);
        return $cache_keys;
    }

    /**
     * @param $user
     * @param null $lang
     * @return array
     */
    public static function wipeUserCache($user, $lang = null)
    {
        $all_cache_keys = [];
        return $all_cache_keys;
    }


    /**
     * @param $user
     * @return string
     */
    public static function generateEntityIdCacheKey($user)
    {
        if (is_string($user) || is_int($user))
            $user = [DBField::USER_ID => $user];

        return UsersManager::GNS_KEY_PREFIX.'.'.$user[DBField::USER_ID];
    }

    /**
     * @param $user
     * @return string
     */
    public static function generateEntityStringCacheKey($user)
    {
        if (is_string($user))
            $user = [DBField::USERNAME => $user];
        return UsersManager::GNS_KEY_PREFIX.'.'.$user[DBField::USERNAME];
    }

    /**
     * @param Request $request
     * @param $emailAddress
     * @return array
     */
    public function getUserLoginDataByEmail($emailAddress)
    {
        return $this->query()
            ->filter($this->filters->byEmailAddress($emailAddress))
            ->get(DBField::USER_ID, DBField::USERNAME, DBField::SALT, DBField::PASSWORD);
    }

    /**
     * @param Request $request
     * @return SQLQuery
     */
    protected function queryJoinUserProfiles(Request $request)
    {
        $usersProfilesManager = $request->managers->usersProfiles();

        $fields = array_merge($this->createDBFields(), $usersProfilesManager->createDBFields(true));

        return $this->query($request->db)
            ->inner_join($usersProfilesManager)
            ->fields($fields);
    }

    /**
     * @param Request $request
     * @param $email
     * @return bool
     */
    public function checkEmailExists(Request $request, $email)
    {
        return $this->query($request->db)
            ->filter($this->filters->byEmailAddress($email))
            ->exists();
    }

    /**
     * @param Request $request
     * @param $username
     * @return bool
     */
    public function checkUsernameExists(Request $request, $username)
    {
        return $this->query($request->db)
            ->bySlug($username)
            ->exists();
    }

    /**
     * @param Request $request
     * @param $firstName
     * @param $lastName
     * @return string
     */
    public function generateNewUserNameFromNames(Request $request, $firstName, $lastName)
    {
        $origUserName = slugify($firstName[0].$lastName);

        $userName = $origUserName;

        $userNameExists = $this->checkUsernameExists($request, $userName);

        $i = 0;

        while ($userNameExists) {
            $i++;
            $userName = "{$origUserName}{$i}";

            $userNameExists = $this->checkUsernameExists($request, $userName);
        }

        return $userName;
    }

    /**
     * @param Request $request
     * @param $id
     * @param int $cache_time
     * @return array|UserEntity
     */
    public function getUserById(Request $request, $id, $cache_time = UsersManager::USER_CACHE_TIME)
    {
        $user = $this->queryJoinUserProfiles($request)
            ->filter($this->filters->byPk($id))
            ->cache($this->generateEntityIdCacheKey($id), $cache_time)
            ->get_entity($request);

        $this->postProcessUsers($request, $user);

        return $user;
    }

    /**
     * @param $userIds
     * @param Request $request
     * @return UserEntity[]
     */
    public function getUsersByIds(Request $request, $userIds)
    {
        foreach ($userIds as $key => $value)
            if ($value === 0 || $value === "0")
                unset($userIds[$key]);

        $queryBuilder = $this->queryJoinUserProfiles($request)
            ->filter($this->filters->byPk($userIds));

        $users = $this->getEntitiesByPks($request, $userIds, self::USER_CACHE_TIME, $queryBuilder);

        if ($users)
            $users = array_index($users, $this->getPkField());

        $this->postProcessUsers($request, $users);

        return $users;
    }

    /**
     * @param Request $request
     * @param $username
     * @param int $cache_time
     * @param bool|true $fail_if_not_found
     * @return UserEntity
     */
    public function getUserByUsername(Request $request, $username, $cache_time = DONT_CACHE, $fail_if_not_found = true)
    {
        return $this->queryJoinUserProfiles($request)
            ->filter($this->filters->Eq($this->getSlugField(), $username))
            ->cache($this->generateEntityStringCacheKey($username), $cache_time)
            ->get_entity($request, !$fail_if_not_found);
    }

    public function getUsers(Request $request, $page = 1, $perPage = 40)
    {

    }

    /**
     * @param Request $request
     * @return UserEntity[]
     */
    public function getAllUsers(Request $request)
    {
        return $this->queryJoinUserProfiles($request)->get_entities($request);
    }

    /**
     * @param Request $request
     * @param $emailAddress
     * @return array|UserEntity
     */
    public function getUserByEmailAddress(Request $request, $emailAddress, $fetchAvatar = false)
    {
        $user = $this->queryJoinUserProfiles($request)
            ->filter($this->filters->byEmailAddress(strtolower($emailAddress)))
            ->get_entity($request);

        if ($fetchAvatar)
            $this->postProcessUsers($request, $user);

        return $user;
    }

    /**
     * @param Request $request
     * @param $phoneNumber
     * @return array|UserEntity
     */
    public function getUserByPhoneNumber(Request $request, $phoneNumber)
    {
        return $this->queryJoinUserProfiles($request)
            ->filter($this->filters->byPhoneNumber($phoneNumber))
            ->sort_desc(DBField::USER_ID)
            ->get_entity($request);
    }

    /**
     * @param Request $request
     * @param $phoneNumber
     * @return null|int
     */
    public function getUserIdByPhoneNumber(Request $request, $phoneNumber)
    {
        try {
            $userId = (int) $this->query($request->db)
                ->filter($this->filters->byPhoneNumber($phoneNumber))
                ->get_value(DBField::PHONE_NUMBER);
        } catch (ObjectNotFound $e) {
            $userId = null;
        }

        return $userId;
    }


    /**
     * @param Request $request
     * @param $ssoServiceId
     * @param $ssoAccountId
     * @return array|UserEntity
     */
    public function getUserBySsoServiceAccountId(Request $request, $ssoServiceId, $ssoAccountId, $fetchAvatar = false)
    {
        $userSsoServicesManager = $request->managers->userSSOServices();

        $joinUserSsoServicesFilter = $this->filters->And_(
            $userSsoServicesManager->filters->bySsoServiceId($ssoServiceId),
            $userSsoServicesManager->filters->bySsoAccountId($ssoAccountId),
            $userSsoServicesManager->filters->byUserId($this->createPkField())
        );

        $user = $this->queryJoinUserProfiles($request)
            ->inner_join($userSsoServicesManager, $joinUserSsoServicesFilter)
            ->filter($userSsoServicesManager->filters->isActive())
            ->get_entity($request);

        if ($fetchAvatar)
            $this->postProcessUsers($request, $user);

        return $user;
    }

    /**
     * @param Request $request
     * @param UserEntity $user
     */
    public function verifyUser(Request $request, UserEntity $user)
    {

        $verificationData = [
            DBField::IS_VERIFIED => 1,
            DBField::USERGROUP_ID => UserGroupsManager::GROUP_ID_REGISTERED_USERS
        ];

        $this->query($request->db)
            ->filter($this->filters->byPk($user->getPk()))
            ->update($verificationData);

        $user->assign($verificationData);

        self::bustBasicCache($user);
    }


    /**
     * @param Form $form
     * @return string|null
     */
    public function formatPhoneNumber($phoneNumber, $countryId)
    {

        $phoneUtil = PhoneNumberUtil::getInstance();

        try {
            $numberProto = $phoneUtil->parse($phoneNumber, $countryId);
            $phoneNumber = $phoneUtil->format($numberProto, \libphonenumber\PhoneNumberFormat::E164);
        } catch (\libphonenumber\NumberParseException $e) {
            $phoneNumber = null;
        }

        return $phoneNumber;
    }


    /**
     * @param Request $request
     * @param UserEntity $user
     */
    public function updateUserData(Request $request, UserEntity $user)
    {
        $usersProfilesManager = $request->managers->usersProfiles();

        if ($updatedUserData = $this->getUpdatedDbFieldsFromEntity($user)) {
            $this->query($request->db)
                ->filter($this->filters->byPk($user->getPk()))
                ->update($updatedUserData);
        }
        if ($updatedProfileData = $usersProfilesManager->getUpdatedDbFieldsFromEntity($user)) {
            $usersProfilesManager->query($request->db)
                ->filter($usersProfilesManager->filters->byPk($user->getPk()))
                ->update($updatedProfileData);
        }

        if ($user->needs_save()) {
            $user->bustBasicCache();
        }
    }

    /**
     * @param Request $request
     * @param UserEntity $user
     * @param PostForm $form
     */
    public function updateUserDataFromForm(Request $request, UserEntity $user, PostForm $form)
    {
        $usersProfilesManager = $request->managers->usersProfiles();

        $permissions = $request->user->permissions;

        $user->assignByForm($form);

        $user->updateField(DBField::PHONE_NUMBER, $this->formatPhoneNumber($form->getCleanedValue(DBField::PHONE_NUMBER), $user->getCountryId()));

        $displayName = "{$form->getCleanedValue(DBField::FIRST_NAME)} {$form->getCleanedValue(DBField::LAST_NAME)}";

        if ($permissions->has(RightsManager::RIGHT_USERS, Rights::MODERATE) && $form->has_field(DBField::HAS_BETA_ACCESS)) {
            $hasBetaAccess = $form->getCleanedValue(DBField::HAS_BETA_ACCESS, 0);
            if ($hasBetaAccess)
                $hasBetaAccess = 1;

            $user->updateField(DBField::HAS_BETA_ACCESS, $hasBetaAccess);
        }

        $user->assign([
            DBField::DISPLAY_NAME => $displayName
        ]);


        // Admin Edit Fields
        if ($permissions->has(RightsManager::RIGHT_USERS, Rights::ADMINISTER) && $form->has_field(DBField::USERGROUP_ID)) {
            $hasBetaAccess = $form->getCleanedValue(DBField::USERGROUP_ID);
            $user->updateField(DBField::USERGROUP_ID, $hasBetaAccess);
        }

        $this->updateUserData($request, $user);
    }

    /**
     * @param Request $request
     * @param null $emailAddress
     * @param null $phoneNumber
     * @param null $passwordChecksum
     * @return int
     */
    public function createNewUser(Request $request, $emailAddress = null, $phoneNumber = null, $passwordChecksum = null,
                                  $zipCode = null, $firstName = null, $lastName = null, $displayName = null, $birthday = null,
                                  $genderId = 1, $betaAccess = 0, $isVerified = 0, $userName = null, $hostSlug = null)
    {
        $usersProfilesManager = $request->managers->usersProfiles();
        $addressesManager = $request->managers->addresses();
        $locationsManager = $request->managers->locations();
        $hostsManager = $request->managers->hosts();
        $screensManager = $request->managers->screens();
        $networksManager = $request->managers->networks();

        $conn = $request->db->get_connection(SQLN_SITE);

        if ($isVerified) {
            $userGroupId = UserGroupsManager::GROUP_ID_REGISTERED_USERS;
        } else {
            $userGroupId = UserGroupsManager::GROUP_ID_UNCONFIRMED_USERS;
        }

        $countryId = $request->user->session->getCountryId();

        if (!$countryId || $countryId == '-')
            $countryId = CountriesManager::ID_UNITED_STATES;

        // Ensure we have normalize phone numbers on input.
        if ($phoneNumber) {
            $phoneNumberUtils = PhoneNumberUtil::getInstance();

            try {
                $numberProto = $phoneNumberUtils->parse($phoneNumber, strtoupper($countryId));
                $phoneNumber = $phoneNumberUtils->format($numberProto, \libphonenumber\PhoneNumberFormat::E164);
            } catch (\libphonenumber\NumberParseException $e) {
                $phoneNumber = null;
            }
        }

        $userData = [
            FormField::USERNAME => $userName,
            FormField::PHONE_NUMBER => $phoneNumber,
            FormField::PASSWORD => $passwordChecksum,
            FormField::EMAIL_ADDRESS => $emailAddress,
            FormField::USERGROUP_ID => $userGroupId,
            FormField::SALT => $request->auth->generate_user_salt(),
            FormField::GEO_REGION_ID => $request->geoRegion->getPk(),
            FormField::IS_VERIFIED => $isVerified
        ];

        $userId = $this->query()->set_connection($conn)->add($userData);

        $userProfileData = [
            DBField::USER_ID => $userId,
            DBField::DISPLAY_NAME => $displayName,
            DBField::FIRST_NAME => $firstName,
            DBField::LAST_NAME => $lastName,
            DBField::BIRTHDAY => $birthday,
            DBField::COUNTRY_ID => $countryId,
            DBField::UI_LANGUAGE_ID => $request->translations->get_lang(),
            DBField::GENDER_ID => $genderId,
            DBField::HAS_BETA_ACCESS => $betaAccess,
            DBField::TOTAL_ORDERS => 0,
            DBField::TOTAL_TIME_PLAYED => '00:00:00',
            DBField::FIRST_SESSION_ID => $request->user->session->getSessionId()
        ];

        $usersProfilesManager->query()->set_connection($conn)->add($userProfileData);

        if ($request->geoIpMapping->getPk()) {
            $geoIpMap = $request->geoIpMapping;
            $city = $geoIpMap->getCityName();
            $state = $geoIpMap->getRegionName();
        } else {
            $city = null;
            $state = null;
        }

        $address = $addressesManager->createNewAddress(
            $request,
            EntityType::USER,
            $userId,
            $countryId,
            AddressesTypesManager::ID_PRIVATE,
            1,
            null,
            null,
            null,
            $phoneNumber,
            null,
            null,
            null,
            $city,
            $state,
            $zipCode
        );

        $host = $hostsManager->createNewHost(
            $request,
            EntityType::USER,
            $userId,
            null,
            $hostSlug
        );

        $network = $networksManager->createNewNetwork($request, $host->getPk());
        $screen = $screensManager->createNewScreen($request, $host->getPk(), $network->getPk());

        return $userId;
    }

    /**
     * @param Request $request
     * @param $userId
     * @return string
     */
    public function getUserPasswordHash(Request $request, $userId)
    {
        try {
            $passwordHash = $this->query($request->db)
                ->filter($this->filters->byPk($userId))
                ->get_value(DBField::PASSWORD);
        } catch (ObjectNotFound $e) {
            $passwordHash = null;
        }

        return $passwordHash;
    }

    /**
     * @param Request $request
     * @param $userId
     * @param $passwordHash
     */
    public function updatePasswordForUser(Request $request, $userId, $passwordHash)
    {
        $this->query($request->db)
            ->filter($this->filters->byPk($userId))
            ->update([DBField::PASSWORD => $passwordHash]);
    }

    /**
     * @param int $characterLength
     * @return string
     */
    public function generateNewUserPassword($characterLength = 12)
    {
        // create random password
        $new_pw_length = $characterLength;
        $low_alpha = "abcdefghijklmnopqrstuvwxyz";
        $up_alpha = strtoupper($low_alpha);
        $num = "0123456789";
        $chars = $low_alpha . $up_alpha . $num;
        $chars_len = strlen($chars);

        $new_pw = "";
        for ($i = 0; $i < $new_pw_length; $i++) {
            $new_pw = $new_pw . substr($chars, rand(0, $chars_len - 1), 1);
        }

        return $new_pw;
    }


    /**
     * @param Request $request
     * @param UserEntity $user
     * @param ActivityEntity $activity
     * @param null $checksum
     * @throws Exception
     */
    public function generateAndSendNewUserPassword(Request $request, UserEntity $user, ActivityEntity $activity, $checksum = null)
    {
        $emailTrackingManager = $request->managers->emailTracking();

        if (!$checksum)
            $checksum = $emailTrackingManager->generateChecksum();

        $new_pw = $this->generateNewUserPassword(12);

        // make checksum of raw password
        $new_js_hash = $request->auth->compute_password_js_hash($new_pw);
        $new_pw_hash = $request->auth->compute_password_hash($new_js_hash);

        $this->updatePasswordForUser($request, $user->getPk(), $new_pw_hash);

        PasswordResetAttemptsManager::objects($request->db)
            ->filter(Q::Eq(DBField::USER_ID, $user->getPk()))
            ->delete();


        $user['new_password'] = $new_pw;

        Modules::load_helper(Helpers::EMAIL);

        $newPasswordEmail = new EmailGenerator(
            $request,
            $user->getEmailAddress(),
            EmailTypesManager::TYPE_SYSTEM_NEW_PASSWORD,
            $checksum,
            $activity->getPk()
        );

        $newPasswordEmail->setRecipientUser($user);

        $newPasswordEmail->assignViewData([]);

        $newPasswordEmail->sendEmail();
    }
}

class UserProfilesManager extends BaseEntityManager {

    protected $entityClass = UserEntity::class;
    protected $table = Table::UsersProfiles;
    protected $table_alias = TableAlias::UsersProfiles;
    protected $pk = DBField::USER_ID;

    protected $foreign_managers = [
        UsersManager::class => DBField::USER_ID
    ];

    public static $fields = [
        DBField::USER_ID,
        DBField::DISPLAY_NAME,
        DBField::FIRST_NAME,
        DBField::LAST_NAME,
        DBField::BIRTHDAY,
        DBField::COUNTRY_ID,
        DBField::UI_LANGUAGE_ID,
        DBField::GENDER_ID,
        DBField::HAS_BETA_ACCESS,
        DBField::TOTAL_ORDERS,
        DBField::TOTAL_TIME_PLAYED,
        DBField::FIRST_SESSION_ID,
    ];
}

class UsersMetaManager extends BaseEntityManager
{
    protected $entityClass = UserMetaEntity::class;
    protected $table = Table::UserMeta;
    protected $table_alias = TableAlias::UserMeta;
    protected $pk = DBField::USER_META_ID;

    const KEY_COMPANY = 'company';
    const KEY_JOB_TITLE = 'job_title';

    protected $foreign_managers = [
        UsersManager::class => DBField::USER_ID
    ];

    public static $fields = [
        DBField::USER_META_ID,
        DBField::USER_ID,
        DBField::KEY,
        DBField::VALUE,
        DBField::IS_ACTIVE,
        DBField::CREATED_BY,
        DBField::MODIFIED_BY,
        DBField::DELETED_BY
    ];

    /**
     * @param Request $request
     * @param $userId
     * @param $key
     * @return array|UserMetaEntity
     */
    public function getUserMetaByKey(Request $request, $userId, $key)
    {
        return $this->query($request->db)
            ->filter($this->filters->byUserId($userId))
            ->filter($this->filters->byKey($key))
            ->get_entity($request);
    }

    /**
     * @param Request $request
     * @param $userId
     * @return array
     */
    public function getAllUserMetaByUserId(Request $request, $userId)
    {
        $userMeta = $this->query($request->db)
            ->filter($this->filters->byUserId($userId))
            ->get_entities($request);

        if ($userMeta)
            $userMeta = array_index($userMeta, DBField::KEY);

        return $userMeta;
    }

    /**
     * @param Request $request
     * @param $userId
     * @param $key
     * @param $value
     * @return array|UserMetaEntity
     */
    public function createUpdateUserMeta(Request $request, $userId, $key, $value)
    {
        if (!$userMeta = $this->getUserMetaByKey($request, $userId, $key)) {
            $data = [
                DBField::USER_ID => $userId,
                DBField::KEY => $key,
                DBField::VALUE => $value,
                DBField::IS_ACTIVE => 1
            ];

            /** @var UserMetaEntity $userMeta */
            $userMeta = $this->query($request->db)->createNewEntity($request, $data);
        } else {
            $updatedData = [
                DBField::IS_ACTIVE => 1,
                DBField::VALUE => $value
            ];

            $userMeta->assign($updatedData)->saveEntityToDb($request);
        }

        return $userMeta;
    }
}


class LoginAttemptsManager extends BaseEntityManager
{
    protected $entityClass = LoginAttemptEntity::class;
    protected $table = Table::LoginAttempts;
    protected $table_alias = TableAlias::LoginAttempts;

    const LOGIN_TIME_CUT = FIFTEEN_MINUTES;

    /**
     * @param Request $request
     * @param $timecut
     * @return int
     */
    public function getLoginAttemptsCountForIp($ipAddress, $timecut)
    {
        $timeStamp = date(SQL_DATETIME, $timecut);

        return $this->query()
            ->filter($this->filters->byIp($ipAddress))
            ->filter($this->filters->createdAfter($timeStamp, false))
            ->count();
    }

    /**
     * @param $emailAddress
     * @param $timecut
     * @return int
     */
    public function getLoginAttemptsCountForEmail($emailAddress, $timecut)
    {
        $timeStamp = date(SQL_DATETIME, $timecut);

        return $this->query()
            ->filter($this->filters->byEmail($emailAddress))
            ->filter($this->filters->createdAfter($timeStamp, false))
            ->count();
    }

    /**
     * @param Request $request
     * @param null $emailAddress
     * @return bool
     */
    public function checkLoginAllowed(Request $request, $emailAddress = null)
    {
        // Check for too many login attemps
        $timecut = (int) microtime(true) - self::LOGIN_TIME_CUT;

        $ipAttempts = $this->getLoginAttemptsCountForIp($request->user->ip, $timecut);

        if ($emailAddress) {
            $emailAttempts = $this->getLoginAttemptsCountForEmail($emailAddress, $timecut);
        } else {
            $emailAttempts = 0;
        }

        $loginAllowed = ($ipAttempts <= 10 && $emailAttempts <= 15);

        return $loginAllowed;
    }

    /**
     * @param Request $request
     * @param $emailAddress
     */
    public function recordFailedLogin(Request $request, $emailAddress)
    {
        $this->query()->add([
                DBField::IP => $request->user->ip,
                DBField::EMAIL => $emailAddress,
            ]);
    }


    /**
     * @param Request $request
     * @param $email
     */
    public function cleanLoginAttempts(Request $request, $email)
    {
        // Clean attemps history
        $this->query()
            ->filter($this->filters->byIp($request->user->ip))
            ->delete();

        $this->query()
            ->filter($this->filters->byEmail($email))
            ->delete();
    }
}

class PasswordResetAttemptsManager extends BaseEntityManager
{
    protected $entityClass = PasswordResetAttemptEntity::class;
    protected $table = Table::PasswordResetAttempts;
    protected $table_alias = TableAlias::PasswordResetAttempts;
}
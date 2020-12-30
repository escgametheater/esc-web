<?php
/**
 * Email Managers
 *
 * @package managers
 */

Entities::uses("email");

class EmailTypesManager extends BaseEntityManager
{
    protected $entityClass = EmailTypeEntity::class;
    protected $table = Table::EmailTypes;
    protected $table_alias = TableAlias::EmailTypes;
    protected $pk = DBField::EMAIL_TYPE_ID;

    public static $fields = [
        DBField::EMAIL_TYPE_ID,
        DBField::DISPLAY_NAME,
        DBField::EMAIL_SETTING_GROUP_ID
    ];

    const UTM_SOURCE_SYSTEM = 'system';
    const UTM_SOURCE_USER = 'subscriptions';
    const UTM_SOURCE_PROMOTIONS = 'promotions';

    const UTM_MEDIUM_EMAIL = 'email';
    const UTM_MEDIUM_SOCIAL = 'social';
    const UTM_MEDIUM_MENTION = 'mention';
    const UTM_MEDIUM_UNKNOWN = 'unknown';
    const UTM_MEDIUM_INTERNAL = 'internal';
    const UTM_MEDIUM_SEARCH = 'search';
    const UTM_MEDIUM_INVALID = 'invalid';
    const UTM_MEDIUM_REFERRAL = 'referral';

    const UTM_CAMPAIGN_REGISTRATION = 'registration';
    const UTM_CAMPAIGN_WWW = 'www';
    const UTM_CAMPAIGN_ADMIN_INVITE = 'admin-invite';

    const TYPE_SYSTEM_REGISTRATION_CONFIRMATION = 1;
    const TYPE_SYSTEM_REGISTRATION_CONFIRMATION_RESEND = 2;
    const TYPE_SYSTEM_FORGOT_PASSWORD = 3;
    const TYPE_SYSTEM_NEW_PASSWORD = 4;
    const TYPE_USER_HOST_INSTANCE_INVITE = 5;
    const TYPE_USER_GAME_INSTANCE_INVITE = 6;
    const TYPE_SYSTEM_ADMIN_USER_INVITE = 7;
    const TYPE_SYSTEM_REGISTRATION_SHAKE = 8;
    const TYPE_SYSTEM_REGISTRATION_CONFIRMATION_PLAY = 9;
    const TYPE_SYSTEM_ADMIN_TEAM_INVITE = 10;
    const TYPE_SYSTEM_TEAM_USER_INVITE = 11;

    const TYPE_CUSTOMER_CONTACT = 12;

    /**
     * @return string
     */
    public static function generateEmailTypesCacheKey()
    {
        return SettingsManager::GNS_KEY_PREFIX.'.email-types';
    }


    /**
     * @param Request $request
     * @return EmailTypeEntity[]
     */
    public function getAllEmailTypes(Request $request)
    {
        return $this->query($request->db)
            ->local_cache($this->generateEmailTypesCacheKey(), ONE_WEEK)
            ->get_entities($request);
    }

    /**
     * @param Request $request
     * @param $setting_group_id
     * @return EmailTypeEntity[]|array
     */
    public function getEmailTypesBySettingGroupId($setting_group_id, Request $request)
    {
        $all_email_types = $this->getAllEmailTypes($request);
        $email_types = [];

        foreach ($all_email_types as $email_type) {
            if ($email_type->matchField(DBField::SETTING_GROUP_ID, $setting_group_id))
                $email_types[] = $email_type;
        }

        return $email_types;
    }

    /**
     * @param Request $request
     * @param $emailTypeId
     * @return EmailTypeEntity|array
     */
    public function getEmailTypeById(Request $request, $emailTypeId)
    {
        $emailTypes = $this->getAllEmailTypes($request);

        foreach ($emailTypes as $emailType) {
            if ($emailType->getPk() == $emailTypeId)
                return $emailType;
        }
        return [];
    }

}

class EmailSettingsGroupsManager extends BaseEntityManager
{
    protected $entityClass = EmailSettingsGroupEntity::class;
    protected $table = Table::EmailSettingsGroups;
    protected $table_alias = TableAlias::EmailSettingsGroups;
    protected $pk = DBField::EMAIL_SETTING_GROUP_ID;

    public static $fields = [
        DBField::EMAIL_SETTING_GROUP_ID,
        DBField::DISPLAY_NAME,
        DBField::IS_ACTIVE,
        DBField::DEFAULT_SETTING
    ];

    const EMAIL_SETTING_SYSTEM = 1;

    public static $EmailSettings = [
        [EmailSettingsGroupsManager::EMAIL_SETTING_SYSTEM, 'System', 0],
    ];

    public static function codeGetEmailSettingsGroups()
    {
        return self::$EmailSettings;
    }

    public static $AccountCreationSettings = [

    ];

    public static $EmailSubscriptionSettings = [

    ];

    /**
     * @param EmailSettingsGroupEntity $data
     * @param Request $request
     * @return DBManagerEntity
     */
    public function processVFields(DBManagerEntity $data, Request $request)
    {
        return $data;
    }

    public static function generateEmailSettingsGroupsCacheKey()
    {
        return SettingsManager::GNS_KEY_PREFIX.'.email-settings-groups';
    }


    /**
     * @param Request $request
     * @return EmailSettingsGroupEntity[]
     */
    public function getAllSettingGroups(Request $request)
    {
        return $this->query($request->db)
            ->local_cache($this->generateEmailSettingsGroupsCacheKey(), ONE_WEEK)
            ->get_entities($request);
    }

    /**
     * @param Request $request
     * @return EmailSettingsGroupEntity[]
     */
    public function getAllActiveSettingGroups(Request $request)
    {
        $setting_groups = $this->getAllSettingGroups($request);

        $active_setting_groups = [];

        foreach ($setting_groups as $key => $setting_group) {
            if ($setting_group->is_active())
                $active_setting_groups[$setting_group->getPk()] = $setting_group;
        }

        return $active_setting_groups;
    }

    /**
     * @param Request $request
     * @param $emailSettingGroupId
     * @return EmailSettingsGroupEntity|null
     */
    public function getSettingGroupById(Request $request, $emailSettingGroupId)
    {
        $emailSettingsGroups = $this->getAllSettingGroups($request);

        foreach ($emailSettingsGroups as $emailSettingsGroup) {
            if ($emailSettingsGroup->getPk() == $emailSettingGroupId)
                return $emailSettingsGroup;
        }

        return null;
    }

}

class EmailSettingsManager extends BaseEntityManager
{
    protected $entityClass = EmailSettingEntity::class;
    protected $table = Table::EmailSettings;
    protected $table_alias = TableAlias::EmailSettings;
    protected $pk = DBField::EMAIL_SETTING_ID;

    public static $fields = [
        DBField::EMAIL_SETTING_ID,
        DBField::USER_ID,
        DBField::EMAIL_SETTING_GROUP_ID,
        DBField::VALUE,
        DBField::CREATE_TIME
    ];

    const SETTING_OPT_OUT = 0;
    const SETTING_OPT_IN = 1;

    /**
     * @param EmailSettingEntity[] $settings
     * @param Request $request
     * @param UserEntity $user
     * @param ActivityEntity $activity
     * @return bool
     */
    public function saveUpdatedEmailSettings($settings = [], Request $request, UserEntity $user)
    {
        if ($settings) {
            // Start Transaction Sequence
            $request->db->get_connection()->begin();

            $activity = $request->managers->activity()->trackActivity(
                $request,
                ActivityTypesManager::ACTIVITY_TYPE_USER_EMAIL_SETTINGS_UPDATE,
                null,
                $user->getPk(),
                $user->getUiLanguageId(),
                $user
            );

            foreach ($settings as $setting) {
                $setting->saveEntityToDb($request, false);

                $setting_history_data = [
                    DBField::USER_ID => $setting->getUserId(),
                    DBField::SETTING_GROUP_ID => $setting->getEmailSettingGroupId(),
                    DBField::OLD_SETTING => $setting->getOrigValue(DBField::SETTING) ? 1 : 0,
                    DBField::NEW_SETTING => $setting->getSetting(),
                    DBField::ACTIVITY_ID => $activity->getPk()
                ];
                $request->managers->emailSettingsHistory()->query($request->db)->add($setting_history_data);
            }
            // Commit Transactions
            $request->db->get_connection()->commit();

            return true;
        }

        return false;
    }

    /**
     * @param Request $request
     * @param EmailSettingEntity|null $emailSetting
     * @param UserEntity $emailUser
     * @param bool|false $includeSelf
     * @return bool
     */
    public function checkShouldEmail(Request $request, EmailSettingEntity $emailSetting = null, UserEntity $emailUser, $includeSelf = false)
    {
        if ($includeSelf)
            return $emailSetting->is_active() && $emailUser->is_verified();
        else
            return $emailSetting->is_active() && $emailUser->is_verified() && $emailUser->getPk() != $request->user->id;
    }

    /**
     * @param UserEntity $user
     * @param Request $request
     * @param bool|true $wipeCache
     * @return array|EmailSettingEntity|mixed
     */
    public function registerUserForEmails(Request $request, UserEntity $user)
    {
        // Get all active email setting groups
        $emailSettingGroups = $request->managers->emailSettingsGroups()->getAllActiveSettingGroups($request);

        // Start DB Transaction
        $request->db->get_connection()->begin();

        foreach ($emailSettingGroups as $emailSettingGroup) {
            // Check if user does not have this setting (used for re-process existing user for new defaults)
            $exists = $this->checkUserHasEmailSetting($request, $user->getPk(), $emailSettingGroup->getPk());

            if (!$exists)
                $this->initializeEmailSetting($request, $user, $emailSettingGroup);
        }

        // Ensure DB Transaction Commit Happened
        $request->db->get_connection()->commit();

        // Return Newly Created Email Settings and Store in User Entity (and warm cache).
        return $user->getEmailSettings($request);
    }

    /**
     * @param Request $request
     * @param $userId
     * @param $emailSettingGroupId
     * @return bool
     */
    public function checkUserHasEmailSetting(Request $request, $userId, $emailSettingGroupId)
    {
        return $this->query($request->db)
            ->filter($this->filters->byUserId($userId))
            ->filter($this->filters->bySettingGroupId($emailSettingGroupId))
            ->exists();
    }

    /**
     * @param Request $request
     * @param UserEntity $user
     * @param EmailSettingsGroupEntity $emailSettingGroup
     * @return EmailSettingEntity
     */
    public function initializeEmailSetting(Request $request, UserEntity $user, EmailSettingsGroupEntity $emailSettingGroup)
    {
        $emailSettingData = [
            DBField::USER_ID => $user->getPk(),
            DBField::SETTING_GROUP_ID => $emailSettingGroup->getPk(),
            DBField::VALUE => $emailSettingGroup->getDefaultSetting()
        ];

        /** @var EmailSettingEntity $emailSetting */
        $emailSetting = $this->query($request->db)->createNewEntity($request, $emailSettingData);

        return $emailSetting;
    }


    /**
     * @param Request $request
     * @param UserEntity $user
     * @param int $cache_time
     * @return EmailSettingEntity[]
     */
    public function getAllEmailSettingsForUser(Request $request, UserEntity $user)
    {
        return $this->query($request->db)
            ->filter($this->filters->byUserId($user->getPk()))
            ->get_entities($request);
    }

    /**
     * @param $emailTypeId
     * @param Request $request
     * @param UserEntity $user
     * @return EmailSettingEntity|array
     */
    public function getEmailSettingByEmailTypeIdForUser(Request $request, $userId, $emailTypeId)
    {
        $emailTypesManager = $request->managers->emailTypes();

        $emailType = $emailTypesManager->getEmailTypeById($request, $emailTypeId);

        /** @var EmailSettingEntity $userEmailSetting */
        $userEmailSetting = $this->query($request->db)
            ->filter($this->filters->byUserId($userId))
            ->filter($this->filters->bySettingGroupId($emailType->getEmailSettingGroupId()))
            ->get_entity($request);

        return $userEmailSetting;
    }

    /**
     * @param $setting_group_id
     * @param Request $request
     * @param UserEntity $user
     * @return array|EmailSettingEntity
     */
    public function getEmailSettingBySettingGroupIdForUser($setting_group_id, Request $request, UserEntity $user)
    {
        $user_email_settings = $this->getAllEmailSettingsForUser($request, $user);

        foreach ($user_email_settings as $email_setting) {
            if ($email_setting->getEmailSettingGroupId() == $setting_group_id)
                return $email_setting;
        }

        return [];
    }

}

class EmailSettingsHistoryManager extends BaseEntityManager
{
    protected $entityClass = EmailSettingHistoryEntity::class;
    protected $table = Table::EmailSettingsHistory;
    protected $table_alias = TableAlias::EmailSettingsHistory;
    protected $pk = DBField::EMAIL_SETTING_HISTORY_ID;

    public static $fields = [
        DBField::EMAIL_SETTING_HISTORY_ID,
        DBField::EMAIL_SETTING_ID,
        DBField::USER_ID,
        DBField::EMAIL_SETTING_GROUP_ID,
        DBField::VALUE,
        DBField::ACTIVITY_ID,
        DBField::CREATE_TIME
    ];
}


class EmailHistoryManager extends BaseEntityManager {

    protected $entityClass = EmailRecordEntity::class;
    protected $table = Table::EmailHistory;
    protected $table_alias = TableAlias::EmailHistory;
    protected $pk = DBField::EMAIL_HISTORY_ID;

    protected $foreign_managers = [
        EmailTrackingManager::class => DBField::EMAIL_TRACKING_ID
    ];

    public static $fields = [
        DBField::EMAIL_HISTORY_ID,
        DBField::EMAIL_TRACKING_ID,
        DBField::EMAIL_ADDRESS,
        DBField::TITLE,
        DBField::SENDER,
        DBField::BODY,
        DBField::CREATE_TIME
    ];


    /**
     * @param EmailRecordEntity $emailRecord
     * @param bool|true $html
     */
    public function sendEmail(EmailRecordEntity $emailRecord, $html = true)
    {
        TasksManager::add(TasksManager::TASK_ADD_SEND_EMAIL, [
            DBField::FROM => $emailRecord->getSender(),
            DBField::DEST => $emailRecord->getEmailAddress(),
            DBField::TITLE => $emailRecord->getTitle(),
            DBField::BODY  => $emailRecord->getBody(),
            DBField::ET_ID => $emailRecord->getEmailTrackingId(),
            DBField::HTML => $html,
        ]);
    }

    /**
     * @param Request $request
     * @param EmailTrackingEntity $email
     * @param $from
     * @param $title
     * @param $body
     * @return EmailRecordEntity
     */
    public function createNewEmail(Request $request, EmailTrackingEntity $email, $from, $title, $body)
    {
        $emailData = [
            DBField::EMAIL_TRACKING_ID => $email->getPk(),
            DBField::EMAIL_ADDRESS => $email->getEmailAddress(),
            DBField::TITLE => $title,
            DBField::SENDER => $from,
            DBField::BODY => $body
        ];

        return $this->query()->createNewEntity($request, $emailData);
    }

    /**
     * @param Request $request
     * @param $etId
     * @return EmailRecordEntity
     * @throws ObjectNotFound
     */
    public function getEmailRecordByEmailTrackingId(Request $request, $etId)
    {
        return $this->query($request->db)
            //->inner_join($emailTrackingManager)
            ->filter($this->filters->byEmailTrackingId($etId))
            ->get_entity($request);
    }
}

class EmailTrackingManager extends BaseEntityManager {

    protected $entityClass = EmailTrackingEntity::class;
    protected $table = Table::EmailTracking;
    protected $table_alias = TableAlias::EmailTracking;
    protected $pk = DBField::EMAIL_TRACKING_ID;

    public static $fields = [
        DBField::EMAIL_TRACKING_ID,
        DBField::EMAIL_TYPE_ID,
        DBField::EMAIL_ADDRESS,
        DBField::CHECKSUM,
        DBField::LANGUAGE_ID,
        DBField::USER_ID,
        DBField::IS_OPENED,
        DBField::IS_CLICKED,

        DBField::ACQ_SOURCE,
        DBField::ACQ_CAMPAIGN,

        DBField::ACTIVITY_ID,
        DBField::SESSION_ID,
        DBField::ENTITY_ID,
        DBField::CONTEXT_ENTITY_ID,

        DBField::SENT_TIME,
        DBField::OPENED_TIME,
        DBField::CLICKED_TIME,
        DBField::CREATE_TIME
    ];

    protected $foreign_managers = [
        UsersManager::class => DBField::USER_ID,
        EmailTypesManager::class => DBField::EMAIL_TYPE_ID,
    ];

    /**
     * @param $emailTypeId
     * @param $userId
     * @param $request
     * @param null $currentTime
     * @return string
     */
    public function generateChecksum()
    {
        $salt = Auth::generate_user_salt(7);
        $now = TIME_NOW . substr(microtime(), 1, 8) . date('P');
        return sha1($salt.TIME_NOW.$now);
    }

    /**
     * @param EmailTrackingEntity $email
     * @return array
     */
    public function generateEmailTrackingParams(EmailTrackingEntity $email, $utmTerm = null)
    {
        $params = [
            GetRequest::PARAM_ET_ID => $email->getPk(),
            GetRequest::PARAM_CHECKSUM => $email->getChecksum(),
            GetRequest::PARAM_UTM_MEDIUM => EmailTypesManager::UTM_MEDIUM_EMAIL,
            GetRequest::PARAM_UTM_SOURCE => $email->getAcqSource(),
            GetRequest::PARAM_UTM_CAMPAIGN => $email->getAcqCampaign()
        ];

        if ($utmTerm)
            $params[GetRequest::PARAM_UTM_TERM] = $utmTerm;

        return $params;
    }

    /**
     * @param string $rawHtml
     * @param array $trackingParams
     * @return string
     */
    public function addTrackingParamsToLinks($rawHtml, EmailTrackingEntity $email, $utmTerm = null)
    {
        return add_get_params_to_all_links($rawHtml, $this->generateEmailTrackingParams($email, $utmTerm));
    }

    /**
     * @param Request $request
     * @param $checksum
     * @param $etId
     * @param $utm_source
     * @param $utm_campaign
     * @param $utm_medium
     * @return string
     */
    public function getEmailTrackingPixelUrl(Request $request, EmailTrackingEntity $email)
    {
        return $request->getWwwUrl("/pixels/email{$request->buildQuery($this->generateEmailTrackingParams($email))}");
    }


    /**
     * @param Request $request
     * @param $userId
     * @param $emailTypeId
     * @param null $timeCut
     * @return SqlQuery
     */
    protected function queryByUserId(Request $request, $userId, $emailTypeId = null, $timeCut = null)
    {
        $queryBuilder = $this->query($request->db)
            ->filter($this->filters->byUserId($userId));

        if ($emailTypeId)
            $queryBuilder->filter($this->filters->byEmailTypeId($emailTypeId));

        if ($timeCut)
            $queryBuilder->filter($this->filters->Gte(DBField::SENT_TIME, $timeCut));

        return $queryBuilder;
    }

    /**
     * @param Request $request
     * @param $checksum
     * @param $emailAddress
     * @param $emailTypeId
     * @param null $userId
     * @param null $entityId
     * @param null $contextEntityId
     * @param null $langId
     * @param null $artistId
     * @param null $comicId
     * @param null $utmSource
     * @param null $utmCampaign
     * @param null $activityId
     * @return EmailTrackingEntity
     */
    public function trackEmail(Request $request, $checksum, $emailAddress, $emailTypeId, $userId = null, $entityId = null,
                                    $contextEntityId = null, $langId = null,
                                    $utmSource = null, $utmCampaign = null, $activityId = null)
    {
        $emailTrackingData = [
            DBField::EMAIL_TYPE_ID => $emailTypeId,
            DBField::EMAIL_ADDRESS => $emailAddress,
            DBField::CHECKSUM => $checksum,
            DBField::LANGUAGE_ID => $langId,
            DBField::USER_ID => $userId,
            DBField::ACQ_SOURCE => $utmSource,
            DBField::ACQ_CAMPAIGN => $utmCampaign,
            DBField::ACTIVITY_ID => $activityId,
            DBField::ENTITY_ID => $entityId,
            DBField::CONTEXT_ENTITY_ID => $contextEntityId,
        ];

        return $this->query($request->db)->createNewEntity($request, $emailTrackingData);
    }

    /**
     * @param Request $request
     * @param $checksum
     * @param null $etId
     * @return EmailTrackingEntity
     */
    public function getEmailRecordByIdAndChecksum(Request $request, $etId, $checksum)
    {
        return $this->query($request->db)
            ->filter($this->filters->byPk($etId))
            ->filter($this->filters->byChecksum($checksum))
            ->get_entity($request);
    }

    /**
     * @param Request $request
     * @param $etId
     * @return EmailTrackingEntity
     * @throws ObjectNotFound
     */
    public function getEmailRecordById(Request $request, $etId)
    {
        $emailTypesManager = $request->managers->emailTypes();

        $fields = $this->createDBFields();

        return $this->query($request->db)
            ->fields($fields)
            ->inner_join($emailTypesManager)
            ->filter($this->filters->byPk($etId))
            ->get_entity($request);
    }

    /**
     * @param Request $request
     * @param $checksum
     * @param $user_id
     * @param null $time_cut
     * @return EmailTrackingEntity
     * @throws ObjectNotFound
     */
    public function getEmailRecordByChecksumUserId(Request $request, $checksum, $user_id, $time_cut = null)
    {
        return $this->queryByUserId($request, $user_id, null, $time_cut)
            ->filter($this->filters->byChecksum($checksum))
            ->sort_desc(DBField::SENT_TIME)
            ->get_entity($request);
    }

    /**
     * @param Request $request
     * @param $checksum
     * @return EmailTrackingEntity|bool
     */
    public function setEmailClickTime(Request $request, $etId, $checksum)
    {
        $email = $this->getEmailRecordByIdAndChecksum($request, $etId, $checksum);

        $current_time = $request->getCurrentSqlTime();

        $data = [];

        if (!$email->is_opened()) {
            $data[DBField::OPENED_TIME] = $current_time;
            $data[DBField::SESSION_ID] = $request->user->session->getSessionId();
            $data[DBField::IS_OPENED] = 1;
            $email->updateField(DBField::OPENED_TIME, $current_time);
            $email->updateField(DBField::SESSION_ID, $request->user->session->getSessionId());
            $email->updateField(DBField::IS_OPENED, 1);
        }

        if (!$email->is_clicked()) {
            $data[DBField::CLICKED_TIME] = $current_time;
            $data[DBField::IS_CLICKED] = 1;
            $email->updateField(DBField::CLICKED_TIME, $current_time);
            $email->updateField(DBField::IS_CLICKED, 1);
        }

        if ($data) {
            $this->query($request->db)
                ->filter($this->filters->byPk($etId))
                ->filter($this->filters->byChecksum($checksum))
                ->update($data);
        }
    }

    /**
     * @param Request $request
     * @param $etId
     * @param $checksum
     * @return EmailTrackingEntity|bool
     */
    public function setEmailOpenTime(Request $request, $etId, $checksum)
    {
        $email = $this->getEmailRecordByIdAndChecksum($request, $etId, $checksum);

        $current_time = $request->getCurrentSqlTime();

        $data = [];

        if ($email && !$email->is_opened()) {
            $data[DBField::OPENED_TIME] = $current_time;
            $data[DBField::SESSION_ID] = $request->user->session->getSessionId();
            $data[DBField::IS_OPENED] = 1;
            $email->updateField(DBField::OPENED_TIME, $current_time);
            $email->updateField(DBField::SESSION_ID, $request->user->session->getSessionId());
            $email->updateField(DBField::IS_OPENED, 1);
            $this->query($request->db)
                ->filter($this->filters->byPk($etId))
                ->filter($this->filters->byChecksum($checksum))
                ->update($data);

        }
    }

    /**
     * @param Request $request
     * @param $emailTypeId
     * @param $userId
     * @param $entityId
     * @return bool
     */
    public function checkRecentUserFollowerEmailExists(Request $request, UserEntity $user, DBManagerEntity $entity, $emailTypeId)
    {
        $timeCut = date($request->settings()->getSqlPostDateFormat(), strtotime("-1 day", TIME_NOW));
        return $this->queryByUserId($request, $user->getPk(), $emailTypeId, $timeCut)
            ->filter($this->filters->byEntityId($entity->getPk()))
            ->exists();
    }

    /**
     * @param $user
     * @param Request $request
     * @param $emailTypeId
     * @return EmailTrackingEntity
     */
    public function getLatestUserActivationEmail(Request $request, UserEntity $user, $timeCut = null)
    {
        $emailTypeIds = [
            EmailTypesManager::TYPE_SYSTEM_REGISTRATION_CONFIRMATION,
            EmailTypesManager::TYPE_SYSTEM_REGISTRATION_CONFIRMATION_RESEND
        ];

        return $this->queryByUserId($request, $user->getPk(), $emailTypeIds, $timeCut)
            ->sort_desc(DBField::SENT_TIME)
            ->get_entity($request);
    }

    /**
     * @param $et_id
     * @return int
     */
    public function markEmailAsSent(Request $request, EmailTrackingEntity $email)
    {
        $timeNow = new DateTime();

        $timeStamp = $timeNow->format($request->settings()->getSqlPostDateFormat());

        $email->updateField(DBField::SENT_TIME, $timeStamp);
        $this->query($request->db)
            ->filter($this->filters->byPk($email->getPk()))
            ->update([DBField::SENT_TIME => $timeStamp]);
    }

    /**
     * @param Request $request
     * @param $page
     * @param $perpage
     * @param null $query
     * @return array
     */
    public function getAdminEmailHistory(Request $request, $page, $perpage, $query = null)
    {

        $usersManager = $request->managers->users();
        $emailTypesManager = $request->managers->emailTypes();
        $emailManager = $request->managers->emailHistory();

        $emailFilter = $emailManager->filters->byEmailTrackingId($this->createPkField());

        $queryFilter = $query ? $this->filters->byEmailAddress($query) : null;

        $fields = $this->createDBFields();
        $fields[] = $usersManager->field(DBField::USERNAME);
        $fields[] = $emailManager->field(DBField::TITLE);
        $fields[] = $emailTypesManager->field(DBField::DISPLAY_NAME);

        $emails = $this->query($request->db)
            ->fields($fields)
            ->inner_join($emailTypesManager)
            ->left_join($emailManager, $emailFilter)
            ->left_join($usersManager)
            ->filter($queryFilter)
            ->paging($page, $perpage)
            ->sort_desc($this->createPkField())
            ->get_entities($request);

        return $emails;
    }

}
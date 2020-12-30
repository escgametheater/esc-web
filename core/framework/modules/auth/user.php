<?php

Modules::uses(Modules::ENTITIES);


class AnonymousUser extends User
{
    public $id               = UsersManager::ANONYMOUS_USER_ID;
    public $username             = UsersManager::ANONYMOUS_USER_NAME;
    public $group            = 1;
    public $is_staff         = false;
    public $is_superadmin    = false;
    public $user_salt        = '';
    public $groups           = [];
    public $rights           = [];
    public $timezone_offset  = 0;
    public $dst_auto         = 0;
    public $dst              = 0;
    public $is_authenticated = false;
    public $is_verified      = 0;
    public $email            = 0;


    public $url              = '';
    public $avatar_url       = '';
    public $avatar_small_url = '';
    public $avatar_tiny_url  = '';
    public $total_posts      = 0;
    public $total_orders     = 0;
    public $releases_lang    = LanguagesManager::LANGUAGE_ENGLISH;
    public $firstname        = '';
    public $lastname         = '';
    public $full_name        = '';
    public $display_real_name_in_public = false;
    public $gender           = 0;
    public $enable_pms       = 0;
    public $join_date        = null;


    public function __construct(Request $request, array $info)
    {
        parent::__construct($request, $info);
    }

    public function getEntity()
    {
        return parent::getEntity() ? parent::getEntity() : null;
    }
}

class User
{
    public $url;
    public $avatar_url;
    public $avatar_small_url;
    public $avatar_tiny_url;
    public $releases_lang;
    public $firstname;
    public $lastname;
    public $full_name;
    public $display_real_name_in_public;
    public $gender;
    public $enable_pms;
    public $join_date;

    protected $gameSalt = 'differentSuperStringDollarDollarBaby';

    public $is_creator = false;

    /**
     * Main group: Basic informations about user (forum)
     *
     * @var array
     */
    protected $group;

    /**
     * Basic informations about user (website)
     * computed from user group
     *
     * @var boolean
     */
    public $is_staff;

    /**
     * Basic informations about user (website)
     * Superadmins are set in the config file
     *
     * @var boolean
     */
    public $is_superadmin;

    /**
     * Basic informations about user (forum)
     *
     * @var unsigned int
     */
    public $id;

    /**
     * Basic informations about user (forum)
     *
     * @var string
     */
    public $username;

    /** @var string */
    public $user_salt;

    /**
     * Group informations
     *
     * @var array
     */
    public $groups = [];

    /**
     * User rights array
     *
     * @var Permissions
     */
    public $permissions;

    /**
     * User account verification status
     *
     * @var boolean
     */

    public $is_verified;

    /*
     * User Email Address
     */
    public $email;

    /**
     * User timezone offset
     *
     * @var int
     */
    public $timezone_offset;

    /**
     * Request user ip
     *
     * @var int
     */
    public $ip;

    /**
     * Cache
     * needed to update session map on destruction
     */
    public $cache;

    /**
     * Is authenticated
     */
    public $is_authenticated = true;

    /**
     * Auto update the user timezone for daytime savings
     */
    public $dst_auto;

    /**
     * Daytime savings offset
     */
    public $dst;

    /**
     * Geo Region ID
     */

    public $isMobile = 0;
    public $isTablet = 0;
    public $isPhone = 0;
    /** @var Mobile_Detect */
    public $deviceDetector;

    /**
     * @var Guest
     */
    public $guest;
    public $is_bot = false;

    public $beta_access = false;

    /**
     * @var UserEntity|array
     */
    public $entity = [];

    /**
     * @var Session
     */
    public $session;

    /** @var ApplicationUserAccessTokenEntity|null */
    public $applicationUserAccessToken;


    /**
     * @param array $info
     */
    public function __construct(Request $request, $info)
    {
        $geoRegionsManager = $request->managers->geoRegions();
        $usersUserGroupsManager = $request->managers->usersUserGroups();
        $usersManager = $request->managers->users();

        $this->info = $info;
        $this->id = array_get($info, 'userid', UsersManager::ANONYMOUS_USER_ID);
        $this->ip = $request->ip;
        $this->cache = $request->cache;

        // Construct the geo region data :>
        $request->geoRegion = $geoRegionsManager->getGeoRegionByCountryId($request, $request->geoIpMapping->getCountryId());
        $request->geo_region_id = $request->geoRegion->getPk();


        if ($this->is_authenticated()) {

            $userEntity = $usersManager->getUserById($request, $this->id);
            $this->username = $userEntity->getUsername();
            $this->is_verified = $userEntity->is_verified();
            $this->email = $userEntity->getEmailAddress();
            $this->beta_access = $userEntity->has_beta_access();
            $this->user_salt = $userEntity->field(DBField::SALT);
            $this->group = $userEntity->getUserGroupId();
            $this->url = $userEntity->getUrl();
            $this->avatar_url = $userEntity->getAvatarUrl();
            $this->avatar_small_url = $userEntity->getAvatarSmallUrl();
            $this->avatar_tiny_url = $userEntity->getAvatarTinyUrl();
            $this->firstname = $userEntity->getFirstName();
            $this->lastname = $userEntity->getLastName();
            $this->full_name = $userEntity->getDisplayName();
            $this->gender = $userEntity->getGender();
            $this->join_date = $userEntity->getJoinDate();

            $this->timezone_offset  = 0;
            $this->dst_auto         = 0;
            $this->dst              = 0;

            $this->cache = $request->cache;

            $this->groups = $usersUserGroupsManager->getUserGroupIdsForUserId($request, $this->id);

            $this->entity = $userEntity;

        } else {
            $this->groups[] = UserGroupsManager::GROUP_ID_GUESTS;
        }

        $this->deviceDetector = new Mobile_Detect($request->headers, $request->getUserAgent());

        array_unshift($this->groups, $this->group);

        $this->is_superadmin = Rights::is_superadmin($this->id);

        if ($this->is_superadmin)
            $this->is_staff = true;
        else
            $this->is_staff = Rights::is_staff($this->groups);

        //$this->is_staff = $this->is_superadmin();

        $this->permissions = new Permissions($this, $request);
    }

    /**
     * @return null|string
     */
    public function getApplicationUserAccessTokenId()
    {
        if ($this->applicationUserAccessToken)
            return $this->applicationUserAccessToken->getPk();
        else
            return null;
    }

    /**
     * @return string
     */
    public function getGameSalt()
    {
        return $this->gameSalt;
    }

    /*
     * Accessors
     */

    /**
     * @param Session $session
     */
    public function setSession(Session $session)
    {
        $this->session = $session;

        // User Device Type Information
        $session_entity = $session->getEntity();
        $this->isMobile = $session_entity[DBField::DEVICE_TYPE_ID] == GuestTrackingManager::TYPE_TABLET || $session_entity[DBField::DEVICE_TYPE_ID] == GuestTrackingManager::TYPE_PHONE;
        $this->isTablet = $session_entity[DBField::DEVICE_TYPE_ID] == GuestTrackingManager::TYPE_TABLET;
        $this->isPhone = $session_entity[DBField::DEVICE_TYPE_ID] == GuestTrackingManager::TYPE_PHONE;

        $this->is_staff = $this->session->validateUserIsStaff($this->groups, $this->is_superadmin);
    }

    /**
     * @param Guest $guest
     * @return $this
     */
    public function setGuest(Guest $guest)
    {
        $this->guest = $guest;

        // Aggregate check if user is a bot -- we don't want to let bots take automated on-site actions.
        $this->is_bot = $guest->isBot() ? true : false;

        return $this;
    }

    /**
     * Check if the user is recognized
     *
     * @return boolean
     */
    public function is_authenticated()
    {
        return !$this instanceof AnonymousUser;
    }

    /**
     * $timezone_offset accessor
     *
     * @return boolean
     */
    public function get_timezone_offset()
    {
        return $this->timezone_offset + $this->dst;
    }

    /**
     * $dst_auto accessor
     *
     * @return boolean
     */
    public function get_dst_auto()
    {
        return $this->dst_auto;
    }

    /**
     * @return string
     */
    public function getReleasesLang()
    {
        return $this->releases_lang;
    }

    /**
     * $dst accessor
     *
     * @return boolean
     */
    public function get_dst()
    {
        return $this->dst;
    }

    /**
     * Check if the user is recognized as staff
     *
     * @return boolean
     */
    public function is_staff()
    {
        return $this->is_staff;
    }

    /**
     * Check if user account is verified
     *
     * @return boolean
     */
    public function is_verified()
    {
        return $this->is_verified;
    }
    /**
     * Get account email
     *
     * @return boolean
     */
    public function get_email()
    {
        return $this->email;
    }
    /**
     * Check if the user is recognized
     *
     * @return boolean
     */
    public function is_superadmin()
    {
        return $this->is_superadmin;
    }

    /**
     * Get user id
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Get username
     *
     * @return string
     */
    public function getUsername()
    {
        return $this->username;
    }

    /**
     * Get username
     *
     * @return string
     */
    public function get_user_salt()
    {
        return $this->user_salt;
    }

    /**
     * @return array|UserEntity
     */
    public function getEntity()
    {
        return $this->entity;
    }

    /**
     * Check if user is in group
     *
     * @param int Group Id
     * @return boolean
     */
    public function has_group($id)
    {
        return (int)$id == $this->group || in_array((string)$id, $this->groups);
    }

    /**
     * Check if user is banned
     *
     * @param int Group Id
     * @return boolean
     */
    public function is_banned()
    {
        return $this->has_group(get_setting(ESCConfiguration::GROUP_BANNED))
            || $this->has_group(get_setting(ESCConfiguration::GROUP_BANNED_SPECIAL));
    }

    /**
     * @param string $content
     * @param int $code
     */
    public function sendFlashMessage($content = '', $code = MSG_INFO, $options = [])
    {
        FlashMessagesManager::sendFlashMessage($this->id, $content, $code, $options);
    }

    public function __destruct()
    {
        if (isset($this->session) && $this->session instanceof Session && $this->session->is_modified())
            Rights::set_session_map($this->cache, $this->id, $this->session->getCacheKey());
    }

    /**
     * @return array
     */
    public function getFlashMessages($clear = true)
    {
        return FlashMessagesManager::getFlashMessagesForUserId($this->id, $clear);
    }

    /**
     * @return bool
     */

    public function can_engage()
    {
        return $this->is_verified() && $this->is_authenticated() && !$this->is_bot;
    }

    /**
     * @param null $right
     * @param string $access_level
     * @return bool
     * @throws Exception
     */
    public function can_use($right = null)
    {
        if ($right)
            return $this->can_engage() && $this->permissions->has($right, Rights::USE);
        else
            return $this->can_engage();
    }
}


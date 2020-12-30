<?php

class SessionDataNotFound extends Exception {}

/**
 * Class Session
 *
 * @Implements ArrayAccess
 */
class Session implements ArrayAccess, Countable, IteratorAggregate
{

    const KEY_MESSAGES = 'flash_messages';
    const KEY_MIXPANEL_EVENTS = 'mixpanel_events';
    const KEY_SEARCH_QUERIES = 'session_search_queries';

    const KEY_FORM_ADMIN_TEAM_INVITE = 'admin.invite-team';

    const KEY_PERMISSIONS = 'permissions';

    const KEY_CONTROLLER_PUB_SUB_GRANTS = 'controller_pub_sub_grants';

    const KEY_SANDBOX_FRAGMENTS = 'sandbox_fragments';
    const KEY_SANDBOX_ANNOTATIONS = 'sandbox_annotations';

    /**
     * New session tracking
     *
     * @var boolean
     */
    protected $new_session = false;

    /**
     * Session modified
     *
     * @var boolean
     */
    protected $session_modified = false;

    /**
     * Session data
     *
     * @var array
     */
    protected $session_data = [];

    /**
     * Session hash
     *
     * @var string
     */
    protected $session_cache_key;


    /**
     * Cache result from getting the session array from cache
     * @var CacheResult
     */
    protected $session_cache;

    /** @var SessionEntity */
    protected $entity;

    /** @var  string */
    protected $session_hash;

    /** @var  int */
    protected $session_id;

    /**
     * Session constructor.
     * @param Request $request
     * @param Guest $guest
     * @param int $userId
     */
    public function __construct(Request $request, Guest $guest, $userId = UsersManager::ANONYMOUS_USER_ID)
    {
        $sessionTrackingManager = $request->managers->sessionTracking();
        $emailTrackingManager = $request->managers->emailTracking();

        // If the session hash is set in the user's cookies and matches a record in our DB, we know this is a continuation
        // of a legit existing session. If the session data is missing or fails validation, we will create a new session for the user.
        if ($request->getRealIp() && $guest->stores_cookies)
            $session = $sessionTrackingManager->checkCreateSessionRecord($request, $guest, $sessionTrackingManager->getTableByIsBot($guest->is_bot));
        else $session = [];

        if ($session) {
            // If the session record has no authenticated user associated, and the user is logged in, we should update the
            // session record with the user data. This let's us track sessions and guests across the lifetime of a user.
            if ($userId > 0 && isset($session[DBField::SESSION_HASH]) && empty($session[DBField::FIRST_USER_ID])) {

                $sessionTrackingManager->updateSessionRecordFirstUserId(
                    $request,
                    $session,
                    $userId,
                    $sessionTrackingManager->getTableByIsBot($guest->is_bot)
                );
            }
        } else {
            $checksum = $emailTrackingManager->generateChecksum();

            $session_data = [
                DBField::GUEST_ID => GuestTrackingManager::ID_NO_COOKIES,
                DBField::REQUEST_ID => $request->requestId,
                DBField::IS_FIRST_SESSION_OF_GUEST => $guest->is_new_guest() ? 1 : 0,
                DBField::SESSION_HASH => $checksum,
                DBField::SESSION_ID => SessionTrackingManager::ID_NO_COOKIES,
                DBField::GEO_REGION_ID => $request->geoRegion->getPk(),
                DBField::COUNTRY => $request->geoIpMapping->getCountryId(),
                DBField::ORIGINAL_REFERRER => null,
                DBField::ORIGINAL_URL => $request->path,
                DBField::PARAMS => $request->get->buildQuery(),
                DBField::FIRST_USER_ID => null,
                DBField::UI_LANGUAGE_ID => $request->getUiLang(),
                DBField::DEVICE_TYPE_ID => -1,
                DBField::ET_ID => $request->get->etId(),
                DBField::ACQ_MEDIUM => $request->get->utmMedium(),
                DBField::ACQ_SOURCE => $request->get->utmSource(),
                DBField::ACQ_CAMPAIGN => $request->get->utmCampaign(),
                DBField::ACQ_TERM => $request->get->utmTerm(),
                DBField::HTTP_USER_AGENT => null,
                DBField::CREATE_TIME => $request->getCurrentSqlTime(),
                DBField::IS_BOT => 0
            ];
            $session = $sessionTrackingManager->createEntity($session_data, $request);
        }

        $this->entity = $session;

        $this->session_hash = $guest->getSessionHash();
        $this->session_id = $session->getSessionId();
        $this->session_cache_key = SessionTrackingManager::GNS_KEY_PREFIX . ".{$userId}.{$guest->getGuestHash()}";

        $this->session_cache = $request->cache->get(
            $this->session_data,
            $this->session_cache_key,
            $request->settings()->getSessionTimeout()
        );

        if (!is_array($this->session_data))
            $this->session_data = [];

        $this->new_session = !$this->session_cache->isset;
        $this->session_modified = $this->session_cache->needsset;

        // Session Flash Messages Initialization
        if (!$this->sessionKeyExists(self::KEY_MESSAGES) || !is_array($this->session_data[self::KEY_MESSAGES])) {
            $this->session_data[self::KEY_MESSAGES] = [];
            $this->session_modified = true;
        }

        if (!$this->sessionKeyExists(self::KEY_MIXPANEL_EVENTS) || !is_array($this->session_data[self::KEY_MIXPANEL_EVENTS])) {
            $this->session_data[self::KEY_MIXPANEL_EVENTS] = [];
            $this->session_modified = true;
        }

        if (!$this->new_session) {
            if ($utmMedium = $request->get->utmMedium())
                $this->setIfNew(DBField::ACQ_MEDIUM, $utmMedium);

            if ($utmSource = $request->get->utmSource())
                $this->setIfNew(DBField::ACQ_SOURCE, $utmSource);

            if ($utmCampaign = $request->get->utmCampaign())
                $this->setIfNew(DBField::ACQ_CAMPAIGN, $utmCampaign);

            if ($utmTerm = $request->get->utmTerm())
                $this->setIfNew(DBField::ACQ_TERM, $utmTerm);

            if ($etId = $request->get->etId())
                $this->setIfNew(DBField::ET_ID, $etId);
        }

    }

    /**
     * Returns true if the referring url path is not the same as the current and the user has not searched for
     * this term during their current session.
     *
     * @param Request $request
     * @param $query_string
     * @return bool
     */
    public function isNewSearchQueryForSession(Request $request, $query_string)
    {
        // Don't track search queries where the query is identical to the referring page.
        $referring_url = $request->getReferer();
        if (!empty($referring_url) && strpos($request->getFullUrl(), $referring_url) === false)
            return false;

        $session_search_queries = [];

        // First we get all Unique Queries that the Guest has had this Session
        try {
            $session_search_queries = $this[Session::KEY_SEARCH_QUERIES];
        } catch (SessionDataNotFound $s) {
            $this[Session::KEY_SEARCH_QUERIES] = $session_search_queries;
            $this->session_modified = true;
        }

        // Then we'll check if this is a new query. If so, we'll add it to the session queries list
        // and will track the search query request.
        if ($is_new_query_term = !in_array($query_string, $session_search_queries)) {
            $this[Session::KEY_SEARCH_QUERIES][] = $query_string;
            $this->session_modified = true;
        }

        return $is_new_query_term;
    }

    /**
     * @param $key
     */
    public function deleteSessionKey($key)
    {
        $this->offsetUnset($key);
        $this->save_session();
    }


    /**
     * @return int|null
     */
    public function getSessionId()
    {
        return $this->session_id;
    }

    /**
     * @return string
     */
    public function getSessionHash()
    {
        return $this->session_hash;
    }

    /**
     * @return string
     */
    public function getCountryId()
    {
        return $this->entity->getCountryId();
    }

    /**
     * @return array|SessionEntity
     */
    public function getEntity()
    {
        return $this->entity;
    }

    /**
     * @return array
     */
    public function getSessionData()
    {
        return $this->session_data;
    }

    /**
     * $session_cache_key accessor
     */
    public function getCacheKey()
    {
        return $this->session_cache_key;
    }

    /**
     * @return bool
     */
    public function is_modified()
    {
        return $this->session_modified;
    }

    /**
     * Saves session data
     */
    public function save_session()
    {
        $this->session_modified = false;
        $this->session_cache->set($this->session_data);
    }

    /**
     * @param $message
     * @param int $type
     * @param array $options
     */
    public function sendSessionFlashMessage($message, $type = MSG_INFO, $options = [])
    {
        if (!array_key_exists(self::KEY_MESSAGES, $this->session_data))
            $this->session_data[self::KEY_MESSAGES] = [];

        $this->session_data[self::KEY_MESSAGES][] = [
            DBField::USER_ID => null,
            DBField::TYPE => $type,
            DBField::CONTENT => json_encode([
                DBField::BODY => $message,
                DBField::OPTIONS => $options
            ])
        ];
        $this->session_modified = true;
    }

    /**
     * @param $eventName
     * @param array $props
     */
    public function sendSessionMixpanelEvent($eventName, $props = [])
    {
        if (!array_key_exists(self::KEY_MIXPANEL_EVENTS, $this->session_data))
            $this->session_data[self::KEY_MIXPANEL_EVENTS] = [];

        $event = [
            'name' => $eventName,
            'properties' => $props
        ];

        $this->session_data[self::KEY_MIXPANEL_EVENTS][] = $event;
        $this->session_modified = true;
        $this->save_session();
    }

    /**
     *
     */
    public function clearSessionMessages()
    {
        $this->session_data[self::KEY_MESSAGES] = [];
        $this->session_modified = true;
    }

    /**
     *
     */
    public function clearSessionMixpanelEvents()
    {
        $this->session_data[self::KEY_MIXPANEL_EVENTS] = [];
        $this->session_modified = true;
    }

    /**
     * @return bool
     */
    public function getHasSessionMessages()
    {
        return !empty($this->session_data[self::KEY_MESSAGES]);
    }

    /**
     * @return bool
     */
    public function getHasSessionMixpanelEvents()
    {
        return !empty($this->session_data[self::KEY_MIXPANEL_EVENTS]);
    }

    /**
     * @param bool $clear
     * @return array
     */
    public function getSessionMessages($clear = false)
    {
        $messages = !empty($this->session_data[self::KEY_MESSAGES]) ? $this->session_data[self::KEY_MESSAGES] : [];
        if (!empty($messages) && $clear)
            $this->clearSessionMessages();

        foreach ($messages as $key => $message)
            $messages[$key] = FlashMessagesManager::createEntity($message);

        return $messages;
    }

    /**
     * @param bool $clear
     * @return array|mixed
     */
    public function getSessionMixpanelEvents($clear = false)
    {
        $events = !empty($this->session_data[self::KEY_MIXPANEL_EVENTS]) ? $this->session_data[self::KEY_MIXPANEL_EVENTS] : [];
        if ($clear)
            $this->clearSessionMixpanelEvents();

        return $events;
    }

    /**
     * @return string
     */
    public function getReferer()
    {
        return $this->entity->getOriginalReferer();
    }

    /**
     * @return int
     */
    public function getDeviceTypeId()
    {
        return $this->entity->getDeviceTypeId();
    }

    /**
     * @return string
     */
    public function getLandingPage()
    {
        return $this->entity->getLandingPage();
    }

    /**
     * @return string
     */
    public function getUtmMedium()
    {
        return $this->entity->getUtmMedium();
    }

    /**
     * @return string
     */
    public function getUtmSource()
    {
        return $this->entity->getUtmSource();
    }

    /**
     * @return string
     */
    public function getUtmCampaign()
    {
        return $this->entity->getUtmCampaign();
    }

    /**
     * @return string
     */
    public function getUtmTerm()
    {
        return $this->entity->getUtmTerm();
    }

    /**
     * @return int|null
     */
    public function getEtId()
    {
        return $this->entity->getEtId();
    }

    /**
     * @return string|null
     */
    public function getLastUtmMedium()
    {
        return $this->safeGet(DBField::ACQ_MEDIUM);
    }

    /**
     * @return string|null
     */
    public function getLastUtmSource()
    {
        return $this->safeGet(DBField::ACQ_SOURCE);
    }

    /**
     * @return string|null
     */
    public function getLastUtmCampaign()
    {
        return $this->safeGet(DBField::ACQ_CAMPAIGN);
    }

    /**
     * @return string|null
     */
    public function getLastUtmTerm()
    {
        return $this->safeGet(DBField::ACQ_TERM);
    }

    /**
     * @return mixed|null
     */
    public function getLastEtId()
    {
        return $this->safeGet(DBField::ET_ID);
    }

    /**
     * @param bool|false $is_superadmin
     * @return bool
     */
    public function validateUserIsStaff($groups, $is_superadmin = false)
    {
        $is_staff = $is_superadmin ? true : false;

        if (!$is_superadmin && !array_key_exists(VField::IS_STAFF, $this->session_data)) {
            if (!$is_staff) {
                $is_staff = Rights::is_staff($groups);
            }

            $this[VField::IS_STAFF] = $is_staff;
        }

        return $is_superadmin ? $is_superadmin : $this[VField::IS_STAFF];
    }

    /**
     * Session data handling (set)
     */
    public function offsetSet($key, $value)
    {
        $this->session_modified = true;
        $this->session_data[$key] = $value;
    }

    /**
     * @param $key
     * @param $value
     */
    public function set($key, $value)
    {
        $this->offsetSet($key, $value);
    }

    /**
     * @param $key
     * @return bool
     */
    public function has($key)
    {
        return $this->offsetExists($key);
    }

    /**
     * @param $key
     * @return bool
     */
    public function unset($key)
    {
        return $this->offsetExists($key) ? $this->offsetUnset($key) : false;
    }

    /**
     * Session data handling (get)
     *
     * @return boolean
     */
    public function offsetGet($key)
    {
        if (!array_key_exists($key, $this->session_data))
            throw new SessionDataNotFound($key);
        return $this->session_data[$key];
    }

    /**
     * @param $key
     * @return mixed|null
     */
    public function safeGet($key, $default = null)
    {
        try {
            $value = $this[$key];
        } catch (SessionDataNotFound $e) {
            $value = $default;
        }

        return $value;
    }

    /**
     * @param $key
     * @param null $value
     * @return $this
     */
    public function setIfNew($key, $value = null)
    {

        if ($value) {
            if ($this->safeGet($key) != $value)
                $this->offsetSet($key, $value);
        }
        return $this;
    }

    /**
     * Session data handling (delete)
     *
     * @return boolean
     */
    public function offsetUnset($key)
    {
        $this->session_modified = true;
        unset($this->session_data[$key]);
        return true;
    }

    /**
     * Empty Session data
     *
     * @return boolean
     */
    public function flush_data()
    {
        $this->session_modified = true;
        $this->session_data = [];
        $this->session_cache->delete();
    }

    /**
     * Check if key exists in session data
     *
     * @return boolean
     */
    public function offsetExists($key)
    {
        return array_key_exists($key, $this->session_data);
    }

    /**
     * @param $key
     * @return bool
     */
    public function sessionKeyExists($key)
    {
        return $this->offsetExists($key);
    }

    /**
     * $new_session accessor
     *
     * @return boolean
     */
    public function is_new_session()
    {
        return $this->new_session;
    }

    /**
     * It
     */
    public function getIterator()
    {
        return new ArrayIterator($this->session_data);
    }

    public function count()
    {
        return count($this->session_data);
    }

    /**
     * Destructor
     * Saves session data (only if modified)
     */
    public function __destruct()
    {
        if ($this->session_modified)
            $this->save_session();
    }

}

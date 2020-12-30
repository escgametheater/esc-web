<?php
/**
 * Created by PhpStorm.
 * User: ccarter
 * Date: 11/30/15
 * Time: 9:30 PM
 */

Modules::uses(Modules::MANAGERS);
Modules::uses(Modules::CACHE);
Modules::uses(Modules::ENTITIES);


class GuestDataNotFound extends Exception {}

class Guest implements ArrayAccess, Countable, IteratorAggregate
{
    /**
     * New session tracking
     *
     * @var boolean
     */
    private $new_guest = false;

    /**
     * Session modified
     *
     * @var boolean
     */
    private $guest_modified = false;

    /**
     * @var GuestEntity
     */
    private $entity = [];

    /**
     * Session hash
     *
     * @var string
     */
    protected $guest_cache_key;

    /**
     * @var CacheResult $guest_cache
     */
    protected $guest_cache;

    /** @var  string */
    protected $guest_hash;

    /** @var  string */
    protected $session_hash;

    /** @var  int */
    protected $guest_id;

    public $is_bot = false;

    public $stores_cookies = false;

    /**
     * Guest constructor.
     * @param Request $request
     * @param int $userid
     */
    public function __construct(Request $request, $userid = UsersManager::ANONYMOUS_USER_ID)
    {
        $this->entity = $this->checkCreateGuestRecord($request, $userid);
    }

    /**
     * @param Request $request
     * @param $user_id
     * @return array|DBManagerEntity|GuestEntity|null
     */
    private function checkCreateGuestRecord(Request $request, $user_id)
    {
        $guestTrackingManager = $request->managers->guestTracking();
        $sessionTrackingManager = $request->managers->sessionTracking();
        $emailTrackingManager = $request->managers->emailTracking();

        // Understand what the shared timestamp is as we are checking or creating new records,
        $current_time = date($request->getCurrentSqlTime());

        // If we know this is not the first visit and we don't store cookies, we don't want to keep generating
        // guest/session records in the DB as they are worthless. We'll handle that case in the else. If this is a
        // session, we'll go ahead with all the tracking checks and logic.
        $this->stores_cookies = $user_stores_cookies = $guestTrackingManager->validateCookiesGetStored($request);

        // If this is a CLI mode or user doesn't store cookies, create fake records -- don't store.
        if ($request->getRealIp() && $user_stores_cookies) {
            // Check the user agent against known Bot Strings, ensuring we track those guests/sessions to separate tables.
            // We also want to store this data in the request object as bots are not allowed to perform POST forms requests.
            $user_agent = $request->getUserAgent();
            $this->is_bot = $is_bot = $guestTrackingManager->getIsBot($user_agent, $request->getRealIp());

            // Check / fetch Guest ID, Guest Hash, and Session Hash from cookies. If they exist, we will check their validity.
            // If they don't exist, we will handle generation of new records as needed.
            $guest_id = $request->readCookie(GuestTrackingManager::COOKIE_GUEST_ID);
            $guest_hash = $request->readCookie(GuestTrackingManager::COOKIE_GUEST_HASH);
            $this->session_hash = $request->readCookie(GuestTrackingManager::COOKIE_SESSION_HASH);

            // If we don't have an active session_hash set in the user's cookies, we should generate an identifier/hash for
            // the session. We generate the session hash first in case we need to make the guest and session hashes identical.
            if (!$this->session_hash) {
                $this->session_hash = $emailTrackingManager->generateChecksum();
            } else {
                // If we have a cookie with a session hash set, we should verify that the session data is real and
                // the record exists. This enables us to know whether or not this is a real and valid session that has
                // not expired.
                if (!$sessionTrackingManager->checkSessionExists($request, $this->session_hash, $sessionTrackingManager->getTableByIsBot($is_bot)))
                    // If session cookie is set, but is corrupted or modified by the user, we are going to create a new
                    // valid session record. This may or may not be the first session of the guest record, and we will check
                    // that below and also verify the records with the data in our DB / cache.
                    $this->session_hash = $emailTrackingManager->generateChecksum();
            }
            // If we don't have a guest id or hash set in cookies, somethings either amiss, or we have a new user/guest/device
            // to handle.
            if (!$guest_id || !$guest_hash) {

                // If the above returns true, it's the first visit of this user / guest. We will set the session_hash and
                // guest_hash to be identical and mark the first session of this particular guest. This will indicate to
                // the aggregate records in our system that we are looking at a first session, without having to do joins
                // on big tables.
                $guest_hash = $this->session_hash;
                $this->new_guest = true;
                // As this is a new guest, we'll generate a new guest record using the session hash as the guest hash.
                // This is an implicit first session of guest setting across all our aggregate tracking systems.
                $guest_id = $guestTrackingManager->generateGuestRecord($request, $current_time, $guest_hash, $is_bot);
                $guest = $guestTrackingManager->getGuestRecordByIdHash($request, $guest_id, $guest_hash, $guestTrackingManager->getTableByIsBot($is_bot));
            } else {

                // However, if we have all the right guest cookies set, we should validate them against our records.
                $guest = $guestTrackingManager->getGuestRecordByIdHash($request, $guest_id, $guest_hash, $guestTrackingManager->getTableByIsBot($is_bot));
                // If the guest is valid, we'll continue with session records checks, but if we fail validation, this is
                // either a corrupted guest cookie, or the first visit of the guest. Let's handle.

                if (!$guest) {
                    // Set Guest Hash = session to indicate first session, then generate the guest record.
                    $guest_hash = $this->session_hash;
                    $this->new_guest = true;
                    $guest_id = $guestTrackingManager->generateGuestRecord($request, $current_time, $guest_hash, $is_bot);
                    // After we create the record, we should fetch the data from DB to verify that we stored all the data.
                    $guest = $guestTrackingManager->getGuestRecordByIdHash($request, $guest_id, $guest_hash, $guestTrackingManager->getTableByIsBot($is_bot));
                }
            }

            // Now that we have either verified a guest record, or created a new record for this user, we should do some user
            // association checks. If the guest has no record of logging in to an account before, and the user is currently
            // authenticated, let's associate the user account with the guest record.
            if ($user_id > UsersManager::ANONYMOUS_USER_ID && $guest[DBField::FIRST_USER_ID] == null) {
                $guest->updateGuestRecordFirstUserId($request, $user_id);
            }
        } else {
            // If we're not storing cookies, let's generate a fake guest & session record for the request object.
            $this->session_hash = $checksum = $emailTrackingManager->generateChecksum();

            $this->new_guest = true;
            $guest_record = [
                DBField::GUEST_ID => GuestTrackingManager::ID_NO_COOKIES,
                DBField::GUEST_HASH => $checksum,
                DBField::REQUEST_ID => $request->requestId,
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
                DBField::CREATE_TIME => $current_time,
                DBField::IS_BOT => 0
            ];

            $guest = $guestTrackingManager->createEntity($guest_record, $request);
        }
        $guest->stores_cookies = $user_stores_cookies;
        $this->guest_hash = $guest[DBField::GUEST_HASH];
        $this->guest_id = $guest[DBField::GUEST_ID];
        return $guest;
    }

    public function getGuestId()
    {
        return $this->guest_id;
    }

    public function getGuestHash()
    {
        return $this->guest_hash;
    }

    public function getSessionHash()
    {
        return $this->session_hash;
    }

    public function getEntity()
    {
        return $this->entity;
    }
    public function getDeviceTypeId()
    {
        return $this->entity[DBField::DEVICE_TYPE_ID];
    }
    public function getReferer()
    {
        return $this->entity[DBField::ORIGINAL_REFERRER];
    }

    public function getLandingPage()
    {
        return $this->entity[DBField::ORIGINAL_URL];
    }


    public function getUtmMedium()
    {
        return $this->entity->field(DBField::ACQ_MEDIUM);
    }

    public function getUtmSource()
    {
        return $this->entity->field(DBField::ACQ_SOURCE);
    }

    public function getUtmCampaign()
    {
        return $this->entity->field(DBField::ACQ_CAMPAIGN);
    }

    public function getUtmTerm()
    {
        return $this->entity->field(DBField::ACQ_TERM);
    }

    public function getEtId()
    {
        return $this->entity->field(DBField::ET_ID);
    }


    public function isBot()
    {
        return $this->entity['is_bot'];
    }

    /**
     * $session_cache_key accessor
     */
    public function getCacheKey()
    {
        return $this->guest_cache_key;
    }

    public function is_modified()
    {
        return $this->guest_modified;
    }

    /**
     * Session data handling (set)
     */
    public function offsetSet($key, $value)
    {
        $this->guest_modified = true;
        $this->entity[$key] = $value;
    }

    /**
     * Session data handling (get)
     *
     * @return boolean
     */
    public function offsetGet($key)
    {
        return $this->entity[$key];
    }

    /**
     * Session data handling (delete)
     *
     * @return boolean
     */
    public function offsetUnset($key)
    {
        $this->guest_modified = true;
        unset($this->entity[$key]);
    }

    /**
     * Empty Session data
     *
     * @return boolean
     */
    public function flush_data()
    {
        $this->guest_modified = true;
        $this->entity = [];
    }

    /**
     * Check if key exists in session data
     *
     * @return boolean
     */
    public function offsetExists($key)
    {
        return array_key_exists($key, $this->entity);
    }

    /**
     * $new_session accessor
     *
     * @return boolean
     */
    public function is_new_guest()
    {
        return $this->new_guest;
    }

    /**
     * It
     */
    public function getIterator()
    {
        return new ArrayIterator($this->entity);
    }

    public function count()
    {
        return count($this->entity);
    }

    /**
     * Destructor
     * Saves session data (only if modified)
     */
    public function __destruct()
    {
        //if ($this->guest_modified)
            //$this->save_guest();
    }

}

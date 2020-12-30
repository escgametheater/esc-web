<?php
/**
 * Tracking Managers
 *
 * @package managers
 */

Entities::uses("tracking");

use Snowplow\RefererParser\Parser;

class GuestTrackingManager extends BaseEntityManager
{
    protected $pk = DBField::GUEST_ID;
    protected $entityClass = GuestEntity::class;
    protected $table = Table::GuestTracking;
    protected $table_alias = TableAlias::GuestTracking;

    const GNS_KEY_PREFIX = GNS_ROOT.'.guests';
    const ID_NO_COOKIES = 0;

    protected $excludedKpiUserAgents = [
        'electron-builder',
        'NodePing'
    ];

    /**
     * DB Fields
     *
     * @var array
     */
    public static $fields = [
        DBField::GUEST_ID,
        DBField::GUEST_HASH,
        DBField::REQUEST_ID,
        DBField::COUNTRY,
        DBField::ORIGINAL_REFERRER,
        DBField::ORIGINAL_URL,
        DBField::PARAMS,
        DBField::FIRST_USER_ID,
        DBField::GEO_REGION_ID,
        DBField::UI_LANGUAGE_ID,
        DBField::DEVICE_TYPE_ID,
        DBField::ET_ID,
        DBField::ACQ_MEDIUM,
        DBField::ACQ_SOURCE,
        DBField::ACQ_CAMPAIGN,
        DBField::ACQ_TERM,
        DBField::HTTP_USER_AGENT,
        DBField::CREATE_TIME,
        DBField::IS_BOT
    ];

    // Remove these fields for frontend
    public $removed_json_fields = [
        DBField::IS_BOT,
        DBField::FIRST_USER_ID,
        DBField::HTTP_USER_AGENT
    ];

    // Cookie settings
    const COOKIE_GUEST_ID = 'gcg_id';
    const COOKIE_GUEST_HASH = 'gcg_hash';
    const COOKIE_SESSION_HASH = 'gcs_hash';

    const COOKIE_PATH_GLOBAL = '/';

    // Guest and session cache times
    const GUEST_RECORD_CACHE_TIME = ONE_DAY;
    const GUEST_SESSION_COOKIE_TIME = HALF_AN_HOUR;

    // Device type definitions and names
    const TYPE_DESKTOP_LAPTOP = 0;
    const TYPE_TABLET = 1;
    const TYPE_PHONE = 2;
    const TYPE_OTHER = 3;

    public static $device_types = [
        self::TYPE_DESKTOP_LAPTOP => 'Desktop/Laptop',
        self::TYPE_TABLET => 'Tablet',
        self::TYPE_PHONE => 'Phone',
        self::TYPE_OTHER => 'Other',
    ];

    /**
     * @param $device_type
     * @return mixed|null
     */
    public static function getDeviceTypeName($device_type)
    {
        $device_types = self::$device_types;
        return array_key_exists($device_type, $device_types) ? $device_types[$device_type] : null;
    }

    /**
     * @param $is_bot
     * @return string
     */
    public function getTableByIsBot($is_bot) {
        return $is_bot ? Table::BotGuestTracking : Table::GuestTracking;
    }

    /**
     * @return array
     */
    public function getExcludedKpiUserAgents()
    {
        return $this->excludedKpiUserAgents;
    }

    /**
     * @param Request $request
     * @return bool
     */
    public function validateCookiesGetStored(Request $request)
    {
        $user_has_cookies = true;
        if ($request->isInternalReferer()) {
            if (!$request->hasCookie(self::COOKIE_GUEST_ID)) {
                // If the referer is our own site and we don't have a cookie, this user is not storing cookies.
                $user_has_cookies = false;
            }
        }
        return $user_has_cookies;
    }

    /**
     * @param Request $request
     * @param $current_time
     * @param $guest_hash
     * @param $is_bot
     * @return int
     */
    public function generateGuestRecord(Request &$request, $current_time, $guest_hash, $is_bot)
    {
        // Since we're creating a guest record, we need to know the device type the guest is using.
        $detector = new Mobile_Detect();
        $is_mobile = $detector->isMobile();
        $is_tablet = $detector->isTablet();

        // Then we need to check where the guest came from, and what URL parameters that indicate medium and source.
        $user_agent = $request->getUserAgent();
        $current_url = $request->getCurrentUrlPath();
        $refererParser = new Parser();
        $referer = $refererParser->parse($request->getRefererDomain(), $current_url);

        // If our referer is valid and matches our checks, we should define the implied and explicit UTM parameters.
        // HTTP header data is more trustworthy than explicit URL parameters' so let's try to match that first.
        if ($referer->isValid()) {
            $utm_medium = $referer->getMedium();
            $utm_medium = $utm_medium ? $utm_medium : $request->get->utmMedium();
            $utm_source = $referer->getSource();
            $utm_source = $utm_source ? $utm_source : $request->get->utmSource();
            $utm_term = $referer->getSearchTerm();
        } else {
            // If we don't have any acquisition source data detected in our referrer headers, let's check the url
            // parameters.
            $utm_medium = $request->get->utmMedium();
            $utm_source = $request->get->utmSource();
            $utm_term = $request->get->utmTerm();
        }

        // If our referrer source failed validation but is still set, and we were not able to detect any UTM params in
        // the uri requested, we should override the failed checks and set the data we "know" to be true regardless.
        if (!$utm_medium && $request->getReferer() && !$request->isInternalReferer()) {
            $utm_medium = EmailTypesManager::UTM_MEDIUM_REFERRAL;
            $utm_source = $request->getRefererDomain();
        }
        // If we don't have a UTM source set and the medium is referral, let's use the referral domain as the source.
        if (!$utm_source && $utm_medium === EmailTypesManager::UTM_MEDIUM_REFERRAL)
            $utm_source = $request->getRefererDomain();

        if (!$geoRegionId = $request->geoRegion->getPk())
            $geoRegionId = GeoRegionsManager::GEO_ID_ALL;

        $excludeParams = [
            GetRequest::PARAM_MAGIC,
            GetRequest::PARAM_EXP,
            GetRequest::PARAM_IDENT
        ];
        $paramString = $request->get->buildQuery('?', $excludeParams);

        // Now that we know all the information we need to create an accurate guest record, let's do that.
        $guest_data = [
            DBField::GUEST_HASH => $guest_hash,
            DBField::REQUEST_ID => $request->requestId,
            DBField::COUNTRY => $request->geoIpMapping->getCountryId(),
            DBField::ORIGINAL_REFERRER => truncate_string($request->getReferer(), 1024),
            DBField::ORIGINAL_URL => $current_url,
            DBField::PARAMS => $paramString,
            DBField::FIRST_USER_ID => null,
            DBField::GEO_REGION_ID => $geoRegionId,
            DBField::UI_LANGUAGE_ID => $request->readCookie(Request::COOKIE_UI_LANG, LanguagesManager::LANGUAGE_ENGLISH),
            DBField::DEVICE_TYPE_ID => $this->getDeviceType($is_mobile, $is_tablet),
            DBField::ET_ID => $request->get->etId(),
            DBField::ACQ_MEDIUM => $utm_medium,
            DBField::ACQ_SOURCE => $utm_source,
            DBField::ACQ_CAMPAIGN => $request->get->utmCampaign(),
            DBField::ACQ_TERM => $utm_term,
            DBField::HTTP_USER_AGENT => $user_agent,
            DBField::CREATE_TIME => $current_time,
            DBField::IS_BOT => $is_bot
        ];
        $guest_id = $this->query($request->db)->table($this->getTableByIsBot($is_bot))->add($guest_data);

        // Set the cookies so the guest identity persists for the user.
        $expire = TIME_NOW + ONE_YEAR*10;

        if (!tracking_skip_cookie($request)) {
            $request->setCookie(self::COOKIE_GUEST_ID, $guest_id, $expire);
            $request->setCookie(self::COOKIE_GUEST_HASH, $guest_hash, $expire);
        }

        return $guest_id;
    }

    public static function generateEntityIdCacheKey($guest_id, $guest_hash)
    {
        return self::GNS_KEY_PREFIX.'.'.$guest_id.'.'.$guest_hash;
    }

    /**
     * @param Request $request
     * @param $thisUser
     * @return array
     */
    public function getGuestRecordsByUser(Request $request, $thisUser)
    {
        return $this->query($request->db)
            ->filter(Q::Eq(DBField::FIRST_USER_ID, $thisUser[DBField::ID]))
            ->get_list(GuestTrackingManager::$fields);
    }

    /**
     * @param Request $request
     * @param $guest_id
     * @return array|GuestEntity
     */
    public function getGuestRecordById(Request $request, $guest_id)
    {
        $geoRegionsManager = $request->managers->geoRegions();

        try {
            $guest = $this->query($request->db)->filter($this->filters->byGuestId($guest_id))->get();

            $guest[VField::GEO_REGION_NAME_DISPLAY] = $geoRegionsManager->getRegionNameById($request, $guest[DBField::GEO_REGION_ID]);
            $guest = $this->createEntity($guest, $request);

        } catch (ObjectNotFound $e) {
            $guest = [];
        }
        return $guest;

    }

    /**
     * @param Request $request
     * @param $guestIds
     * @return GuestEntity[]
     */
    public function getGuestsByIds(Request $request, $guestIds, $fetchCountries = false)
    {
        $countriesManager = $request->managers->countries();
        $sessionsManager = $request->managers->sessionTracking();

        $joinSessionManagerFilter = $this->filters->Eq($this->field(DBField::GUEST_HASH), $sessionsManager->field(DBField::SESSION_HASH));

        $fields = $this->createDBFields();

        $fields[] = $sessionsManager->field(DBField::IP);

        /** @var GuestEntity[] $guests */
        $guests = $this->query($request->db)
            ->fields($fields)
            ->inner_join($sessionsManager, $joinSessionManagerFilter)
            ->filter($this->filters->byPk($guestIds))
            ->get_entities($request);

        if ($guests) {
            foreach ($guests as $guest) {

                if ($fetchCountries)
                    $country = $countriesManager->getCountryById($request, $guest->getCountryId());
                else
                    $country = [];

                $deviceName = $this->getDeviceTypeName($guest->getDeviceTypeId());

                $guest->updateField('orig_country', $country);
                $guest->updateField(VField::DEVICE_TYPE_NAME, $deviceName);
            }
            $guests = array_index($guests, $this->getPkField());
        }

        return $guests;
    }

    /**
     * @param Request $request
     * @param $guest_id
     * @param $guest_hash
     * @param string $table
     * @return array|GuestEntity
     */
    public function getGuestRecordByIdHash(Request $request, $guest_id, $guest_hash, $table = Table::GuestTracking)
    {
        return $this->query($request->db)
            ->cache($this->generateEntityIdCacheKey($guest_id, $guest_hash), self::GUEST_RECORD_CACHE_TIME, false)
            ->table($table)
            ->filter($this->filters->q->Eq(DBField::GUEST_ID, $guest_id))
            ->filter($this->filters->q->Eq(DBField::GUEST_HASH, $guest_hash))
            ->get_entity($request);
    }


    /**
     * @param Request $request
     * @param $guest_hash
     * @return array|DBManagerEntity|GuestEntity|null
     */
    public function getGuestRecordByHash(Request $request, $guest_hash)
    {
        return $this->query($request->db)
            ->filter($this->filters->q->Eq(DBField::GUEST_HASH, $guest_hash))
            ->get_entity($request);
    }

    /**
     * @param $is_mobile
     * @param $is_tablet
     * @return int|null
     */
    public static function getDeviceType($is_mobile, $is_tablet)
    {
        $device_type = null;

        if (!$is_mobile && !$is_tablet)
            $device_type = self::TYPE_DESKTOP_LAPTOP;
        elseif ($is_mobile && !$is_tablet)
            $device_type = self::TYPE_PHONE;
        elseif ($is_tablet)
            $device_type = self::TYPE_TABLET;
        else
            $device_type = self::TYPE_OTHER;

        return $device_type;
    }

    /**
     * @param GuestEntity $data
     * @param Request $request
     * @return mixed
     */
    public function processVFields(DBManagerEntity $data, Request $request)
    {
        $data[VField::DEVICE_TYPE_NAME] = $this->getDeviceTypeName($data->getDeviceTypeId());
        return $data;
    }

    /**
     * @param Request $request
     * @param GuestEntity $guest
     * @param string $table
     * @return int
     */
    public function updateGuestRecordFirstUserId(Request $request, GuestEntity $guest, $table = Table::GuestTracking)
    {
        $guest->updateField(DBField::FIRST_USER_ID, $request->user->id);

        $request->cache->set(
            $this->generateEntityIdCacheKey($guest->getGuestId(), $guest->getGuestHash()),
            $guest->getDataArray(true),
            GuestTrackingManager::GUEST_RECORD_CACHE_TIME
        );

        return $this->query($request->db)
            ->table($table)
            ->filter($this->filters->q->Eq(DBField::GUEST_ID, $guest->getGuestId()))
            ->update([
                    DBField::FIRST_USER_ID => $request->user->id
                ]);
    }

    /**
     * @param $userAgent
     * @return bool
     */
    public static function getIsBot($userAgent, $ip)
    {
        if (!$ip)
            return true;

        if (!$userAgent) {
            return true;
        }

        foreach (self::getBotIpPrefixes() as $prefix) {
            if (strpos($ip, $prefix) === 0)
                return true;
        }

        if (in_array($ip, self::getBotIps()))
            return true;

        foreach (self::getBotPrefixes() as $prefix) {
            if (stripos($userAgent, $prefix) === 0)
                return true;
        }

        foreach (self::getBotSignatures() as $signature) {
            if(stripos($userAgent, $signature) !== FALSE)
                return true;
        }

        return in_array($userAgent, self::getBotUserAgents());
    }

    /**
     * @return array
     */
    public static function getBotIps()
    {
        return [
            '217.182.93.87', // Go HTTP Client from OVH
            '18.144.50.19',
            /*
             * WP Login Bots
             */

            '101.109.74.115',
            '101.180.144.152',
            '101.85.211.198',
            '103.12.211.57',
            '103.196.139.150',
            '103.217.166.143',
            '103.218.171.54',
            '103.220.204.143',
            '103.220.28.203',
            '103.227.116.70',
            '103.255.6.106',
            '103.41.36.250',
            '103.46.203.248',
            '103.5.3.240',
            '103.54.97.154',
            '103.72.10.180',
            '103.78.221.2',
            '103.8.115.51',
            '103.88.140.105',
            '104.238.51.92',
            '105.156.24.230',
            '105.184.14.161',
            '105.225.222.252',
            '105.255.145.174',
            '105.98.213.61',
            '106.161.139.94',
            '106.168.170.213',
            '106.51.154.253',
            '106.51.46.168',
            '107.150.63.170',
            '107.150.63.174',
            '107.179.216.155',
            '108.237.91.118',
            '109.103.76.194',
            '109.12.97.228',
            '109.122.95.100',
            '109.130.205.131',
            '109.172.171.227',
            '109.245.63.6',
            '109.49.246.144',
            '109.66.76.7',
            '109.73.216.99',
            '110.137.195.147',
            '110.159.114.52',
            '110.164.223.235',
            '110.36.116.110',
            '110.36.222.138',
            '110.67.35.168',
            '110.93.87.10',
            '111.110.60.227',
            '111.88.170.65',
            '112.134.3.163',
            '112.196.158.181',
            '112.198.243.253',
            '112.198.72.200',
            '112.198.73.85',
            '112.201.17.86',
            '112.202.174.137',
            '112.205.203.153',
            '112.208.47.71',
            '112.208.56.98',
            '116.193.131.126',
            '116.203.74.162',
            '116.240.235.153',
            '116.86.159.241',
            '117.193.234.117',
            '117.222.127.104',
            '117.58.146.46',
            '118.101.90.197',
            '118.70.128.227',
            '120.22.167.149',
            '120.29.65.198',
            '121.245.105.181',
            '121.7.54.98',
            '121.97.216.125',
            '122.175.128.238',
            '122.177.225.181',
            '122.2.255.77',
            '122.54.109.160',
            '122.54.127.16',
            '124.104.234.199',
            '124.121.210.92',
            '124.124.197.72',
            '124.150.94.39',
            '124.29.237.181',
            '124.43.19.123',
            '126.150.85.174',
            '126.48.178.63',
            '135.23.208.139',
            '138.185.108.35',
            '138.19.223.13',
            '14.12.120.0',
            '14.139.60.12',
            '14.169.195.66',
            '14.187.89.26',
            '14.202.7.187',
            '141.168.149.58',
            '142.54.177.2',
            '142.54.177.6',
            '143.202.112.199',
            '143.255.76.134',
            '146.241.21.17',
            '149.164.111.200',
            '151.73.157.34',
            '153.180.108.119',
            '154.16.165.253',
            '157.119.80.11',
            '157.50.13.179',
            '158.140.195.196',
            '160.177.130.143',
            '169.149.5.124',
            '170.247.56.1',
            '171.4.235.221',
            '173.208.169.252',
            '173.73.33.220',
            '174.17.83.154',
            '174.59.14.132',
            '175.138.246.176',
            '175.156.76.128',
            '175.157.124.39',
            '175.158.216.16',
            '176.63.76.173',
            '176.88.41.216',
            '177.1.122.42',
            '177.17.109.236',
            '177.17.124.81',
            '177.205.71.232',
            '177.42.194.164',
            '177.55.88.9',
            '178.116.153.241',
            '178.118.199.157',
            '178.155.4.85',
            '178.184.250.20',
            '178.221.234.221',
            '178.222.215.162',
            '178.79.13.152',
            '179.34.10.76',
            '179.57.137.92',
            '179.7.91.140',
            '180.151.205.124',
            '180.248.69.55',
            '180.251.89.2',
            '180.254.105.163',
            '180.254.254.129',
            '180.74.31.243',
            '181.139.48.202',
            '181.163.30.192',
            '181.164.250.152',
            '181.167.13.6',
            '181.223.162.94',
            '181.225.107.237',
            '181.231.52.183',
            '181.239.41.35',
            '181.26.104.195',
            '181.64.237.105',
            '181.75.191.96',
            '182.185.245.29',
            '182.187.101.65',
            '182.188.73.94',
            '182.253.48.82',
            '182.65.17.186',
            '183.87.216.174',
            '183.87.37.58',
            '184.157.43.79',
            '185.121.211.91',
            '185.165.29.138',
            '185.94.193.76',
            '186.2.179.39',
            '187.0.72.69',
            '187.111.210.117',
            '187.182.148.220',
            '187.183.37.87',
            '187.188.64.139',
            '187.190.166.38',
            '187.40.154.80',
            '188.135.48.201',
            '188.170.74.42',
            '188.187.28.57',
            '188.191.237.100',
            '188.192.49.17',
            '189.11.101.63',
            '189.174.16.236',
            '189.6.234.43',
            '189.71.70.1',
            '190.101.93.12',
            '190.164.10.95',
            '190.197.21.34',
            '190.223.54.2',
            '190.235.229.176',
            '190.238.172.20',
            '190.242.24.130',
            '191.19.94.128',
            '191.98.196.12',
            '192.116.130.20',
            '192.228.141.45',
            '193.227.162.14',
            '194.160.88.82',
            '195.211.86.88',
            '196.20.180.116',
            '196.20.238.162',
            '196.65.204.81',
            '197.14.132.252',
            '197.159.0.177',
            '197.251.135.184',
            '197.27.84.87',
            '197.31.110.1',
            '197.50.126.218',
            '198.204.225.114',
            '198.204.253.50',
            '199.192.142.54',
            '2.44.223.212',
            '2.44.236.194',
            '2.50.39.207',
            '2.51.8.207',
            '2.80.253.36',
            '2.88.43.235',
            '200.138.74.143',
            '200.52.25.118',
            '201.148.89.33',
            '201.229.7.50',
            '201.68.249.143',
            '202.136.92.35',
            '202.179.25.0',
            '202.62.17.77',
            '202.69.45.21',
            '202.80.212.118',
            '210.187.183.185',
            '212.112.134.132',
            '212.98.121.101',
            '213.149.62.252',
            '213.172.248.3',
            '213.178.242.106',
            '213.94.145.161',
            '213.98.196.61',
            '217.39.67.228',
            '222.104.207.187',
            '223.205.234.43',
            '223.237.139.103',
            '24.108.29.38',
            '24.133.33.52',
            '24.135.164.225',
            '24.139.11.148',
            '24.178.226.16',
            '24.236.194.186',
            '24.243.128.210',
            '27.126.28.198',
            '27.255.205.66',
            '27.6.185.141',
            '31.0.124.208',
            '31.176.191.196',
            '31.193.222.10',
            '31.223.40.196',
            '31.45.162.60',
            '36.102.210.45',
            '36.255.233.191',
            '36.69.166.207',
            '36.83.104.155',
            '36.84.13.139',
            '36.85.252.96',
            '36.85.65.109',
            '37.201.192.151',
            '37.23.203.150',
            '37.47.44.118',
            '37.77.124.196',
            '39.42.131.203',
            '39.42.90.114',
            '39.47.251.47',
            '39.54.118.138',
            '39.54.14.248',
            '41.143.9.163',
            '41.164.152.125',
            '41.207.216.219',
            '41.209.89.217',
            '41.230.79.158',
            '41.235.102.33',
            '42.115.138.23',
            '43.224.131.15',
            '43.241.194.63',
            '43.249.39.230',
            '43.252.213.152',
            '45.115.59.166',
            '45.124.58.150',
            '45.127.48.101',
            '45.49.130.40',
            '46.196.21.117',
            '46.240.238.32',
            '46.40.33.243',
            '47.182.67.153',
            '47.53.149.208',
            '49.144.127.220',
            '49.145.133.237',
            '49.150.16.64',
            '49.150.81.10',
            '49.205.42.180',
            '49.207.177.198',
            '49.207.191.120',
            '5.167.194.150',
            '5.37.150.49',
            '5.64.251.3',
            '5.82.127.134',
            '5.87.179.9',
            '50.66.201.219',
            '50.71.58.87',
            '50.98.108.73',
            '51.36.94.247',
            '58.182.171.74',
            '58.78.12.177',
            '59.189.184.117',
            '59.89.206.29',
            '60.227.95.23',
            '60.50.199.152',
            '61.6.230.177',
            '61.6.230.6',
            '61.6.6.250',
            '61.69.210.162',
            '62.210.247.187',
            '67.164.251.187',
            '67.204.243.169',
            '68.37.56.197',
            '70.48.196.17',
            '70.50.122.150',
            '70.66.56.15',
            '72.211.255.79',
            '72.227.72.78',
            '72.252.105.119',
            '73.0.208.50',
            '73.137.153.141',
            '73.138.212.66',
            '73.170.143.196',
            '73.171.186.228',
            '73.98.27.183',
            '75.162.193.9',
            '77.140.201.98',
            '77.22.35.172',
            '77.49.86.118',
            '78.15.207.66',
            '78.154.143.158',
            '78.159.23.16',
            '78.197.159.79',
            '78.21.142.57',
            '78.210.132.50',
            '78.251.220.37',
            '78.56.192.2',
            '78.95.23.25',
            '79.104.7.97',
            '79.117.40.49',
            '79.24.232.65',
            '79.44.1.86',
            '80.85.63.127',
            '81.133.200.80',
            '81.182.145.156',
            '81.206.135.68',
            '81.206.75.184',
            '81.99.203.65',
            '82.131.135.105',
            '82.140.128.154',
            '82.149.98.92',
            '82.178.115.11',
            '82.222.200.17',
            '82.232.27.16',
            '82.34.88.55',
            '82.47.227.252',
            '83.176.9.66',
            '83.39.35.91',
            '84.181.96.81',
            '84.193.8.114',
            '84.217.80.134',
            '84.252.31.105',
            '84.3.93.98',
            '85.105.84.215',
            '85.139.128.33',
            '85.194.2.114',
            '85.201.138.196',
            '85.233.153.26',
            '85.61.104.16',
            '86.100.252.19',
            '86.16.119.190',
            '86.214.192.111',
            '86.98.216.240',
            '86.98.67.192',
            '86.99.141.172',
            '87.110.175.67',
            '87.114.80.119',
            '87.61.157.127',
            '88.105.117.11',
            '88.119.49.130',
            '88.179.145.169',
            '88.181.60.225',
            '88.240.5.118',
            '89.117.26.109',
            '89.123.124.47',
            '89.139.96.54',
            '89.141.57.215',
            '89.176.135.34',
            '89.190.78.138',
            '89.70.224.233',
            '89.73.176.189',
            '90.148.169.212',
            '90.203.134.65',
            '90.206.12.157',
            '90.227.62.68',
            '90.42.217.52',
            '90.43.132.62',
            '90.61.133.33',
            '91.117.225.169',
            '91.162.28.131',
            '91.214.221.231',
            '92.190.110.196',
            '92.28.31.119',
            '93.151.240.148',
            '93.35.121.109',
            '93.36.189.210',
            '93.40.230.247',
            '93.66.136.192',
            '93.67.176.55',
            '93.75.25.237',
            '94.122.111.233',
            '94.207.175.143',
            '94.21.31.238',
            '94.248.230.165',
            '94.254.169.7',
            '94.52.12.235',
            '94.61.209.67',
            '94.68.109.174',
            '94.74.254.59',
            '95.121.232.222',
            '95.140.198.78',
            '95.147.63.210',
            '95.181.2.176',
            '95.208.248.127',
            '95.233.86.24',
            '95.235.106.194',
            '95.31.35.93',
            '95.65.126.72',
            '95.65.149.133',
            '95.78.255.185',
            '95.94.225.31',
            '97.90.88.25',
            '98.118.25.207',
            '98.212.150.198',
            '98.224.186.75',
            '98.254.124.188',
            '99.7.197.34',
            '148.251.48.200',
            '192.99.66.206',
            '54.85.182.120',
            '37.17.172.122',
            '185.92.73.105',
            '54.201.143.242',
            '54.193.93.53',
            '211.115.79.122',
            '210.103.250.230',
            '198.12.125.86',
            '206.222.22.82'
        ];
    }

    /**
     * @return array
     */
    public static function getBotIpPrefixes()
    {
        return [
            '34.228.130.', // AWS
            '217.182.93.', // OVH Scraper
            '138.197.7.', // Digital Ocean Scraper
            '159.203.91.', // Digital Ocean Scraper
            '148.251.48.', // German Product Hunt Scraper
            '54.85.182.', // AWS
            '37.17.172.', // Hungarian Scraper
            '18.144.50.', // Scraper
            '185.6.8.', // Scraper
            '185.92.73.', // Scraper
            '54.201.143.', // Scraper
            '54.193.93.', // Scraper
            '211.115.79.', // Scraper
            '210.103.250.', // Scraper
            '198.12.125.', // XMPRPC
        ];
    }

    /**
     * @return array
     */
    public static function getBotPrefixes()
    {
        return [
            'SafeDNSBot',
            'DomainCrawler',
            'rogerbot',
            'Wget',
            'ApacheBench',
            'Tumblr',
            'larbin',
            'Java',
            'CCBot',
            'Python',
            //'PayPal IPN', //creates problems with callback and postProcessGuest
            'AntBot',
            'Yeti',
            'yeggi',
            'Twitterbot',
            'ShowyouBot',
            'Mediapartners-Google',
            'ContextAd Bot',
            'slurp',
            'AdsBot',
            'zerbybot',
            'yacybot',
            'WordPress',
            'Superfeedr bot',
            'squirrobot',
            'SEOENGWorldBot',
            'curl',
            'woobot',
            'CloudEndure Scanner',
            'facebookexternalhit'
        ];
    }

    /**
     * @return array
     */
    public static function getBotSignatures()
    {
        return [
            'Daum',
            'Mastodon',
            'R6_CommentReader',
            'okhttp',
            'CipaCrawler',
            'Discordbot',
            'SMTBot',
            'trendiction',
            'newspaper',
            'com.apple.Safari.SearchHelper',
            'Guzzle',
            'Dalvik',
            'DotBot',
            'SeznamBot',
            'SemrushBot',
            'Magic Browser',
            'googlebot',
            'AhrefsBot',
            'MJ12bot',
            'Feedfetcher-Google',
            'Mail.RU_Bot',
            'GrapeshotCrawler',
            'metauri.com',
            'MetaURI',
            'CloudEndure Scanner',
            'woobot',
            'LongURL API',
            'WhatWeb',
            'Crowsnest',
            'masscan',
            'loaderio',
            'Slackbot',
            'DomainTunoCrawler',
            'Uptimebot',
            'ZmEu',
            'Scanbot',
            'MixrankBot',
            'meanpathbot',
            'OpenHoseBot',
            'NetcraftSurveyAgent',
            'SkypeUriPreview',
            'KomodiaBot',
            'Google Page Speed Insights',
            'YandexBot',
            'ShortLinkTranslate',
            'EveryoneSocialBot',
            'zitebot',
            'kraken',
            'Applebot',
            'redditbot',
            'PaperLiBot',
            'TweetmemeBot',
            'facebookexternalhit',
            'Exabot',
            'Domain Re-Animator Bot',
            'Barkrowler',
            'RankingBot',
            'Feedbin',
            'Feeder.co',
            'ltx71',
            'eContext',
            'SurdotlyBot',
            'FeedlyBot',
            'zgrab',
            'Nmap',
            'AnyConnect',
            'ips-agent',
            'Garlik',
            'DF Bot',
            'DnyzBot',
            'Sideqik',
            'linkdexbot',
            'Acoon',
            'FBL range check',
            'DomainSONOCrawler',
            'Nuzzel',
            'libwww-perl',
            'Embed PHP Library',
            'Go-http-client',
            'Scrapy',
            'netEstate',
            'VelenPublicWebCrawler',
            'Google-speakr',
            'ESC-Bot'
        ];
    }

    /**
     * @return array
     */
    public static function getBotUserAgents()
    {
        return [
            'Google-speakr',
            'Go-http-client/1.1',
            'Dalvik/2.1.0 (Linux; U; Android 5.0.2; prime Build/LRX22G)',
            'Nuzzel',
            'Mozilla/5.0 (compatible; DomainSONOCrawler/0.1; +http://domainsono.com)',
            'FBL range check',
            'Acoon v4.9.5 (www.acoon.de)',
            'Mozilla/5.0 (compatible; linkdexbot/2.2; +http://www.linkdex.com/bots/)',
            'Sideqik +http://www.sideqik.com',
            'Mozilla/5.0 (compatible; DnyzBot/1.0)',
            'DF Bot 1.0',
            'GarlikCrawler/1.2 (http://garlik.com/, crawler@garlik.com)',
            'Mozilla/5.0 (compatible; ips-agent)',
            'AnyConnect Darwin_i386 3.1.05160',
            'Mozilla/5.0 (compatible; Nmap Scripting Engine; https://nmap.org/book/nse.html)',
            'Mozilla/5.0 (compatible; SeznamBot/3.2; +http://napoveda.seznam.cz/en/seznambot-intro/)',
            'Mozilla/5.0 (compatible; SemrushBot/1.2~bl; +http://www.semrush.com/bot.html)',
            'Magic Browser',
            'ltx71 - (http://ltx71.com/)',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_8_2; Feeder.co) AppleWebKit/537.31 (KHTML, like Gecko) Chrome/37.0.2062.120 Safari/537.36',
            'RankingBot2 -- https://varocarbas.com/bot_ranking2/',
            'Barkrowler/0.5.1 (experimenting / debugging - sorry for your logs ) http://www.exensa.com/crawl - admin@exensa.com -- based on BuBiNG',
            'Mozilla/5.0 (compatible; SeznamBot/3.2; +http://fulltext.sblog.cz/)',
            'WIRE/0.22 (Linux; x86_64; Bot,Robot,Spider,Crawler)',
            'Mozilla/5.0 (compatible; EasouSpider; +http://www.easou.com/search/spider.html)',
            'Mozilla/5.0 (compatible; Exabot/3.0; +http://www.exabot.com/go/robot) ',
            'Mozilla/5.0 (compatible; Butterfly/1.0; +http://labs.topsy.com/butterfly/) Gecko/2009032608 Firefox/3.0.8',
            'Vienna 3.0.0 Beta 11 :9d8d6f6: rv:4790 (Macintosh; Mac OS X 10.8.3; en_GB)',
            'Mozilla/5.0 (compatible; Exabot/3.0 (BiggerBetter); +http://www.exabot.com/go/robot)',
            'Mozilla/5.0 (Windows; U; Windows NT 5.1; zh-CN; rv:1.8.0.11)  Firefox/1.5.0.11; 360Spider',
            'PercolateCrawler/3.1.30 (ops@percolate.com)',
            'NetNewsWire/3.3.2 (Mac OS X; http://netnewswireapp.com/mac/; gzip-happy)',
            'larbin_2.6.3 larbin2.6.3@unspecified.mail',
            'Mozilla/5.0 (compatible; Yahoo! Slurp; http://help.yahoo.com/help/us/ysearch/slurp)',
            'repparser/0.1 (python)',
            'CCBot/2.0',
            'Mozilla/5.0 (compatible; JikeSpider; +http://shoulu.jike.com/spider.html)',
            'msnbot-media/1.1 (+http://search.msn.com/msnbot.htm)',
            'Mozilla/5.0 (compatible; Baiduspider/2.0; +http://www.baidu.com/search/spider.html)',
            'Mozilla/5.0 (compatible; YandexBot/3.0; +http://yandex.com/bots)',
            'facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)',
            'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
            'ABCdatos BotLink/1.0.2 (test links)',
            'Ahoy! The Homepage Finder',
            'AlkalineBOT',
            'appie/1.1',
            'Arachnophilia',
            'Araneo/0.7 (araneo@esperantisto.net; http://esperantisto.net)',
            'AraybOt/1.0 (+http://www.araykoo.com/araybot.html)',
            'ArchitextSpider',
            'ASpider/0.09',
            'ATN_Worldwide',
            'Atomz/1.0',
            'AURESYS/1.0',
            'BackRub/*.*',
            'BaySpider',
            'bbot/0.100',
            'Big Brother',
            'Bjaaland/0.5',
            'BlackWidow',
            'Die Blinde Kuh',
            'borg-bot/0.9',
            'BoxSeaBot/0.5 (http://boxsea.com/crawler)',
            'Mozilla/3.01 (compatible;)',
            'BSpider/1.0 libwww-perl/0.40',
            'CACTVS Chemistry Spider',
            'Calif/0.6 (kosarev@tnps.net; http://www.tnps.dp.ua)',
            'Digimarc CGIReader/1.0',
            'Checkbot/x.xx LWP/5.x',
            'Mozilla/4.0 (compatible; ChristCrawler.com, ChristCrawler@ChristCENTRAL.com)',
            'cIeNcIaFiCcIoN.nEt Spider (http://www.cienciaficcion.net)',
            'CMC/0.01',
            'combine/0.0',
            'Confuzzledbot/X.X (+http://www.confuzzled.lu/bot/)',
            'CoolBot',
            'root/0.1',
            'cosmos/0.3',
            'Internet Cruiser Robot/2.1',
            'Cusco/3.2',
            'CyberSpyder/2.1',
            'CydralSpider/X.X (Cydral Web Image Search;',
            'DesertRealm.com; 0.2; [J];',
            'Deweb/1.01',
            'dienstspider/1.0',
            'Digger/1.0 JDK/1.3.0',
            'DIIbot',
            'grabber',
            'DNAbot/1.0',
            'DragonBot/1.0 libwww/5.0',
            'DWCP/2.0',
            'LWP::',
            'EIT-Link-Verifier-Robot/0.2',
            'Emacs-w3/v[0-9\.]+',
            'EMC Spider',
            'esculapio/1.1',
            'esther',
            'Evliya Celebi v0.151 - http://ilker.ulak.net.tr',
            'explorersearch',
            'FastCrawler 3.0.X (crawler@1klik.dk) - http://www.1klik.dk',
            'Hazel\'s Ferret Web hopper,',
            'ESIRover v1.0',
            'fido/0.9 Harvest/1.4.pl2',
            'Hämähäkki/0.2',
            'KIT-Fireball/2.0 libwww/5.0a',
            'Fish-Search-Robot',
            'Mozilla/2.0 (compatible fouineur v2.0; fouineur.9bit.qc.ca)',
            'Robot du CRIM 1.0a',
            'Freecrawl',
            'FunnelWeb-1.0',
            'gammaSpider xxxxxxx ()/',
            'gazz/1.0',
            'gcreep/1.0',
            'GetURL.rexx v1.05',
            'Golem/1.1',
            'Googlebot/2.X (+http://www.googlebot.com/bot.html)',
            'Gromit/1.0',
            'Gulliver/1.1',
            'Gulper Web Bot 0.2.4 (www.ecsl.cs.sunysb.edu/~maxim/cgi-bin/Link/GulperBot)',
            'havIndex/X.xx[bxx]',
            'AITCSRobot/1.1',
            'Hometown Spider Pro',
            'wired-digital-newsbot/1.5',
            'htdig/3.1.0b2',
            'HTMLgobble v2.2',
            'iajaBot/0.1',
            'IBM_Planetwide,',
            'gestaltIconoclast/1.0 libwww-FM/2.17',
            'INGRID/0.1',
            'Mozilla 3.01 PBWF (Win95)',
            'IncyWincy/1.0b1',
            'Informant',
            'InfoSeek Robot 1.0',
            'Infoseek Sidewinder',
            'InfoSpiders/0.1',
            'inspectorwww/1.0 http://www.greenpac.com/inspectorwww.html',
            'IAGENT/1.0',
            'I Robot 0.4 (irobot@chaos.dk)',
            'IsraeliSearch/1.0',
            'JBot (but can be changed by the user)',
            'JCrawler/0.2',
            'Mozilla/2.0 (compatible; Ask Jeeves/Teoma)',
            'JoBo (can be modified by the user)',
            'Jobot/0.1alpha libwww-perl/4.0',
            'JoeBot/x.x,',
            'JubiiRobot/version#',
            'jumpstation',
            'image.kapsi.net/1.0',
            'Katipo/1.0',
            'KDD-Explorer/0.1',
            'KO_Yappo_Robot/1.0.4(http://yappo.com/info/robot.html)',
            'LabelGrab/1.1',
            'larbin (+mail)',
            'legs',
            'Linkidator/0.93',
            'LinkWalker',
            'logo.gif crawler',
            'Lycos/x.x',
            'Magpie/1.0',
            'marvin/infoseek (marvin-team@webseek.de)',
            'M/3.8',
            'MediaFox/x.y',
            'MerzScope',
            'MindCrawler',
            'UdmSearch',
            'MOMspider/1.00 libwww-perl/0.40',
            'Monster/vX.X.X -$TYPE ($OSTYPE)',
            'Motor/0.2',
            'MSNBOT/0.1 (http://search.msn.com/msnbot.htm)',
            'Muninn/0.1 libwww-perl-5.76',
            'MuscatFerret/<version>',
            'MwdSearch/0.1',
            'User-Agent: Mozilla/4.0 (compatible; sharp-info-agent v1.0; )',
            'NDSpider/1.5',
            'NetCarta CyberPilot Pro',
            'NetMechanic',
            'NetScoop/1.0 libwww/5.0a',
            'newscan-online/1.1',
            'NHSEWalker/3.0',
            'Nomad-V2.x',
            'NorthStar',
            'ObjectsSearch/0.01',
            'Occam/1.0',
            'HKU WWW Robot,',
            'Orbsearch/1.0',
            'PackRat/1.0',
            'ParaSite/0.21 (http://www.ianett.com/parasite/)',
            'Patric/0.01a',
            'web robot PEGASUS',
            'Peregrinator-Mathematics/0.7',
            'PerlCrawler/1.0 Xavatoria/2.0',
            'Duppies',
            'phpdig/x.x.x',
            'PiltdownMan/1.0 profitnet@myezmail.com',
            'Mozilla/4.0 (compatible: Pimptrain\'s robot)',
            'Pioneer',
            'PortalJuice.com/4.0',
            'PGP-KA/1.2',
            'PlumtreeWebAccessor/0.9',
            'Poppi/1.0',
            'PortalBSpider/1.0 (spider@portalb.com)',
            'psbot/0.X (+http://www.picsearch.com/bot.html)',
            'straight FLASH!! GetterroboPlus 1.5',
            'Raven-v2',
            'Resume Robot',
            'RHCS/1.0a',
            'RixBot (http://www.oops-as.no/rix/)',
            'Road Runner: ImageScape Robot (lim@cs.leidenuniv.nl)',
            'Robbie/0.1',
            'ComputingSite Robi/1.0 (robi@computingsite.com)',
            'RoboCrawl (http://www.canadiancontent.net)',
            'Robofox v2.0',
            'Robozilla/1.0',
            'Roverbot',
            'RuLeS/1.0 libwww/4.0',
            'SafetyNet Robot 0.1,',
            'Scooter/2.0 G.R.A.B. V1.1.0',
            'not available',
            'Mozilla/4.0 (Sleek Spider/1.2)',
            'searchprocess/0.9',
            'Senrigan/xxxxxx',
            'SG-Scout',
            'Shai\'Hulud',
            'libwww-perl-5.41',
            'SimBot/1.0',
            'Site Valet',
            'SiteTech-Rover',
            'aWapClient',
            'Slurp/2.0',
            'Slurp/3.0',
            'ESISmartSpider/2.0',
            'Snooper/b97_01',
            'Solbot/1.0 LWP/5.07',
            'mouse.house/7.1',
            'SpiderBot/1.0',
            'spiderline/3.1.3',
            'Mozilla/4.0 (compatible; SpiderView 1.0;unix)',
            'ssearcher100',
            'suke/*.*',
            'suntek/1.0',
            'http://www.sygol.com',
            'Mozilla/3.0 (Black Widow v1.1.0; Linux 2.0.27; Dec 31 1997 12:25:00',
            'Tarantula/1.0',
            'tarspider',
            'dlw3robot/x.y (in TclX by http://hplyot.obspm.fr/~dl/)',
            'TechBOT',
            'Templeton/{version} for {platform}',
            'TitIn/0.2',
            'TITAN/0.1',
            'UCSD-Crawler',
            'UdmSearch/2.1.1',
            'uptimebot',
            'urlck/1.2.3',
            'URL Spider Pro',
            'Valkyrie/1.0 libwww-perl/0.40',
            'Verticrawlbot',
            'Victoria/1.0',
            'vision-search/3.0',
            'void-bot/0.1 (bot@void.be; http://www.void.be/)',
            'Voyager/0.0',
            'VWbot_K/4.2',
            'w3index',
            'W3M2/x.xxx',
            'CrawlPaper/n.n.n (Windows n)',
            'WWWWanderer v3.0',
            'w@pSpider/xxx (unix) by wap4.com',
            'WebCatcher/1.0',
            'WebCopy/(version)',
            'WebFetcher/0.8,',
            'weblayers/0.0',
            'WebLinker/0.0 libwww-perl/0.1',
            'WebMoose/0.0.0000',
            'Digimarc WebReader/1.2',
            'WebReaper [webreaper@otway.com]',
            'webs@recruit.co.jp',
            'webvac/1.0',
            'webwalk',
            'WebWalker/1.10',
            'WebWatch',
            'Wget/1.4.0',
            'whatUseek_winona/3.0',
            'wlm-1.1',
            'w3mir',
            'WOLP/1.0 mda/1.0',
            'WWWC/0.25 (Win95)',
            'XGET/0.7',
            'Nederland.zoek',
            'bingbot/2.0',
            'Baiduspider/2.0',
            'Pingdom.com_bot_version_1.4',
            'tech.support@clearspring.com',
            'Googlebot-Image/1.0',
            'Googlebot-Mobile/2.1',
            'magpie-crawler/1.1',
            'http://www.alexa.com/site/help/webmasters; crawler@alexa.com',
            'www.metadatalabs.com/mlbot',
            'Wotbox/2.01 (+http://www.wotbox.com/bot/)',
            'Mozilla/5.0 (iPhone; U; CPU iPhone OS 4_1 like Mac OS X; en-us) AppleWebKit/532.9 (KHTML, like Gecko) Version/4.0.5 Mobile/8B117 Safari/6531.22.7 (compatible; Googlebot-Mobile/2.1; +http://www.google.com/bot.html)',
            'rogerbot/1.0 (http://www.seomoz.org/dp/rogerbot, rogerbot-crawler@seomoz.org)',
            'Mozilla/5.0 (compatible; discoverybot/2.0; +http://discoveryengine.com/discoverybot.html)',
            'Mozilla/5.0 (compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm)',
            'Mozilla/5.0 (compatible; BeetleBot; )',
            'Mozilla/5.0 (compatible; Ezooms/1.0; ezooms.bot@gmail.com)',
            'msnbot/2.0b (+http://search.msn.com/msnbot.htm)',
            'SeznamBot/3.0 (+http://fulltext.sblog.cz/)',
            'Mozilla/5.0 (compatible; MJ12bot/v1.4.3; http://www.majestic12.co.uk/bot.php?+)',
            'Mozilla/5.0 (iPhone; U; CPU iPhone OS 4_1 like Mac OS X; en-us) AppleWebKit/532.9 (KHTML, like Gecko) Version/4.0.5 Mobile/8B117 Safari/6531.22.7 (compatible; Googlebot-Mobile/2.1; +http://www.google.com/bot.html)',
            'Mozilla/5.0 (compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm)',
            'msnbot/2.0b (+http://search.msn.com/msnbot.htm)',
            'Mozilla/5.0 (compatible; discoverybot/2.0; +http://discoveryengine.com/discoverybot.html)',
            'OpenLD.ORG Spider v0.1 (http://www.openwebspider.org/)',
            'Mozilla/5.0 (compatible; IstellaBot/1.10.2 +http://www.tiscali.it/)',
            'ShopWiki/1.0 ( +http://www.shopwiki.com/wiki/Help:Bot)',
            'Mozilla/5.0 (compatible; Ezooms/1.0; ezooms.bot@gmail.com)',
            'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.0; CHKD 1.2)',
            'Mozilla/5.0 (compatible; 008/0.83; http://www.80legs.com/webcrawler.html) Gecko/2008032620',
            'Mozilla/5.0 (compatible; WBSearchBot/1.1; +http://www.warebay.com/bot.html)',
            'ichiro/3.0 (http://help.goo.ne.jp/door/crawler.html)',
            'BacklinkCrawler (http://www.backlinktest.com/crawler.html)',
            'check_http/v1.4.16 (nagios-plugins 1.4.16)',
            'Pingdom.com_bot_version_1.4_(http://www.pingdom.com/)',
            'Mozilla/5.0 (compatible; 008/0.85; http://www.80legs.com/webcrawler.html) Gecko/2008032620',
            'Mozilla/5.0 (compatible; SISTRIX Crawler; http://crawler.sistrix.net/)',
            'Mozilla/5.0 (compatible; Memorybot/1.10.51 +http://www.mignify.com/)',
            'SearchBot',
            'parsijoo',
            'Mozilla/4.0 (compatible; MSIE 8.0; Windows NT 6.1; Trident/4.0; SLCC2; .NET CLR 2.0.50727; .NET CLR 3.5.30729; .NET CLR 3.0.30729; Media Center PC 6.0; MDDR; .NET4.0C; .NET4.0E; .NET CLR 1.1.4322; Tablet PC 2.0); 360Spider',
            'Mozilla/5.0 (Windows; U; Windows NT 5.1; zh-CN; rv:1.8.0.11) Gecko/20070312 Firefox/1.5.0.11; 360Spider',
            'DoCoMo/2.0 P900i(c100;TB;W24H11) (compatible; ichiro/mobile goo; +http://search.goo.ne.jp/option/use/sub4/sub4-1/)',
            'SolomonoBot/1.04 (http://www.solomono.ru)',
            'crawl/0.4 libcrawl/0.3',
            'ia_archiver (+http://www.alexa.com/site/help/webmasters; crawler@alexa.com)',
            'Mozilla/5.0 (compatible; spbot/3.1; +http://www.seoprofiler.com/bot )',
            'rogerbot/1.0 (http://www.seomoz.org/dp/rogerbot, rogerbot-crawler+pr1-crawler-02@seomoz.org)',
            'DoCoMo/2.0 P900i(c100;TB;W24H11) (compatible; ichiro/mobile goo;+http://search.goo.ne.jp/option/use/sub4/sub4-1/)',
            'Mozilla/5.0 (compatible; AcoonBot/4.11.1; +http://www.acoon.de/robot.asp)',
            'Java/1.6.0_04',
            'rogerbot/1.0 (http://www.seomoz.org/dp/rogerbot, rogerbot-crawler+pr1-crawler-04@seomoz.org)',
            'www.integromedb.org/Crawler',
            'libwww-perl/5.79',
            'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.4 (KHTML, like Gecko; Google Web Preview) Chrome/22.0.1229 Safari/537.4',
            'Mozilla/4.0 (compatible;  Vagabondo/4.0; webcrawler at wise-guys dot nl; http://webagent.wise-guys.nl/; http://www.wise-guys.nl/)',
            'Mozilla/5.0 (Windows; U; Windows NT 5.1; fr; rv:1.8.1) VoilaBot BETA 1.2 (support.voilabot@orange-ftgroup.com)',
            'Linguee Bot (http://www.linguee.com/bot; bot@linguee.com)',
            'W3C-mobileOK/DDC-1.0 (see http://www.w3.org/2006/07/mobileok-ddc)',
            'Pinterest/0.1 +http://pinterest.com/',
            'WebCopier vx.xa',
            'lwp-trivial/1.35',
            'WordPress/x.x.x.x PHP/4.x.xx',
            'node-oembed/0.1.0 (http://github.com/astro/node-oembed)',
            'ActiveBookmark 1.x',
            'ANTFresco/x.xx',
            'Mozilla/5.0 (compatible; Blekkobot; ScoutJet; +http://blekko.com/about/blekkobot)',
            'magpie-crawler/1.1 (U; Linux amd64; en-GB; +http://www.brandwatch.net)',
            'Sogou web spider/4.0(+http://www.sogou.com/docs/help/webmasters.htm#07)',
            'Mozilla/5.0 (compatible; PageAnalyzer/1.5;)',
            'Go 1.1 package http',
            'Mozilla/5.0 (compatible; MJ12bot/v1.4.5; http://www.majestic12.co.uk/bot.php?+)',
            'Mozilla/5.0 (compatible; DuckDuckGo-Favicons-Bot/1.0; +http://duckduckgo.com)',
            'ApacheBench/2.3',
            'curl/7.29.0',
            'CRAZYWEBCRAWLER 0.9.4, http://www.crazywebcrawler.com',
            'Mozilla/5.0 (compatible; AhrefsBot/5.0; +http://ahrefs.com/robot/)',
            'Mozilla/5.0 (Windows; U; Windows NT 5.1; en; rv:1.9.0.13) Gecko/2009073022 Firefox/3.5.2 (.NET CLR 3.5.30729) SurveyBot/2.3 (DomainTools)',
            'NerdyBot',
            'curl/7.35.0',
            'Slackbot-LinkExpanding 1.0 (+https://api.slack.com/robots)',
            '(compatible; MSIE 6.0; Windows NT 5.1; Netcraft SSL Server Survey - contact info@netcraft.com)',
            'WhatWeb/0.4.8-dev',
            'LSSRocketCrawler/1.0 LightspeedSystems',
            'Mozilla/5.0 (compatible; NetcraftSurveyAgent/1.0; +info@netcraft.com)',
            'the beast',
            'woobot/2.0',
            'W3C_Validator/1.3',
            'Google favicon',
            'Mozilla',
            'Mozilla/5.0 (compatible; GimmeUSAbot/1.0; +https://gimmeusa.com/pages/crawler)',
            'Mozilla/5.0 (compatible; MixrankBot; crawler@mixrank.com)',
            'woobot',
            'loaderio;verification-bot',
            'woobot/1.1',
            'Mozilla/5.0 ()',
            'Jamie\'s Spider (http://jamiembrown.com/)',
            'Go-http-client/2.0',
            'facebookexternalhit/1.1',
            'Mozilla/5.0 (compatible; Exabot/3.0; +http://www.exabot.com/go/robot)',
            'Domain Re-Animator Bot (http://domainreanimator.com) - support@domainreanimator.com',
            'NodePing',
            'electron-builder'
        ];
    }
}

/**
 * @param Request $request
 * @return bool
 */
function tracking_skip_cookie(Request $request)
{
    $ext = FilesToolkit::get_file_extension($request->path, true);
    return in_array($ext, ['js', 'css', 'jpg', 'jpeg', 'png', 'map'])
        || $request->app == 'images'
        || stripos('/cga/', $request->path) === 0
        || stripos('/game-controller/', $request->path) === 0
        || stripos('/host-controller/', $request->path) === 0;
}

class SessionTrackingManager extends BaseEntityManager
{

    protected $entityClass = SessionEntity::class;
    protected $table = Table::SessionTracking;
    protected $table_alias = TableAlias::SessionTracking;
    protected $pk = DBField::SESSION_ID;
    const GNS_KEY_PREFIX = GNS_ROOT.'.sessions';
    const ID_NO_COOKIES = 0;

    public static $fields = [
        DBField::SESSION_ID,
        DBField::SESSION_HASH,
        DBField::GUEST_ID,
        DBField::REQUEST_ID,
        DBField::IS_FIRST_SESSION_OF_GUEST,
        DBField::FIRST_USER_ID,
        DBField::GEO_REGION_ID,
        DBField::COUNTRY,
        DBField::ET_ID,
        DBField::UI_LANGUAGE_ID,
        DBField::DEVICE_TYPE_ID,
        DBField::ORIGINAL_REFERRER,
        DBField::ORIGINAL_URL,
        DBField::PARAMS,
        DBField::HTTP_USER_AGENT,
        DBField::ACQ_MEDIUM,
        DBField::ACQ_SOURCE,
        DBField::ACQ_CAMPAIGN,
        DBField::ACQ_TERM,
        DBField::CREATE_TIME,
        DBField::IP
    ];

    public $removed_json_fields = [
        DBField::IP,
        DBField::HTTP_USER_AGENT,
        DBField::FIRST_USER_ID
    ];

    /**
     * @param $session_hash
     * @return string
     */
    public static function generateCacheKey($session_hash)
    {
        return GuestTrackingManager::GNS_KEY_PREFIX.'.sessions.'.$session_hash;
    }

    /**
     * @param Request $request
     * @param $sessionId
     * @return SessionEntity
     * @throws ObjectNotFound
     */
    public function getSessionById(Request $request, $sessionId)
    {
        return $this->query($request->db)
            ->filter($this->filters->byPk($sessionId))
            ->get_entity($request);
    }

    /**
     * @param Request $request
     * @param $sessionIds
     * @param bool $fetchCountries
     * @return array|SessionEntity[]
     */
    public function getSessionsByIds(Request $request, $sessionIds, $fetchCountries = true)
    {
        $countriesManager = $request->managers->countries();


        /** @var SessionEntity[] $sessions */
        $sessions = $this->query($request->db)
            ->filter($this->filters->byPk($sessionIds))
            ->get_entities($request);

        if ($sessions) {
            foreach ($sessions as $session) {

                if ($fetchCountries)
                    $country = $countriesManager->getCountryById($request, $session->getCountryId());
                else
                    $country = [];

                $deviceName = GuestTrackingManager::getDeviceTypeName($session->getDeviceTypeId());

                $session->updateField('orig_country', $country);
                $session->updateField(VField::DEVICE_TYPE_NAME, $deviceName);
            }
            $sessions = array_index($sessions, $this->getPkField());
        }

        return $sessions;

    }

    /**
     * @param $request
     * @param $sessionHash
     * @param string $table
     * @return array|SessionEntity
     */
    public function getSessionByHash(Request $request, $sessionHash, $table = Table::SessionTracking)
    {
        return $this->query($request->db)
            ->table($table)
            ->filter(Q::Eq(DBField::SESSION_HASH, $sessionHash))
            ->get_entity($request);
    }

    /**
     * @param Request $request
     * @param $sessionHashes
     * @param string $table
     * @return SessionEntity[]
     */
    public function getSessionsByHashes(Request $request, $sessionHashes, $table = Table::SessionTracking)
    {
        return $this->query($request->db)
            ->table($table)
            ->filter(Q::Eq(DBField::SESSION_HASH, $sessionHashes))
            ->get_entities($request);
    }

    /**
     * @param $request
     * @param $guestHash
     * @return array
     */
    public function getSessionsByGuestHash($request, $guestHash)
    {
        return $this->query($request->db)
            ->filter($this->filters->byGuestHash($guestHash))
            ->get_list();
    }

    /**
     * @param $is_bot
     * @return string
     */
    public static function getTableByIsBot($is_bot)
    {
        return $is_bot ? Table::BotSessionTracking : Table::SessionTracking;
    }

    /**
     * @param Request $request
     * @param Guest $guest
     * @param string $table
     * @param int $user_id
     * @return array|mixed|null|SessionEntity
     */
    public function checkCreateSessionRecord(Request $request, Guest $guest, $table = Table::SessionTracking, $user_id = UsersManager::ANONYMOUS_USER_ID)
    {
        $skipCookie = tracking_skip_cookie($request);

        try {
            // First, let's see if this session is active in our cache.
            $session = $request->cache[$this->generateCacheKey($guest->getSessionHash())];
        } catch (CacheEntryNotFound $c) {
            // If not, let's see if it's a valid session in our DB in case the cache failed. We will act on the validity
            // after we set up the data needed for recording a session.
            $session = $this->getSessionByHash($request, $guest->getSessionHash(), $table);

            // Let's understand where we're at, and where the user came from. HTTP headers and URL parameters are both
            // used when understanding a session's origin.
            $refererParser = new Parser();
            $referer = $refererParser->parse($request->getReferer(), $request->getCurrentUrlPath());

            // If our referer is valid and matches our checks, we should define the implied and explicit UTM parameters.
            // HTTP header data is more trustworthy than explicit URL parameters' so let's try to match that first.
            if ($referer->isValid()) {
                $utm_medium = $referer->getMedium();
                $utm_medium = $utm_medium ? $utm_medium : $request->get->utmMedium();
                $utm_source = $referer->getSource();
                $utm_source = $utm_source ? $utm_source : $request->get->utmSource();
                $utm_term = $referer->getSearchTerm();
                $utm_term = $utm_term ? $utm_term : $request->get->utmTerm();
            } else {
                // If we don't have any acquisition source data detected in our referrer headers, let's check the url
                // parameters.
                $utm_medium = $request->get->utmMedium();
                $utm_source = $request->get->utmSource();
                $utm_term = $request->get->utmTerm();
            }
            // If we for some reason failed referrer data validation but the referrer is actually set, let's set the
            // UTM medium to referral and the source to the referring domain.
            if (!$utm_medium && $request->getReferer() && !$request->isInternalReferer()) {
                $utm_medium = EmailTypesManager::UTM_MEDIUM_REFERRAL;
                $utm_source = $request->getRefererDomain();
            }
            // If we for some reason still don't have a source and this is referral traffic, force the UTM source to be
            // the referring domain.
            if (!$utm_source && $utm_medium == EmailTypesManager::UTM_MEDIUM_REFERRAL)
                $utm_source = $request->getRefererDomain();

            // Finally we can store the session record to DB and set all applicable cookies for the user.
            if (!$session) {

                if (!$geoRegionId = $request->geoRegion->getPk())
                    $geoRegionId = GeoRegionsManager::GEO_ID_ALL;

                $excludeParams = [
                    GetRequest::PARAM_MAGIC,
                    GetRequest::PARAM_EXP,
                    GetRequest::PARAM_IDENT
                ];
                $paramString = $request->get->buildQuery('?', $excludeParams);

                $session = [
                    DBField::SESSION_HASH => $guest->getSessionHash(),
                    DBField::REQUEST_ID => $request->requestId,
                    DBField::IS_FIRST_SESSION_OF_GUEST => $guest->is_new_guest() ? 1 : 0,
                    DBField::GUEST_ID => $guest->getGuestId(),
                    DBField::FIRST_USER_ID => $user_id > 0 ? $user_id : null,
                    DBField::ORIGINAL_REFERRER => truncate_string($request->getReferer(), 1024),
                    DBField::ORIGINAL_URL => $request->getCurrentUrlPath(),
                    DBField::PARAMS => $paramString,
                    DBField::GEO_REGION_ID => $geoRegionId,
                    DBField::COUNTRY => $request->geoIpMapping->getCountryId(),
                    DBField::ET_ID => $request->get->etId(),
                    DBField::UI_LANGUAGE_ID => $request->getUiLang(),
                    DBField::DEVICE_TYPE_ID => $guest->getDeviceTypeId(),
                    DBField::HTTP_USER_AGENT => $request->getUserAgent(),
                    DBField::IP => $request->ip,
                    DBField::ACQ_MEDIUM => $utm_medium,
                    DBField::ACQ_SOURCE => $utm_source,
                    DBField::ACQ_CAMPAIGN => $request->get->utmCampaign(),
                    DBField::ACQ_TERM => $utm_term
                ];

                $session_id = SessionTrackingManager::objects($request->db)
                    ->table($table)
                    ->add($session);
                $session[DBField::SESSION_ID] = $session_id;
            }

            if (!$session instanceof SessionEntity) {
                $c->set($session, $request->settings()->getSessionTimeout());
            } else {
                $c->set($session->getDataArray(true), $request->settings()->getSessionTimeout());
            }


            if (!$skipCookie) {

                $expire = time() + GuestTrackingManager::GUEST_SESSION_COOKIE_TIME;
                $request->setCookie(GuestTrackingManager::COOKIE_SESSION_HASH, $guest->getSessionHash(), $expire);
            }
        }

        if (!$session instanceof SessionEntity) {
            $session = $this->createEntity($session, $request);
        }

        // Renew Session Cookie if it's about to expire and continue a user's session until they maintain
        // the full duration of inactivity.
        $timeToExpiration = (strtotime($session->getCreateTime()) + HALF_AN_HOUR) - time();
        if ($timeToExpiration < FIVE_MINUTES && !$skipCookie) {
            $expire = TIME_NOW + GuestTrackingManager::GUEST_SESSION_COOKIE_TIME;
            $request->setCookie(GuestTrackingManager::COOKIE_SESSION_HASH, $guest->getSessionHash(), $expire);
        }

        return $session;
    }

    /**
     * @param Request $request
     * @param $session_hash
     * @param string $table
     * @return bool
     */
    public function checkSessionExists(Request $request, $session_hash, $table = Table::SessionTracking)
    {
        return $this->query($request->db)
            ->table($table)
            ->filter(Q::Eq(DBField::SESSION_HASH, $session_hash))
            ->exists();
    }

    /**
     * @param Request $request
     * @param SessionEntity $session
     * @param $userId
     * @param string $table
     * @return int
     */
    public function updateSessionRecordFirstUserId(Request $request, SessionEntity $session, $userId, $table = Table::SessionTracking)
    {
        $session->updateField(DBField::FIRST_USER_ID, $userId);
        Cache::set($this->generateCacheKey($session->getSessionHash()), $session->getDataArray(true), $request->settings()->getSessionTimeout());
        return $this->query($request->db)
            ->table($table)
            ->filter(Q::Eq(DBField::SESSION_HASH, $session[DBField::SESSION_HASH]))
            ->update([DBField::FIRST_USER_ID => $userId]);
    }

    public static function getUrlParamsAsString($get)
    {
        $params = $get;
        return $params ? '?'.http_build_query($params) : null;
    }

    public static function getUserAgent($request)
    {
        return array_get($request->server, 'HTTP_USER_AGENT', '');
    }

    public static function getCurrentPath($request)
    {
        return $request->scheme.'://'.$request->host.$request->path;
    }
}

/*
 *
 *
 * New Managers ESC 06/02/2018
 *
 *
 */


class DevicesManager extends BaseEntityManager {

    protected $entityClass = DeviceEntity::class;
    protected $table = Table::Device;
    protected $table_alias = TableAlias::Device;
    protected $pk = DBField::DEVICE_ID;
    protected $foreign_managers = [];

    public static $fields = [

    ];

    /**
     * @param DBManagerEntity $data
     * @param Request $request
     * @return DBManagerEntity|void
     */
    public function processVFields(DBManagerEntity $data, Request $request)
    {

    }

}

class DeviceTypesManager extends BaseEntityManager {

    protected $entityClass = DeviceTypeEntity::class;
    protected $table = Table::DeviceType;
    protected $table_alias = TableAlias::DeviceType;
    protected $pk = DBField::DEVICE_TYPE_ID;
    protected $foreign_managers = [];

    public static $fields = [

    ];

    /**
     * @param DBManagerEntity $data
     * @param Request $request
     * @return DBManagerEntity|void
     */
    public function processVFields(DBManagerEntity $data, Request $request)
    {

    }

}

class ApiLogsManager extends BaseEntityManager
{

    protected $entityClass = ApiLogEntity::class;
    protected $table = Table::ApiLog;
    protected $table_alias = TableAlias::ApiLog;
    protected $pk = DBField::API_LOG_ID;
    protected $foreign_managers = [];

    public static $fields = [
        DBField::API_LOG_ID,
        DBField::HEADERS,
        DBField::FILES,
        DBField::PARAMS,
        DBField::RESPONSE,
        DBField::CREATED_BY,
        DBField::MODIFIED_BY,
        DBField::DELETED_BY
    ];


    /**
     * @param Request $request
     * @param array $headers
     * @param array $files
     * @param array $params
     * @param array $response
     */
    public function insertApiLog(Request $request, $response = [])
    {
        $encodedResponse = json_encode($response);

        if (strlen($encodedResponse) > 10000000)
            $encodedResponse = json_encode("CLIPPED, TOO BIG");

        $data = [
            DBField::HEADERS => json_encode($request->headers),
            DBField::FILES => json_encode($request->files),
            DBField::PARAMS => json_encode($request->post),
            DBField::RESPONSE => $encodedResponse,
            DBField::CREATED_BY => $request->requestId
        ];

        $this->query($request->db)->add($data);
    }

}

class RequestsManager extends BaseEntityManager {

    protected $requestIsTracked = false;

    protected $entityClass = RequestEntity::class;
    protected $table = Table::Request;
    protected $table_alias = TableAlias::Request;
    protected $pk = DBField::REQUEST_ID;
    protected $foreign_managers = [];

    public static $fields = [
        DBField::REQUEST_ID,
        DBField::SESSION_ID,
        DBField::GUEST_ID,
        DBField::USER_ID,
        DBField::APPLICATION_USER_ACCESS_TOKEN_ID,
        DBField::CREATE_TIME,
        DBField::GEO_IP_MAP_ID,
        DBField::SCHEME,
        DBField::METHOD,
        DBField::HOST,
        DBField::APP,
        DBField::URI,
        DBField::PARAMS,
        DBField::REFERRER,
        DBField::ACQ_MEDIUM,
        DBField::ACQ_SOURCE,
        DBField::ACQ_CAMPAIGN,
        DBField::ACQ_TERM,
        DBField::IP_ADDRESS,
        DBField::RESPONSE_TIME,
        DBField::RESPONSE_CODE,
    ];

    /**
     * @param DBManagerEntity $data
     * @param Request $request
     * @return DBManagerEntity|void
     */
    public function processVFields(DBManagerEntity $data, Request $request)
    {

    }

    /**
     * @param Request $request
     * @param $requestId
     * @param $userId
     * @return bool
     */
    public function validateRequestByIdAndUserId(Request $request, $requestId, $userId)
    {
        return $this->query($request->db)
            ->filter($this->filters->byPk($requestId))
            ->filter($this->filters->byUserId($userId))
            ->exists();
    }

    /**
     * @return bool
     */
    public function request_is_tracked()
    {
        return $this->requestIsTracked;
    }

    /**
     * @param Request $request
     */
    public function trackRequest(Request $request, HttpResponse $response)
    {
        if (!$this->requestIsTracked) {

            $excludeParams = [
                GetRequest::PARAM_MAGIC,
                GetRequest::PARAM_EXP,
                GetRequest::PARAM_IDENT
            ];

            $requestData = [
                DBField::REQUEST_ID => $request->requestId,
                DBField::SESSION_ID => $request->user->session->getSessionId(),
                DBField::GUEST_ID => $request->user->guest->getGuestId(),
                DBField::USER_ID => $request->user->id,
                DBField::APPLICATION_USER_ACCESS_TOKEN_ID => $request->user->getApplicationUserAccessTokenId(),
                DBField::CREATE_TIME => $request->getCurrentSqlTime(),
                DBField::GEO_IP_MAP_ID => $request->geoIpMapping->getPk(),
                DBField::SCHEME => $request->scheme,
                DBField::METHOD => $request->method,
                DBField::HOST => $request->host,
                DBField::APP => $request->app,
                DBField::URI => $request->path,
                DBField::PARAMS => json_encode($request->get->params($excludeParams)),
                DBField::REFERRER => truncate_string($request->getReferer(), 1024),
                DBField::ACQ_MEDIUM => $request->get->utmMedium(),
                DBField::ACQ_SOURCE => $request->get->utmSource(),
                DBField::ACQ_CAMPAIGN => $request->get->utmCampaign(),
                DBField::ACQ_TERM => $request->get->utmTerm(),
                DBField::IP_ADDRESS => $request->getRealIp(),
                DBField::RESPONSE_TIME => $response->getResponseTime(),
                DBField::RESPONSE_CODE => $response->getCode(),
            ];

            if ($request->user->is_bot)
                $table = Table::BotRequest;
            else
                $table = Table::Request;

            $this->query()->table($table)->add($requestData);

            $this->requestIsTracked = true;
        }
    }

    /**
     * @param Request $request
     * @param $dateRangeTypeId
     * @return array
     */
    public function summarizeRequestByDateRangeTypeId(Request $request, $dateRangeTypeId)
    {
        $dateRangesManager = $request->managers->dateRanges();

        $fields = [
            $dateRangesManager->createPkField(),
            $this->field(DBField::APP),
            new CountDBField($dateRangesManager->getSumField(), $this->getPkField(), $this->getTable()),
            new AvgDBField($dateRangesManager->getAvgField(), $this->field(DBField::RESPONSE_TIME), $this->getTable(), 2)
        ];

        $dt = new DateTime($request->getCurrentSqlTime());

        $dt->modify("-3 month");

        /** @var array $requestSummary */
        $requestSummary = $this->query($request->db)
            ->set_connection($request->db->get_connection(SQLN_BI))
            ->fields($fields)
            ->left_join($dateRangesManager, $dateRangesManager->joinDateRangesFilter($this->field(DBField::CREATE_TIME)))
            ->filter($this->filters->Eq(DBField::HOST, array_values($request->config['hosts'])))
            ->filter($this->filters->Lte(DBField::CREATE_TIME, $request->getCurrentSqlTime()))
            ->filter($this->filters->Gte(DBField::CREATE_TIME, $dt->format(SQL_DATETIME)))
            ->filter($dateRangesManager->filters->Lte(DBField::START_TIME, $request->getCurrentSqlTime()))
            ->filter($dateRangesManager->filters->Gte(DBField::START_TIME, $dt->format('Y-m-d')))
            ->filter($dateRangesManager->filters->byDateRangeTypeId($dateRangeTypeId))
            ->group_by(1)
            ->group_by(2)
            ->get_list();

        return $requestSummary;
    }

    /**
     * @param Request $request
     * @param $dateRangeTypeId
     * @return array
     */
    public function summarizeAppRequestsByDateRangeTypeId(Request $request, $dateRangeTypeId)
    {
        $dateRangesManager = $request->managers->dateRanges();

        $kpiSummaryTypesDailyData = [
            KpiSummariesTypesManager::ID_REQUESTS_WWW => [],
            KpiSummariesTypesManager::ID_REQUESTS_PLAY => [],
            KpiSummariesTypesManager::ID_REQUESTS_API => [],
            KpiSummariesTypesManager::ID_REQUESTS_IMAGES => [],
            KpiSummariesTypesManager::ID_REQUESTS_GO => [],
            KpiSummariesTypesManager::ID_REQUESTS_DEVELOP => [],
        ];

        foreach ($this->summarizeRequestByDateRangeTypeId($request, $dateRangeTypeId) as $dailyRequestSummary) {
            switch ($dailyRequestSummary[DBField::APP]) {
                case 'www':
                    $kpiSummaryTypesDailyData[KpiSummariesTypesManager::ID_REQUESTS_WWW][] = [
                        $dateRangesManager->getPkField() => $dailyRequestSummary[DBField::DATE_RANGE_ID],
                        DBField::KPI_SUMMARY_TYPE_ID => KpiSummariesTypesManager::ID_REQUESTS_WWW,
                        $dateRangesManager->getSumField() => $dailyRequestSummary[$dateRangesManager->getSumField()],
                    ];
                    $kpiSummaryTypesDailyData[KpiSummariesTypesManager::ID_REQUESTS_WWW_MS][] = [
                        $dateRangesManager->getPkField() => $dailyRequestSummary[DBField::DATE_RANGE_ID],
                        DBField::KPI_SUMMARY_TYPE_ID => KpiSummariesTypesManager::ID_REQUESTS_WWW_MS,
                        $dateRangesManager->getSumField() => $dailyRequestSummary[$dateRangesManager->getAvgField()]
                    ];
                    break;
                case 'play':
                    $kpiSummaryTypesDailyData[KpiSummariesTypesManager::ID_REQUESTS_PLAY][] = [
                        $dateRangesManager->getPkField() => $dailyRequestSummary[DBField::DATE_RANGE_ID],
                        DBField::KPI_SUMMARY_TYPE_ID => KpiSummariesTypesManager::ID_REQUESTS_PLAY,
                        $dateRangesManager->getSumField() => $dailyRequestSummary[$dateRangesManager->getSumField()]
                    ];
                    $kpiSummaryTypesDailyData[KpiSummariesTypesManager::ID_REQUESTS_PLAY_MS][] = [
                        $dateRangesManager->getPkField() => $dailyRequestSummary[DBField::DATE_RANGE_ID],
                        DBField::KPI_SUMMARY_TYPE_ID => KpiSummariesTypesManager::ID_REQUESTS_PLAY_MS,
                        $dateRangesManager->getSumField() => $dailyRequestSummary[$dateRangesManager->getAvgField()]
                    ];

                    break;
                case 'api':
                    $kpiSummaryTypesDailyData[KpiSummariesTypesManager::ID_REQUESTS_API][] = [
                        $dateRangesManager->getPkField() => $dailyRequestSummary[DBField::DATE_RANGE_ID],
                        DBField::KPI_SUMMARY_TYPE_ID => KpiSummariesTypesManager::ID_REQUESTS_API,
                        $dateRangesManager->getSumField() => $dailyRequestSummary[$dateRangesManager->getSumField()]
                    ];

                    $kpiSummaryTypesDailyData[KpiSummariesTypesManager::ID_REQUESTS_API_MS][] = [
                        $dateRangesManager->getPkField() => $dailyRequestSummary[DBField::DATE_RANGE_ID],
                        DBField::KPI_SUMMARY_TYPE_ID => KpiSummariesTypesManager::ID_REQUESTS_API_MS,
                        $dateRangesManager->getSumField() => $dailyRequestSummary[$dateRangesManager->getAvgField()]
                    ];

                    break;
                case 'images':
                    $kpiSummaryTypesDailyData[KpiSummariesTypesManager::ID_REQUESTS_IMAGES][] = [
                        $dateRangesManager->getPkField() => $dailyRequestSummary[DBField::DATE_RANGE_ID],
                        DBField::KPI_SUMMARY_TYPE_ID => KpiSummariesTypesManager::ID_REQUESTS_IMAGES,
                        $dateRangesManager->getSumField() => $dailyRequestSummary[$dateRangesManager->getSumField()]
                    ];

                    $kpiSummaryTypesDailyData[KpiSummariesTypesManager::ID_REQUESTS_IMAGES_MS][] = [
                        $dateRangesManager->getPkField() => $dailyRequestSummary[DBField::DATE_RANGE_ID],
                        DBField::KPI_SUMMARY_TYPE_ID => KpiSummariesTypesManager::ID_REQUESTS_IMAGES_MS,
                        $dateRangesManager->getSumField() => $dailyRequestSummary[$dateRangesManager->getAvgField()]
                    ];

                    break;
                case 'go':
                    $kpiSummaryTypesDailyData[KpiSummariesTypesManager::ID_REQUESTS_GO][] = [
                        $dateRangesManager->getPkField() => $dailyRequestSummary[DBField::DATE_RANGE_ID],
                        DBField::KPI_SUMMARY_TYPE_ID => KpiSummariesTypesManager::ID_REQUESTS_GO,
                        $dateRangesManager->getSumField() => $dailyRequestSummary[$dateRangesManager->getSumField()]
                    ];
                    
                    $kpiSummaryTypesDailyData[KpiSummariesTypesManager::ID_REQUESTS_GO_MS][] = [
                        $dateRangesManager->getPkField() => $dailyRequestSummary[DBField::DATE_RANGE_ID],
                        DBField::KPI_SUMMARY_TYPE_ID => KpiSummariesTypesManager::ID_REQUESTS_GO_MS,
                        $dateRangesManager->getSumField() => $dailyRequestSummary[$dateRangesManager->getAvgField()]
                    ];
                    break;
                case 'develop':
                    $kpiSummaryTypesDailyData[KpiSummariesTypesManager::ID_REQUESTS_DEVELOP][] = [
                        $dateRangesManager->getPkField() => $dailyRequestSummary[DBField::DATE_RANGE_ID],
                        DBField::KPI_SUMMARY_TYPE_ID => KpiSummariesTypesManager::ID_REQUESTS_DEVELOP,
                        $dateRangesManager->getSumField() => $dailyRequestSummary[$dateRangesManager->getSumField()]
                    ];

                    $kpiSummaryTypesDailyData[KpiSummariesTypesManager::ID_REQUESTS_DEVELOP_MS][] = [
                        $dateRangesManager->getPkField() => $dailyRequestSummary[DBField::DATE_RANGE_ID],
                        DBField::KPI_SUMMARY_TYPE_ID => KpiSummariesTypesManager::ID_REQUESTS_DEVELOP_MS,
                        $dateRangesManager->getSumField() => $dailyRequestSummary[$dateRangesManager->getAvgField()]
                    ];
                    break;

            }
        }

        return $kpiSummaryTypesDailyData;
    }
}


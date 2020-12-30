<?php
/**
 * Created by PhpStorm.
 * User: christophercarter
 * Date: 11/29/18
 * Time: 12:13 PM
 */


Entities::uses('hosts-instances');

use \libphonenumber\PhoneNumberUtil;
use \libphonenumber\PhoneNumberFormat;

class HostsInstancesManager extends BaseEntityManager {

    const CLOUD_FLARE_ZONE_ID = '328a4bbffbed79fbcf5e39792ce1e591';

    const EXIT_STATUS_KILLED = 'killed';
    const EXIT_STATUS_TIMED_OUT = 'timed-out';
    const EXIT_STATUS_STOPPED = 'stopped';

    const DEFAULT_MAX_SEATS = 4;

    protected $entityClass = HostInstanceEntity::class;
    protected $table = Table::HostInstance;
    protected $table_alias = TableAlias::HostInstance;
    protected $pk = DBField::HOST_INSTANCE_ID;

    public static $fields = [
        DBField::HOST_INSTANCE_ID,
        DBField::HOST_INSTANCE_TYPE_ID,
        DBField::USER_ID,
        DBField::HOST_ID,
        DBField::HOST_DEVICE_ID,
        DBField::LOCATION_ID,
        DBField::ADDRESS_ID,
        DBField::HOST_VERSION_ID,
        DBField::PLATFORM_ID,
        DBField::NETWORK_ID,
        DBField::IS_PUBLIC,
        DBField::PUBLIC_HOST_DOMAIN,
        DBField::PUBLIC_HOST_NAME,
        DBField::PUBLIC_IP_ADDRESS,
        DBField::LOCAL_IP_ADDRESS,
        DBField::LOCAL_PORT,
        DBField::DNS_ID,
        DBField::DNS_IS_ACTIVE,
        DBField::PUB_SUB_CHANNEL,
        DBField::START_TIME,
        DBField::END_TIME,
        DBField::MAX_SEATS,
        DBField::LAST_PING_TIME,
        DBField::EXIT_STATUS,

        DBField::IS_ACTIVE,
        DBField::CREATED_BY,
        DBField::MODIFIED_BY,
        DBField::DELETED_BY
    ];

    public $foreign_managers = [
        HostsManager::class => DBField::HOST_ID,
        HostsInstancesTypesManager::class => DBField::HOST_INSTANCE_TYPE_ID,
        LocationsManager::class => DBField::LOCATION_ID,
        NetworksManager::class => DBField::NETWORK_ID,
        HostVersionsManager::class => DBField::HOST_VERSION_ID,
    ];

    /**
     * @param HostInstanceEntity $data
     * @param Request $request
     * @return DBManagerEntity|void
     */
    public function processVFields(DBManagerEntity $data, Request $request)
    {
        if (!$data->hasField(VField::GAME_INSTANCES))
            $data->updateField(VField::GAME_INSTANCES, []);

        if (!$data->hasField(VField::ACTIVATIONS))
            $data->updateField(VField::ACTIVATIONS, []);

        if (!$data->hasField(VField::HOST_INSTANCE_TYPE))
            $data->updateField(VField::HOST_INSTANCE_TYPE, []);

        if (!$data->hasField(VField::HOST_DEVICE))
            $data->updateField(VField::HOST_DEVICE, []);


        $isAdmin = $data->getUserId() == $request->user->id;

        $data->updateField(VField::USER_IS_HOST_ADMIN, $isAdmin);

        $uri = "/i/{$data->getPk()}/";
        $url = $request->getPlayUrl($uri);

        $localUrl = "{$data->getPublicHostName()}.{$data->getPublicHostDomain()}";

        $data->updateField(VField::URL, $url);
        $data->updateField(VField::LOCAL_URL, $localUrl);


        if ($request->settings()->is_dev()) {
            $smsNumber = $request->config['twilio']['test']['from_number'];
        } else {
            $smsNumber = $smsNumber = $request->config['twilio']['live']['from_number'];
        }

        try {

            $phoneUtil = PhoneNumberUtil::getInstance();
            $numberProto = $phoneUtil->parse($smsNumber, CountriesManager::ID_UNITED_STATES);
            $isValid = $phoneUtil->isValidNumber($numberProto);

            if ($isValid) {
                $find = ['(', ')', ' '];
                $replace = ['', '', '-'];
                $smsNumber = str_replace($find, $replace, $phoneUtil->format($numberProto, PhoneNumberFormat::NATIONAL));
            }

        } catch (\libphonenumber\NumberParseException $e) {

        }

        $data->updateField(VField::SMS_NUMBER, $smsNumber);

        if (!$data->hasField(VField::LOCATION))
            $data->updateField(VField::LOCATION, []);
        if (!$data->hasField(VField::USER))
            $data->updateField(VField::USER, []);
        if (!$data->hasField(VField::PLATFORM))
            $data->updateField(VField::PLATFORM, []);
        if (!$data->hasField(VField::HOST_VERSION))
            $data->updateField(VField::HOST_VERSION, []);
        if (!$data->hasField(VField::HOST))
            $data->updateField(VField::HOST, []);
    }

    /**
     * @param Request $request
     * @param $userId
     * @param $hostId
     * @param $networkId
     * @param $localIp
     * @param $localPort
     * @param null $startTime
     * @param null $endTime
     * @return HostInstanceEntity
     */
    public function createNewHostInstance(Request $request, $hostInstanceTypeId, $userId, $hostId, $locationId = null, $addressId = null, $networkId, $localIp,
                                          $localPort, $isPublic = 1, $startTime = null, $endTime = null,
                                          $hostVersionId = null, $platformId = null, $hostDeviceId = null,
                                          $maxSeats = self::DEFAULT_MAX_SEATS)
    {
        $publicHostDomain = 'qa1.playesc.com';
        //$publicHostDomain = $request->config['root_host'];

        $uuIdV4HostName = uuidV4HostName();

        $pubSubChannelHash = sha1("{$userId}-{$hostId}-{$startTime}-{$uuIdV4HostName}");

        $pubSubChannel = "host-{$pubSubChannelHash}";

        $data = [
            DBField::HOST_INSTANCE_TYPE_ID => $hostInstanceTypeId,
            DBField::USER_ID => $userId,
            DBField::HOST_ID => $hostId,
            DBField::HOST_DEVICE_ID => $hostDeviceId,
            DBField::LOCATION_ID => $locationId,
            DBField::ADDRESS_ID => $addressId,
            DBField::HOST_VERSION_ID => $hostVersionId,
            DBField::PLATFORM_ID => $platformId,
            DBField::NETWORK_ID => $networkId,
            DBField::IS_PUBLIC => $isPublic,
            DBField::PUBLIC_HOST_NAME => $uuIdV4HostName,
            DBField::PUBLIC_HOST_DOMAIN => $publicHostDomain,
            DBField::PUBLIC_IP_ADDRESS => $request->ip,
            DBField::LOCAL_IP_ADDRESS => $localIp,
            DBField::LOCAL_PORT => $localPort,
            DBField::PUB_SUB_CHANNEL => $pubSubChannel,
            DBField::MAX_SEATS => $maxSeats,
            DBField::START_TIME => $startTime,
            DBField::END_TIME => $endTime,
            DBField::LAST_PING_TIME => $startTime,
            DBField::IS_ACTIVE => 1
        ];

        /** @var HostInstanceEntity $hostInstance */
        $hostInstance = $this->query($request->db)->createNewEntity($request, $data);

        return $hostInstance;
    }

    /**
     * @param Request $request
     * @return FormField[]
     */
    public function getFormFields($hostInstanceTypes = [], $hosts = [], $networks = [], $locations = [])
    {
        $fields = [
            new SelectField(DBField::HOST_INSTANCE_TYPE_ID, 'Host Instance Type Id', $hostInstanceTypes, false),
            new IntegerField(DBField::HOST_ID, 'Host Id', true),
            new SelectField(DBField::NETWORK_ID, 'Network Id', $networks, false),
            new CharField(DBField::LOCAL_IP_ADDRESS, 'Local IP Address'),
            new IntegerField(DBField::LOCAL_PORT, 'Local Port'),
            new CharField(DBField::HOST_VERSION, 'Host app version number'),
            new SlugField(DBField::PLATFORM_SLUG, 'Url slug for platform', 32),
            new IntegerField(DBField::HOST_DEVICE_ID, 'Host Device Id', false),

            // Todo: Make this field required when we've updated host app
            new SelectField(DBField::LOCATION_ID, 'Location Id', $locations, false),

            // Location and state Data
            new CharField(DBField::CITY, 'City', 0, false),
            new CharField(DBField::COUNTRY_ID, 'Country Id', 2, false),
            new CharField(DBField::STATE, 'State Name', 0, false),
            new CharField(DBField::STREET_NAME, 'Street Name', 0, false),
            new CharField(DBField::STREET_NUMBER, 'Street Number', 0, false),
            new CharField(DBField::ZIP_CODE, 'Zip Code', 0, false),
            new CharField(DBField::LATITUDE, 'Latitude', 0, false),
            new CharField(DBField::LONGITUDE, 'Longitude', 0, false),

        ];

        return $fields;
    }

    /**
     * @param Request $request
     * @param HostEntity $host
     * @return HostInstanceEntity
     */
    public function generateOfflineHostInstance(Request $request, HostEntity $host)
    {
        $hostInstanceData = [
            DBField::HOST_INSTANCE_ID => -1,
            DBField::USER_ID => $host->getOwnerId(),
            DBField::HOST_ID => $host->getPk(),
            DBField::HOST_DEVICE_ID => null,
            DBField::LOCATION_ID => -1,
            DBField::ADDRESS_ID => -1,
            DBField::HOST_VERSION_ID => -1,
            DBField::PLATFORM_ID => -1,
            DBField::NETWORK_ID => -1,
            DBField::IS_PUBLIC => 1,
            DBField::PUBLIC_HOST_DOMAIN => null,
            DBField::PUBLIC_HOST_NAME => null,
            DBField::PUBLIC_IP_ADDRESS => null,
            DBField::LOCAL_IP_ADDRESS => null,
            DBField::LOCAL_PORT => null,
            DBField::DNS_ID => null,
            DBField::DNS_IS_ACTIVE => null,
            DBField::PUB_SUB_CHANNEL => $host->getPubSubChannel(),
            DBField::START_TIME => $request->getCurrentSqlTime(),
            DBField::END_TIME => null,
            DBField::MAX_SEATS => 80000,
            DBField::LAST_PING_TIME => $request->getCurrentSqlTime(),
            DBField::EXIT_STATUS => null,

            DBField::IS_ACTIVE => 1,
            DBField::CREATED_BY => $request->requestId,
            DBField::MODIFIED_BY => null,
            DBField::DELETED_BY => null
        ];

        /** @var HostInstanceEntity $hostInstance */
        $hostInstance = $this->createEntity($hostInstanceData, $request);
        $hostInstance->setHost($host);

        return $hostInstance;
    }

    /**
     * @param Request $request
     * @return SQLQuery
     */
    protected function queryJoinHostsNetworks(Request $request)
    {
        $hostsManager = $request->managers->hosts();
        $networksManager = $request->managers->networks();

        $fields = $this->selectAliasedManagerFields($hostsManager, $networksManager);

        $queryBuilder = $this->query($request->db)
            ->fields($fields)
            ->inner_join($hostsManager)
            ->inner_join($networksManager);

        return $queryBuilder;
    }

    /**
     * @param Request $request
     * @return SQLQuery
     */
    protected function queryJoinHosts(Request $request)
    {
        $hostsManager = $request->managers->hosts();

        $fields = $this->selectAliasedManagerFields($hostsManager);

        $queryBuilder = $this->query($request->db)
            ->fields($fields)
            ->inner_join($hostsManager);

        return $queryBuilder;
    }

    /**
     * @param Request $request
     * @return SQLQuery
     */
    protected function queryJoinHostsNetworksVersionsPlatforms(Request $request)
    {
        $hostsManager = $request->managers->hosts();
        $networksManager = $request->managers->networks();
        $hostVersionsManager = $request->managers->hostVersions();
        $platformsManager = $request->managers->platforms();

        $fields = $this->selectAliasedManagerFields($hostsManager, $networksManager, $hostVersionsManager, $platformsManager);

        $queryBuilder = $this->query($request->db)
            ->fields($fields)
            ->inner_join($hostsManager)
            ->inner_join($networksManager)
            ->left_join($hostVersionsManager)
            ->left_join($platformsManager);

        return $queryBuilder;
    }

    /**
     * @param Request $request
     * @param HostInstanceEntity|HostInstanceEntity[] $hostInstances
     */
    protected function postProcessHostInstances(Request $request, $hostInstances, $expand = true)
    {
        $gamesInstancesManager = $request->managers->gamesInstances();
        $hostsInstancesTypesManager = $request->managers->hostsInstancesTypes();

        if ($hostInstances) {
            if ($hostInstances instanceof HostInstanceEntity)
                $hostInstances = [$hostInstances];

            /** @var HostInstanceEntity[] $hostInstances */
            $hostInstances = array_index($hostInstances, $this->getPkField());

            $gameInstances = $gamesInstancesManager->getGameInstancesByHostInstanceIds($request, array_keys($hostInstances), $expand);

            foreach ($gameInstances as $gameInstance)
                $hostInstances[$gameInstance->getHostInstanceId()]->setGameInstance($gameInstance);

            foreach ($hostInstances as $hostInstance) {
                $hostInstanceType = $hostsInstancesTypesManager->getHostInstanceTypeById($request, $hostInstance->getHostInstanceTypeId());
                $hostInstance->setHostInstanceType($hostInstanceType);
            }

        }
    }

    /**
     * @param Request $request
     * @param $hostInstances
     * @param bool $expand
     */
    protected function adminPostProcessHostInstances(Request $request, $hostInstances, $expand = true)
    {
        $platformsManager = $request->managers->platforms();
        $hostVersionsManager = $request->managers->hostVersions();
        $locationsManager = $request->managers->locations();
        $usersManager = $request->managers->users();
        $imagesManager = $request->managers->images();
        $gamesInstancesManager = $request->managers->gamesInstances();
        $gamesInstancesLogsManager = $request->managers->gamesInstancesLogs();

        if ($hostInstances) {

            $this->postProcessHostInstances($request, $hostInstances, $expand);

            if ($hostInstances instanceof HostInstanceEntity)
                $hostInstances = [$hostInstances];

            /** @var HostInstanceEntity[] $hostInstances */
            $hostInstances = array_index($hostInstances, $this->getPkField());

            $hostVersionIds = unique_array_extract(DBField::HOST_VERSION_ID, $hostInstances);
            $locationIds = unique_array_extract(DBField::LOCATION_ID, $hostInstances);
            $userIds = unique_array_extract(DBField::USER_ID, $hostInstances);

            $platforms = $platformsManager->getAllPlatforms($request);
            $hostVersions = $hostVersionsManager->getHostVersionsByIds($request, $hostVersionIds);
            $locations = $locationsManager->getLocationByIds($request, $locationIds, true);
            $users = $usersManager->getUsersByIds($request, $userIds);

            Modules::load_helper(Helpers::PUBNUB);

            /** @var GameEntity[] $games */
            $games = [];
            /** @var GameInstanceEntity[] $gameInstances */
            $gameInstances = [];

            foreach ($hostInstances as $hostInstance) {
                foreach ($hostInstance->getGameInstances() as $gameInstance) {
                    $gameInstances[$gameInstance->getPk()] = $gameInstance;

                    $games[$gameInstance->getGameId()] = $gameInstance->getGame();

                    $pubNubHelper = new PubNubHelper($request->user, $hostInstance, $gameInstance);
                    $gameInstance->updateField(VField::PUB_NUB_CHANNELS, $pubNubHelper->getHostGameInstancePubNubChannels());
                }

                if ($hostInstance->getPlatformId())
                    $hostInstance->setPlatform($platforms[$hostInstance->getPlatformId()]);

                if ($hostInstance->getHostVersionId())
                    $hostInstance->setHostVersion($hostVersions[$hostInstance->getHostVersionId()]);

                if ($hostInstance->getLocationId())
                    $hostInstance->setLocation($locations[$hostInstance->getLocationId()]);

                if ($hostInstance->getUserId())
                    $hostInstance->setUser($users[$hostInstance->getUserId()]);
            }

            // Get/set game thumbnail images
            if ($games) {
                $avatarImages = $imagesManager->getActiveGameAvatarImagesByGameIds($request, array_keys($games));
                foreach ($avatarImages as $avatarImage) {
                    $games[$avatarImage->getContextEntityId()]->setAvatarImageUrls($avatarImage);
                }
            }

            if ($gameInstances) {
                $gameInstanceIds = array_keys($gameInstances);

                $gameInstanceSummaryStats = $gamesInstancesManager->getGameInstanceSummaryStatsByGameInstanceIds($request, $gameInstanceIds);
                foreach ($gameInstanceSummaryStats as $gameInstanceId => $gameInstanceSummaryStat) {
                    if (array_key_exists($gameInstanceId, $gameInstances))
                        $gameInstances[$gameInstanceId]->assign($gameInstanceSummaryStat);
                }

                $gameInstancesLogs = $gamesInstancesLogsManager->getGameInstanceLogsByGameInstanceIds($request, $gameInstanceIds);
                foreach ($gameInstancesLogs as $gameInstanceLog) {
                    $gameInstances[$gameInstanceLog->getGameInstanceId()]->setGameInstanceLog($gameInstanceLog);
                }
            }

        }
    }

    protected function teamAdminPostProcessHostInstances(Request $request, $hostInstances, $expand = true)
    {
        $platformsManager = $request->managers->platforms();
        $hostVersionsManager = $request->managers->hostVersions();
        $locationsManager = $request->managers->locations();
        $usersManager = $request->managers->users();
        $imagesManager = $request->managers->images();
        $gamesInstancesManager = $request->managers->gamesInstances();
        $gamesInstancesRoundsManager = $request->managers->gamesInstancesRounds();
        $gamesInstancesLogsManager = $request->managers->gamesInstancesLogs();

        if ($hostInstances) {

            $this->postProcessHostInstances($request, $hostInstances, $expand);

            if ($hostInstances instanceof HostInstanceEntity)
                $hostInstances = [$hostInstances];

            /** @var HostInstanceEntity[] $hostInstances */
            $hostInstances = array_index($hostInstances, $this->getPkField());

            $hostVersionIds = unique_array_extract(DBField::HOST_VERSION_ID, $hostInstances);
            $locationIds = unique_array_extract(DBField::LOCATION_ID, $hostInstances);
            $userIds = unique_array_extract(DBField::USER_ID, $hostInstances);

            $platforms = $platformsManager->getAllPlatforms($request);
            $hostVersions = $hostVersionsManager->getHostVersionsByIds($request, $hostVersionIds);
            $locations = $locationsManager->getLocationByIds($request, $locationIds, true);
            $users = $usersManager->getUsersByIds($request, $userIds);

            Modules::load_helper(Helpers::PUBNUB);

            /** @var GameEntity[] $games */
            $games = [];
            /** @var GameInstanceEntity[] $gameInstances */
            $gameInstances = [];

            foreach ($hostInstances as $hostInstance) {
                foreach ($hostInstance->getGameInstances() as $gameInstance) {
                    $gameInstances[$gameInstance->getPk()] = $gameInstance;

                    $games[$gameInstance->getGameId()] = $gameInstance->getGame();

                    $pubNubHelper = new PubNubHelper($request->user, $hostInstance, $gameInstance);
                    $gameInstance->updateField(VField::PUB_NUB_CHANNELS, $pubNubHelper->getHostGameInstancePubNubChannels());
                }

                if ($hostInstance->getPlatformId())
                    $hostInstance->setPlatform($platforms[$hostInstance->getPlatformId()]);

                if ($hostInstance->getHostVersionId())
                    $hostInstance->setHostVersion($hostVersions[$hostInstance->getHostVersionId()]);

                if ($hostInstance->getLocationId())
                    $hostInstance->setLocation($locations[$hostInstance->getLocationId()]);

                if ($hostInstance->getUserId())
                    $hostInstance->setUser($users[$hostInstance->getUserId()]);
            }

            // Get/set game thumbnail images
            if ($games) {
                $avatarImages = $imagesManager->getActiveGameAvatarImagesByGameIds($request, array_keys($games));
                foreach ($avatarImages as $avatarImage) {
                    $games[$avatarImage->getContextEntityId()]->setAvatarImageUrls($avatarImage);
                }
            }

            if ($gameInstances) {
                $gameInstanceIds = array_keys($gameInstances);

                $gameInstanceRounds = $gamesInstancesRoundsManager->getGameInstanceRoundsByGameInstanceId($request, $gameInstanceIds, false);
                foreach ($gameInstanceRounds as $gameInstanceRound) {
                    $gameInstances[$gameInstanceRound->getGameInstanceId()]->setGameInstanceRound($gameInstanceRound);
                }
            }

        }
    }

    /**
     * @param Request $request
     * @param $hostInstanceId
     * @return array|HostInstanceEntity
     */
    public function getHostInstanceById(Request $request, $hostInstanceId, $expand = false, $postProcess = true)
    {
        /** @var HostInstanceEntity $hostInstance */
        $hostInstance = $this->queryJoinHostsNetworks($request)
            ->filter($this->filters->byPk($hostInstanceId))
            ->get_entity($request);

        if ($postProcess)
            $this->postProcessHostInstances($request, $hostInstance, $expand);

        return $hostInstance;
    }

    /**
     * @param Request $request
     * @param $hostInstanceId
     * @param bool $expand
     * @param bool $postProcess
     * @return HostInstanceEntity
     */
    public function getSlimHostInstanceById(Request $request, $hostInstanceId, $expand = false, $postProcess = true)
    {
        /** @var HostInstanceEntity $hostInstance */
        $hostInstance = $this->queryJoinHosts($request)
            ->filter($this->filters->byPk($hostInstanceId))
            ->get_entity($request);

        if ($postProcess)
            $this->postProcessHostInstances($request, $hostInstance, $expand);

        return $hostInstance;
    }

    /**
     * @param Request $request
     * @param bool $expand
     * @return HostInstanceEntity[]
     */
    public function getActiveHostInstances(Request $request, $expand = false)
    {
        $dt = new DateTime();

        $dt->modify("-7 day");

        $hostInstances = $this->queryJoinHosts($request)
            ->filter($this->filters->Gte(DBField::START_TIME, $dt->format(SQL_DATETIME)))
            ->filter($this->filters->IsNull(DBField::END_TIME))
            ->filter($this->filters->isActive())
            ->sort_desc($this->getPkField())
            ->get_entities($request);

        if ($expand)
            $this->teamAdminPostProcessHostInstances($request, $hostInstances, $expand);

        return $hostInstances;
    }

    /**
     * @param Request $request
     * @param $userId
     * @param int $count
     * @return HostInstanceEntity[]
     */
    public function getHostInstancesByUserId(Request $request, $userId, $count = 50)
    {
        return $this->query($request->db)
            ->filter($this->filters->byUserId($userId))
            ->sort_desc($this->createPkField())
            ->limit($count)
            ->get_entities($request);
    }

    /**
     * @param Request $request
     * @param bool $expand
     * @return HostInstanceEntity
     */
    public function getActiveHostInstanceByHostId(Request $request, $hostId, $expand = false)
    {
        $dt = new DateTime();

        $dt->modify("-1 day");

        $hostInstance = $this->queryJoinHosts($request)
            ->filter($this->filters->Gte(DBField::START_TIME, $dt->format(SQL_DATETIME)))
            ->filter($this->filters->IsNull(DBField::END_TIME))
            ->filter($this->filters->isActive())
            ->filter($this->filters->byHostId($hostId))
            ->sort_desc($this->getPkField())
            ->get_entity($request);

        if ($expand)
            $this->postProcessHostInstances($request, $hostInstance, $expand);

        return $hostInstance;
    }

    /**
     * @param Request $request
     * @param $hostId
     * @param bool $expand
     * @return HostInstanceEntity[]
     */
    public function getActiveLocalHostInstancesByHostId(Request $request, $hostId, $expand = false)
    {
        $dt = new DateTime();

        $dt->modify("-14 day");

        $hostInstance = $this->queryJoinHosts($request)
            ->filter($this->filters->Gte(DBField::START_TIME, $dt->format(SQL_DATETIME)))
            ->filter($this->filters->IsNull(DBField::END_TIME))
            ->filter($this->filters->isActive())
            ->filter($this->filters->byHostId($hostId))
            ->filter($this->filters->byHostInstanceTypeId(HostsInstancesTypesManager::ID_HOST_APP))
            ->sort_desc($this->getPkField())
            ->get_entities($request);

        if ($expand)
            $this->teamAdminPostProcessHostInstances($request, $hostInstance, $expand);

        return $hostInstance;
    }


    /**
     * @param Request $request
     * @param $hostId
     * @param bool $expand
     * @return HostInstanceEntity[]
     */
    public function getActivePrioritizedHostInstancesByHostId(Request $request, $hostId, $expand = false)
    {
        $hostsInstanceTypesManager = $request->managers->hostsInstancesTypes();

        $dt = new DateTime();

        $dt->modify("-30 day");

        /** @var HostInstanceEntity[] $hostInstances */
        $hostInstances = $this->queryJoinHosts($request)
            ->inner_join($hostsInstanceTypesManager)
            ->filter($this->filters->Gte(DBField::START_TIME, $dt->format(SQL_DATETIME)))
            ->filter($this->filters->IsNull(DBField::END_TIME))
            ->filter($this->filters->isActive())
            ->filter($this->filters->byHostId($hostId))
            ->sort_asc($hostsInstanceTypesManager->field(DBField::PRIORITY))
            ->sort_desc($this->getPkField())
            ->get_entities($request);

        if ($expand)
            $this->postProcessHostInstances($request, $hostInstances, $expand);

        return $hostInstances;
    }

    /**
     * @param Request $request
     * @param $publicIpAddress
     * @param bool $expand
     * @return array|DBManagerEntity
     */
    public function getActiveHostInstanceByPublicIpAddress(Request $request, $publicIpAddress, $expand = false)
    {
        $hostInstance = $this->queryJoinHostsNetworks($request)
            ->filter($this->filters->byPublicIpAddress($publicIpAddress))
            ->filter($this->filters->IsNull(DBField::EXIT_STATUS))
            ->filter($this->filters->IsNull(DBField::END_TIME))
            ->sort_desc($this->getPkField())
            ->get_entity($request);

        if ($expand)
            $this->postProcessHostInstances($request, $hostInstance);

        return $hostInstance;
    }

    /**
     * @param Request $request
     * @param bool $expand
     * @return HostInstanceEntity[]
     */
    public function getRecentHostInstances(Request $request, $page = 1, $count = 50, $hostId = null, $hostInstanceTypeId = null, $expand = false)
    {
        $hostInstances = $this->queryJoinHosts($request)
            ->filter($this->filters->byHostId($hostId))
            ->filter($this->filters->byHostInstanceTypeId($hostInstanceTypeId))
            ->filter($this->filters->isActive())
            ->sort_desc($this->getPkField())
            ->paging($page, $count)
            ->get_entities($request);

        if ($expand)
            $this->adminPostProcessHostInstances($request, $hostInstances, $expand);

        return $hostInstances;
    }


    /**
     * @param Request $request
     * @return int
     */
    public function getHostInstanceCount(Request $request, $hostId = null)
    {
        try {
            $hostInstanceCount = $this->query($request->db)
                ->filter($this->filters->byHostId($hostId))
                ->filter($this->filters->isActive())
                ->count($this->getPkField());

        } catch (ObjectNotFound $e) {
            $hostInstanceCount = 0;
        }

        return $hostInstanceCount;
    }

    /**
     * @param Request $request
     * @return HostInstanceEntity[]
     */
    public function getExpiredHostInstances(Request $request)
    {
        $dt = new DateTime();

        $dt->modify("-2 minute");

        /** @var HostInstanceEntity[] $hostInstances */
        $hostInstances = $this->query($request->db)
            ->filter($this->filters->Lte(DBField::START_TIME, $request->getCurrentSqlTime()))
            ->filter($this->filters->IsNull(DBField::END_TIME))
            ->filter($this->filters->Lte(DBField::LAST_PING_TIME, $dt->format(SQL_DATETIME)))
            ->filter($this->filters->isActive())
            ->get_entities($request);

        $this->postProcessHostInstances($request, $hostInstances, false);

        return $hostInstances;
    }

    /**
     * @param Request $request
     * @param HostInstanceEntity $hostInstance
     * @param string $exitStatus
     */
    public function stopHostInstance(Request $request, HostInstanceEntity $hostInstance, $exitStatus = self::EXIT_STATUS_STOPPED)
    {
        $gamesInstancesManager = $request->managers->gamesInstances();

        $currentTimeStamp = $request->getCurrentSqlTime();

        if (!$hostInstance->has_ended()) {
            $hostInstance->assign([
                DBField::END_TIME => $currentTimeStamp,
                DBField::EXIT_STATUS => $exitStatus
            ]);

            $hostInstance->saveEntityToDb($request);

            $gameInstanceExitStatus = GamesInstancesManager::EXIT_STATUS_STOPPED;
            if ($exitStatus == self::EXIT_STATUS_TIMED_OUT)
                $gameInstanceExitStatus = GamesInstancesManager::EXIT_STATUS_TIMED_OUT;

            foreach ($hostInstance->getGameInstances() as $gameInstance) {
                $gamesInstancesManager->stopGameInstance($request, $gameInstance, $gameInstanceExitStatus);
            }
        }

        if ($hostInstance->getDnsId() && $hostInstance->dns_is_active())
            $this->deleteCloudFlareHostInstanceDNSRecord($request, $hostInstance);
    }

    /**
     * @param Request $request
     * @param HostInstanceEntity $hostInstance
     */
    public function createCloudFlareHostInstanceDNSRecord(Request $request, HostInstanceEntity $hostInstance)
    {

        $cfEmail = $request->config['cloudflare']['test']['auth-email'];
        $cfApiKey = $request->config['cloudflare']['test']['auth-key'];

        $key = new \Cloudflare\API\Auth\APIKey($cfEmail, $cfApiKey);
        $adapter = new \Cloudflare\API\Adapter\Guzzle($key);

        try {
            $dns = new \Cloudflare\API\Endpoints\DNS($adapter);

            $zoneId = self::CLOUD_FLARE_ZONE_ID;
            $dnsName = "{$hostInstance->getPublicHostName()}.qa1";

            $dnsRecord = $dns->addRecord(
                $zoneId,
                'A',
                $dnsName,
                $hostInstance->getLocalIpAddress(),
                120,
                false,
                "10"
            );

            if ($dnsRecord) {
                $records = $dns->listRecords($zoneId, 'A', "{$dnsName}.playesc.com");
                $dnsId = $records->result[0]->id;

                $hostInstanceData = [
                    DBField::DNS_ID => $dnsId,
                    DBField::DNS_IS_ACTIVE => 1
                ];

                $hostInstance->assign($hostInstanceData)->saveEntityToDb($request);
            }

        } catch (GuzzleHttp\Exception\ClientException $e) {
        }
    }

    /**
     * @param Request $request
     * @param HostInstanceEntity $hostInstance
     */
    public function deleteCloudFlareHostInstanceDNSRecord(Request $request, HostInstanceEntity $hostInstance)
    {

        $cfEmail = $request->config['cloudflare']['test']['auth-email'];
        $cfApiKey = $request->config['cloudflare']['test']['auth-key'];

        $key = new \Cloudflare\API\Auth\APIKey($cfEmail, $cfApiKey);
        $adapter = new \Cloudflare\API\Adapter\Guzzle($key);

        try {
            $dns = new \Cloudflare\API\Endpoints\DNS($adapter);

            $zoneId = self::CLOUD_FLARE_ZONE_ID;

            $dnsRecord = $dns->deleteRecord($zoneId, $hostInstance->getDnsId());

            if ($dnsRecord) {
                $hostInstance->updateField(DBField::DNS_IS_ACTIVE, 0)->saveEntityToDb($request);
            }

        } catch (GuzzleHttp\Exception\ClientException $e) {
        }
    }

    /**
     * @param Request $request
     * @param $dateRangeTypeId
     * @return array
     */
    public function summarizeUniqueHostLocations(Request $request, $dateRangeTypeId)
    {
        $dateRangesManager = $request->managers->dateRanges();
        $locationsManager = $request->managers->locations();

        $fields = [
            $dateRangesManager->createPkField(),
            new CountDBField($dateRangesManager->getSumField(), $locationsManager->field(DBField::LOCATION_HASH), $locationsManager->getTable(), true),
        ];

        /** @var array $hostLocationSummary */
        $hostLocationSummary = $this->query($request->db)
            ->set_connection($request->db->get_connection(SQLN_BI))
            ->fields($fields)
            ->inner_join($locationsManager)
            ->left_join($dateRangesManager, $dateRangesManager->joinDateRangesFilter($this->field(DBField::START_TIME), $this->field(DBField::END_TIME)))
            ->filter($dateRangesManager->filters->Lte(DBField::START_TIME, $request->getCurrentSqlTime()))
            ->filter($dateRangesManager->filters->Gte(DBField::START_TIME, '2018-07-01'))
            ->filter($dateRangesManager->filters->byDateRangeTypeId($dateRangeTypeId))
            ->group_by(1)
            ->get_list();

        return $hostLocationSummary;
    }
}

class HostsInstancesTypesManager extends BaseEntityManager
{
    protected $entityClass = HostInstanceTypeEntity::class;
    protected $table = Table::HostInstanceType;
    protected $table_alias = TableAlias::HostInstanceType;
    protected $pk = DBField::HOST_INSTANCE_TYPE_ID;

    const ID_HOST_APP = 1;
    const ID_ESC_WAN_CLOUD = 2;
    const ID_OFFLINE_CLOUD = 3;

    const GNS_KEY_PREFIX = GNS_ROOT.'.host-instances.types';

    public static $fields = [
        DBField::HOST_INSTANCE_TYPE_ID,
        DBField::DISPLAY_NAME,
        DBField::PRIORITY,
        DBField::IS_ACTIVE,
        DBField::CREATED_BY,
        DBField::MODIFIED_BY,
        DBField::DELETED_BY,
    ];

    /**
     * @var HostInstanceTypeEntity[]
     */
    protected $hostInstanceTypes = [];

    protected $foreign_managers = [

    ];

    /**
     * @param HostInstanceTypeEntity $data
     * @param Request $request
     * @return DBManagerEntity|void
     */
    public function processVFields(DBManagerEntity $data, Request $request)
    {

    }

    /**
     * @return string
     */
    public function generateCacheKey()
    {
        return self::GNS_KEY_PREFIX.'.all';
    }

    /**
     * @param Request $request
     * @return HostInstanceTypeEntity[]
     */
    public function getAllHostInstanceTypes(Request $request)
    {
        if (!$this->hostInstanceTypes) {
            $this->hostInstanceTypes = $this->query($request->db)
                ->cache($this->generateCacheKey(), ONE_WEEK)
                ->get_entities($request);

            $this->index($this->hostInstanceTypes);
        }

        return $this->hostInstanceTypes;
    }

    /**
     * @param Request $request
     * @return HostInstanceTypeEntity[]
     */
    public function getAllActiveHostInstanceTypes(Request $request)
    {
        $hostInstanceTypes = [];

        foreach ($this->getAllHostInstanceTypes($request) as $hostInstanceType) {
            if ($hostInstanceType->is_active())
                $hostInstanceTypes[$hostInstanceType->getPk()] = $hostInstanceType;
        }

        return $hostInstanceTypes;
    }

    /**
     * @param Request $request
     * @param $hostInstanceTypeId
     * @return HostInstanceTypeEntity
     */
    public function getHostInstanceTypeById(Request $request, $hostInstanceTypeId)
    {
        return $this->getAllHostInstanceTypes($request)[$hostInstanceTypeId];
    }

}

class HostsInstancesDevicesManager extends BaseEntityManager
{
    protected $entityClass = HostInstanceDeviceEntity::class;
    protected $table = Table::HostInstanceDevice;
    protected $table_alias = TableAlias::HostInstanceDevice;
    protected $pk = DBField::HOST_INSTANCE_DEVICE_ID;

    public static $fields = [
        DBField::HOST_INSTANCE_DEVICE_ID,
        DBField::HOST_INSTANCE_ID,
        DBField::DEVICE_HASH,
        DBField::USER_ID,
        DBField::GUEST_ID,
        DBField::GUEST_HASH,
        DBField::SESSION_ID,
        DBField::START_TIME,
        DBField::END_TIME,
        DBField::LAST_PING_TIME,
        DBField::EXIT_STATUS,
        DBField::PLAYER_REQUEST_ID,
        DBField::CREATE_TIME,
        DBField::IS_ACTIVE,
        DBField::CREATED_BY,
        DBField::MODIFIED_BY,
        DBField::DELETED_BY,
    ];

    protected $foreign_managers = [

    ];

    public function processVFields(DBManagerEntity $data, Request $request)
    {

    }
}

class HostsInstancesInvitesManager extends BaseEntityManager
{
    protected $entityClass = HostInstanceInviteEntity::class;
    protected $table = Table::HostInstanceInvite;
    protected $table_alias = TableAlias::HostInstanceInvite;
    protected $pk = DBField::HOST_INSTANCE_INVITE_ID;

    public static $fields = [
        DBField::HOST_INSTANCE_INVITE_ID,
        DBField::HOST_INSTANCE_INVITE_TYPE_ID,
        DBField::HOST_INSTANCE_ID,
        DBField::GAME_INSTANCE_ID,
        DBField::INVITE_HASH,
        DBField::HOST_INSTANCE_DEVICE_ID,
        DBField::USER_ID,
        DBField::SMS_ID,
        DBField::EMAIL_TRACKING_ID,
        DBField::SHORT_URL_ID,
        DBField::INVITE_RECIPIENT,
        DBField::CREATE_TIME,
        DBField::IS_ACTIVE,
        DBField::CREATED_BY,
        DBField::MODIFIED_BY,
        DBField::DELETED_BY,
    ];

    protected $foreign_managers = [

    ];

    public function processVFields(DBManagerEntity $data, Request $request)
    {

    }

    /**
     * @param Request $request
     * @param $hostInstanceInviteTypeId
     * @param $hostInstanceId
     * @param $inviteRecipient
     * @param null $gameInstanceId
     * @param null $shortUrlId
     * @param null $userId
     * @param null $smsId
     * @param null $emailTrackingId
     * @return HostInstanceInviteEntity
     */
    public function createNewHostInstanceInvite(Request $request, $hostInstanceInviteTypeId, $hostInstanceId, $inviteRecipient,
                                                $inviteHash, $gameInstanceId = null, $shortUrlId = null, $userId = null,
                                                $smsId = null, $emailTrackingId = null)
    {
        $data = [
            DBField::HOST_INSTANCE_INVITE_TYPE_ID => $hostInstanceInviteTypeId,
            DBField::HOST_INSTANCE_ID => $hostInstanceId,
            DBField::GAME_INSTANCE_ID => $gameInstanceId,
            DBField::INVITE_HASH => $inviteHash,
            DBField::HOST_INSTANCE_DEVICE_ID => null,
            DBField::USER_ID => $userId,
            DBField::SMS_ID => $smsId,
            DBField::EMAIL_TRACKING_ID => $emailTrackingId,
            DBField::SHORT_URL_ID => $shortUrlId,
            DBField::INVITE_RECIPIENT => $inviteRecipient,
            DBField::CREATE_TIME => $request->getCurrentSqlTime(),
            DBField::IS_ACTIVE => 1,
            DBField::CREATED_BY => $request->requestId
        ];

        /** @var HostInstanceInviteEntity $hostInstanceInvite */
        $hostInstanceInvite = $this->query($request->db)->createNewEntity($request, $data);

        return $hostInstanceInvite;
    }

    /**
     * @param Request $request
     * @param $hostInstanceId
     * @param $inviteHash
     * @return array|HostInstanceInviteEntity
     */
    public function getHostInstanceInviteByHostInstanceAndHash(Request $request, $hostInstanceId, $inviteHash)
    {
        return $this->query($request->db)
            ->filter($this->filters->byHostInstanceId($hostInstanceId))
            ->filter($this->filters->byInviteHash($inviteHash))
            ->filter($this->filters->isActive())
            ->get_entity($request);
    }
}

class HostsInstancesInvitesTypesManager extends BaseEntityManager
{
    protected $entityClass = HostInstanceInviteTypeEntity::class;
    protected $table = Table::HostInstanceInviteType;
    protected $table_alias = TableAlias::HostInstanceInviteType;
    protected $pk = DBField::HOST_INSTANCE_INVITE_TYPE_ID;

    const ID_PHONE = 1;
    const ID_EMAIL = 2;

    public static $fields = [
        DBField::HOST_INSTANCE_INVITE_TYPE_ID,
        DBField::DISPLAY_NAME,
        DBField::IS_ACTIVE,
        DBField::CREATED_BY,
        DBField::MODIFIED_BY,
        DBField::DELETED_BY,
    ];

    protected $foreign_managers = [

    ];
}

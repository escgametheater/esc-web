<?php
/**
 * Created by PhpStorm.
 * User: christophercarter
 * Date: 6/2/18
 * Time: 4:52 PM
 */

Entities::uses('hosts-locations');


class LocationsManager extends BaseEntityManager {

    protected $entityClass = LocationEntity::class;
    protected $table = Table::Location;
    protected $table_alias = TableAlias::Location;
    protected $pk = DBField::LOCATION_ID;

    // 8 Decimals LatLon precision is to "within 0.1mm" accuracy.
    const LAT_LONG_DECIMAL_PRECISION = 8;

    protected $foreign_managers = [
        AddressesManager::class => DBField::ADDRESS_ID
    ];

    public static $fields = [
        DBField::LOCATION_ID,
        DBField::HOST_ID,
        DBField::LOCATION_HASH,
        DBField::LATITUDE,
        DBField::LONGITUDE,

        DBField::DISPLAY_NAME,

        DBField::IS_ACTIVE,
        DBField::CREATED_BY,
        DBField::MODIFIED_BY,
        DBField::DELETED_BY
    ];

    /**
     * @param LocationEntity $data
     * @param Request $request
     */
    public function processVFields(DBManagerEntity $data, Request $request)
    {
        if (!$data->hasField(VField::ADDRESS))
            $data->updateField(VField::ADDRESS, []);
    }

    /**
     * @param $latitude
     * @param $longitude
     * @return string
     */
    public function hashLatLong($latitude, $longitude)
    {
        return sha1("{$latitude}<->{$longitude}");
    }

    /**
     * @param $geoCoordinate
     * @return float
     */
    public function roundGeoCoordinate($geoCoordinate)
    {
        return round((float) $geoCoordinate, self::LAT_LONG_DECIMAL_PRECISION);
    }

    /**
     * @param Request $request
     * @param $addressId
     * @param $displayName
     * @param null $url
     * @return LocationEntity
     */
    public function createNewLocation(Request $request, $hostId, $latitude = null, $longitude = null, $displayName = null)
    {
        if ($latitude && $longitude) {

            $latitude = $this->roundGeoCoordinate($latitude);
            $longitude = $this->roundGeoCoordinate($longitude);

            $locationHash = $this->hashLatLong($latitude, $longitude);

        } else {
            $locationHash = null;
        }

        $data = [
            DBField::HOST_ID => $hostId,
            DBField::LOCATION_HASH => $locationHash,
            DBField::LATITUDE => $latitude,
            DBField::LONGITUDE => $longitude,
            DBField::DISPLAY_NAME => $displayName,
        ];

        /** @var LocationEntity $location */
        $location = $this->query()->createNewEntity($request, $data);

        $this->postProcessLocations($request, $location);

        return $location;
    }

    /**
     * @param $addresses AddressEntity[]
     * @param bool $requirePk
     * @return FormField[]
     */
    public function getFormFields($addresses, $requirePk = true)
    {
        $fields = [
            new IntegerField(DBField::ID, 'Location ID', $requirePk),
            new SelectField(DBField::ADDRESS_ID, 'Address', $addresses, true),
            new CharField(DBField::DISPLAY_NAME, 'Name', 100, true)
        ];

        return $fields;
    }

    /**
     * @param Request $request
     * @param $locations
     */
    public function postProcessLocations(Request $request, $locations)
    {
        $addressesManager = $request->managers->addresses();

        /** @var LocationEntity[] $locations */
        if ($locations = $this->preProcessResultAsResultArray($locations)) {

            $locationIds = extract_pks($locations);

            $addresses = $addressesManager->getAddressesByLocationIds($request, $locationIds);

            foreach ($addresses as $locationId => $addressLocation) {
                /** @var AddressEntity $address */
                foreach ($addressLocation as $address) {
                    $locations[$locationId]->setAddress($address);
                }
            }

        }
    }

    /**
     * @param Request $request
     * @param bool $includeFields
     * @return SQLQuery
     */
    protected function queryJoinAddressesTypes(Request $request, $includeFields = true)
    {
        $addressesManager = $request->managers->addresses();
        $addressTypesManager = $request->managers->addressesTypes();

        $queryBuilder = $this->query($request->db)
            ->inner_join($addressesManager);
            //->inner_join($addressTypesManager);

        if ($includeFields)
            $queryBuilder->fields($this->selectAliasedManagerFields($addressesManager));

        return $queryBuilder;
    }

    /**
     * @param Request $request
     * @param $locationId
     * @return LocationEntity
     */
    public function getLocationById(Request $request, $locationId, $expand = false)
    {

        /** @var LocationEntity $location */
        $location = $this->queryJoinAddressesTypes($request)
            ->filter($this->filters->byPk($locationId))
            ->filter($this->filters->isActive())
            ->get_entity($request);

        if ($expand)
            $this->postProcessLocations($request, $location);

        return $location;
    }

    /**
     * @param Request $request
     * @param $locationIds
     * @param bool $expand
     * @return LocationEntity[]
     */
    public function getLocationByIds(Request $request, $locationIds, $expand = false)
    {
        $locations = $this->query($request->db)
            ->filter($this->filters->byPk($locationIds))
            ->get_entities($request);

        if ($locations)
            $locations = array_index($locations, $this->getPkField());

        if ($expand)
            $this->postProcessLocations($request, $locations);

        return $locations;
    }

    /**
     * @param Request $request
     * @param $hostId
     * @param $latitude
     * @param $longitude
     * @return LocationEntity
     */
    public function getLocationByLatitudeLongitude(Request $request, $hostId, $latitude, $longitude)
    {
        /** @var LocationEntity $location */
        $location = $this->query($request->db)
            ->filter($this->filters->byHostId($hostId))
            ->filter($this->filters->byLatitude($latitude))
            ->filter($this->filters->byLongitude($longitude))
            ->get_entity($request);

        return $location;
    }

    /**
     * @param Request $request
     * @param $hostIds
     * @param bool $expand
     * @return LocationEntity[]
     */
    public function getLocationsByHostIds(Request $request, $hostIds, $expand = false)
    {
        /** @var LocationEntity[] $locations */
        $locations = $this->query($request->db)
            ->filter($this->filters->byHostId($hostIds))
            ->filter($this->filters->isActive())
            ->get_entities($request);

        if ($expand)
            $this->postProcessLocations($request, $locations);

        return $locations;
    }

    /**
     * @param Request $request
     * @return LocationEntity[]
     */
    public function getAllLocations(Request $request)
    {
        /** @var LocationEntity[] $locations */
        $locations = $this->query()->get_entities($request);

        $this->postProcessLocations($request, $locations);

        return $locations;
    }
}

class HostsManager extends BaseEntityManager {

    const HOST_SLUG_PLAY_NYC = 'nyc';

    protected $entityClass = HostEntity::class;
    protected $table = Table::Host;
    protected $table_alias = TableAlias::Host;
    protected $pk = DBField::HOST_ID;

    const GNS_KEY_PREFIX = GNS_ROOT.'.hosts';

    protected $foreign_managers = [
        LocationsManager::class => DBField::LOCATION_ID
    ];

    public static $fields = [
        DBField::HOST_ID,
        DBField::OWNER_TYPE_ID,
        DBField::OWNER_ID,

        DBField::DISPLAY_NAME,
        DBField::SLUG,
        DBField::ALTERNATE_URL,
        DBField::IS_PROD,
        DBField::HAS_CUSTOM_SLUG,
        DBField::OFFLINE_GAME_ID,
        DBField::OFFLINE_GAME_MOD_ID,

        DBField::IS_ACTIVE,
        DBField::CREATED_BY,
        DBField::MODIFIED_BY,
        DBField::DELETED_BY
    ];

    /**
     * @param HostEntity $data
     * @param Request $request
     */
    public function processVFields(DBManagerEntity $data, Request $request)
    {
        $data->updateField(VField::SCREENS, []);
        $data->updateField(VField::NETWORKS, []);
        $data->updateField(VField::GAME, []);

        if ($data->is_prod())
            $url = $request->getPlayUrl("/{$data->getSlug()}");
        else
            $url = $request->getPlayUrl("/{$data->getSlug()}"); // $request->getPlayUrl("/sandbox/{$data->getSlug()}");

        $data->updateField(VField::URL, $url);

        if ($data->getAlternateUrl())
            $effectiveUrl = $data->getAlternateUrl();
        else {
            if ($request->settings()->is_prod()) {
                $effectiveUrl = "https://esc.io/{$data->getSlug()}";
            } else {
                $effectiveUrl = $url;
            }
        }

        $data->updateField(VField::EFFECTIVE_URL, $effectiveUrl);

        $data->updateField(DBField::PUB_SUB_CHANNEL, "hostSlug-{$data->getSlug()}");
    }

    /**
     * @param $hostSlug
     * @return string
     */
    public function generateHostSlugCacheKey($hostSlug)
    {
        return self::GNS_KEY_PREFIX.".slug.{$hostSlug}";
    }

    /**
     * @param $hostId
     * @return string
     */
    public function generateEntityIdCacheKey($hostId)
    {
        return self::GNS_KEY_PREFIX.".{$hostId}";
    }

    /**
     * @param $slug
     * @return bool
     */
    public function checkSlugExists($slug)
    {
        return $this->query()
            ->filter($this->filters->bySlug($slug))
            ->exists();
    }

    /**
     * @param $slug
     * @return string
     */
    public function generateNewValidCustomHostSlug($slug)
    {
        $originalSlug = $slug;

        $slugExists = $this->checkSlugExists($slug);

        $i = 0;

        while ($slugExists) {
            $i++;
            $slug = "{$originalSlug}-{$i}";

            $slugExists = $this->checkSlugExists($slug);
        }

        return $slug;
    }

    /**
     * @param Request $request
     * @param $locationId
     * @param $displayName
     * @param null $slug
     * @return HostEntity
     */
    public function createNewHost(Request $request, $ownerTypeId, $ownerId, $displayName = null, $slug = null, $isProd = 0)
    {
        if (!$slug) {
            $hasCustomSlug = 0;
            $slug = $this->generateNewValidCustomHostSlug(uuidV4HostName());
        } else {
            $hasCustomSlug = 1;
            $slug = $this->generateNewValidCustomHostSlug($slug);
        }

        $data = [
            DBField::OWNER_TYPE_ID => $ownerTypeId,
            DBField::OWNER_ID => $ownerId,
            DBField::DISPLAY_NAME => $displayName,
            DBField::IS_PROD => $isProd,
            DBField::SLUG => $slug,
            DBField::HAS_CUSTOM_SLUG => $hasCustomSlug,
            DBField::OFFLINE_GAME_ID => null,
            DBField::IS_ACTIVE => 1,
        ];

        /** @var HostEntity $host */
        $host = $this->query()->createNewEntity($request, $data);

        $this->postProcessHosts($request, $host);

        return $host;
    }

    /**
     * @param LocationEntity[] $locations
     * @param bool $requirePk
     * @return FormField[]
     */
    public function getFormFields($locations, $requirePk = true)
    {
        $fields = [
            new IntegerField(DBField::ID, 'Host ID', $requirePk),
            new SelectField(DBField::LOCATION_ID, 'Location', $locations, true),
            new CharField(DBField::DISPLAY_NAME, 'Name', 100, true)
        ];

        return $fields;
    }

    /**
     * @param Request $request
     * @return HostEntity[]
     */
    public function getHostsWithHostInstances(Request $request)
    {
        $hostInstancesManager = $request->managers->hostsInstances();

        $joinHostInstancesFilter = $this->filters->byPk($hostInstancesManager->field(DBField::HOST_ID));

        $hosts = $this->query($request->db)
            ->inner_join($hostInstancesManager, $joinHostInstancesFilter)
            ->group_by($this->createPkField())
            ->sort_asc(DBField::SLUG)
            ->get_entities($request);

        return $hosts;
    }

    /**
     * @param Request $request
     * @param $hosts
     */
    protected function postProcessHosts(Request $request, $hosts)
    {
        $screensManager = $request->managers->screens();
        $networksManager = $request->managers->networks();

        /** @var HostEntity[] $hosts */
        if ($hosts = $this->preProcessResultAsResultArray($hosts)) {

            $hostIds = extract_pks($hosts);

            $screens = $screensManager->getScreensByHostId($request, $hostIds);
            foreach ($screens as $screen)
                $hosts[$screen->getHostId()]->setScreen($screen);

            $networks = $networksManager->getNetworksByHostId($request, $hostIds);
            foreach ($networks as $network)
                $hosts[$network->getHostId()]->setNetwork($network);
        }

    }

    /**
     * @param Request $request
     * @return HostEntity[]
     */
    public function getHostsWithCustomSlug(Request $request)
    {
        return $this->query($request->db)
            ->filter($this->filters->hasCustomSlug())
            ->sort_asc(DBField::SLUG)
            ->get_entities($request);
    }

    /**
     * @param Request $request
     * @param $excludeHostIds
     * @return HostEntity[]
     */
    public function getHostsWithOfflineTriviaGamesActive(Request $request, $excludeHostIds)
    {
        $gamesManager = $request->managers->games();
        $joinGamesFilter = $this->filters->And_(
            $gamesManager->filters->byPk($this->field(DBField::OFFLINE_GAME_ID)),
            $gamesManager->filters->byGameCategoryId(GamesCategoriesManager::ID_TRIVIA),
            $gamesManager->filters->isActive()
        );

        /** @var HostEntity[] $host */
        $hosts = $this->query($request->db)
            ->inner_join($gamesManager, $joinGamesFilter)
            ->filter($this->filters->IsNotNull(DBField::OFFLINE_GAME_ID))
            ->filter($this->filters->NotEq($excludeHostIds))
            ->filter($this->filters->isActive())
            ->get_entities($request);

        return $hosts;
    }

    /**
     * @param Request $request
     * @param $excludeHostIds
     * @return HostEntity[]
     */
    public function getHostsWithOfflineGamesActive(Request $request, $excludeHostIds)
    {
        $gamesManager = $request->managers->games();
        $joinGamesFilter = $this->filters->And_(
            $gamesManager->filters->byPk($this->field(DBField::OFFLINE_GAME_ID)),
            $gamesManager->filters->isActive()
        );

        /** @var HostEntity[] $host */
        $hosts = $this->query($request->db)
            ->inner_join($gamesManager, $joinGamesFilter)
            ->filter($this->filters->IsNotNull(DBField::OFFLINE_GAME_ID))
            ->filter($this->filters->NotEq($excludeHostIds))
            ->filter($this->filters->isActive())
            ->get_entities($request);

        return $hosts;
    }

    /**
     * @param Request $request
     * @param $hostId
     * @return HostEntity
     */
    public function getHostById(Request $request, $hostId, $expand = true)
    {
        $locationsManager = $request->managers->locations();

        /** @var HostEntity $host */
        $host = $this->query($request->db)
            ->fields($this->selectAliasedManagerFields($locationsManager))
            ->filter($this->filters->byPk($hostId))
            ->filter($this->filters->isActive())
            ->inner_join($locationsManager)
            ->get_entity($request);

        if ($expand)
            $this->postProcessHosts($request, $host);

        return $host;
    }

    /**
     * @param Request $request
     * @param $hostId
     * @return HostEntity
     */
    public function getSlimHostById(Request $request, $hostId)
    {
        /** @var HostEntity $host */
        $host = $this->query($request->db)
            ->filter($this->filters->byPk($hostId))
            ->filter($this->filters->isActive())
            ->get_entity($request);

        return $host;
    }

    /**
     * @param Request $request
     * @param $slug
     * @return array|HostEntity
     */
    public function getHostBySlug(Request $request, $slug)
    {
        return $this->query($request->db)
            ->filter($this->filters->bySlug($slug))
            ->cache($this->generateHostSlugCacheKey($slug), ONE_HOUR)
            ->get_entity($request);
    }

    /**
     * @param Request $request
     * @param $locationId
     * @return HostEntity[]
     * @deprecated
     */
    public function getHostsByLocationId(Request $request, $locationId, $expand = true)
    {
        /** @var HostEntity[] $hosts */
        $hosts = $this->query($request->db)
            ->filter($this->filters->byLocationId($locationId))
            ->filter($this->filters->isActive())
            ->get_entities($request);

        if ($expand)
            $this->postProcessHosts($request, $hosts);

        return $hosts;
    }

    /**
     * @param Request $request
     * @param $userId
     * @return HostEntity[]
     */
    public function getHostsByUserId(Request $request, $userId, $page = 1, $perpage = 10, $expand = true)
    {
        $hosts = $this->query($request->db)
            ->filter($this->filters->byOwnerTypeId(EntityType::USER))
            ->filter($this->filters->byOwnerId($userId))
            ->filter($this->filters->isActive())
            ->paging($page, $perpage)
            ->get_entities($request);

        if ($expand)
            $this->postProcessHosts($request, $hosts);

        return $hosts;
    }

    /**
     * @param Request $request
     * @param $userId
     * @return HostEntity[]
     */
    public function getHostsByUserIds(Request $request, $userId)
    {
        $hosts = $this->query($request->db)
            ->filter($this->filters->byOwnerTypeId(EntityType::USER))
            ->filter($this->filters->byOwnerId($userId))
            ->filter($this->filters->isActive())
            ->get_entities($request);

        return $hosts;
    }

    /**
     * @param Request $request
     * @param $userId
     * @param bool $expand
     * @return HostEntity
     */
    public function getFirstUserHost(Request $request, $userId, $expand = true)
    {
        /** @var HostEntity $host */
        $host = $this->query($request->db)
            ->filter($this->filters->byOwnerTypeId(EntityType::USER))
            ->filter($this->filters->byOwnerId($userId))
            ->filter($this->filters->isActive())
            ->limit(1)
            ->get_entity($request);

        if ($expand)
            $this->postProcessHosts($request, $host);

        return $host;
    }

    /**
     * @param Request $request
     * @param $organizationId
     * @param int $page
     * @param int $perpage
     * @param bool $expand
     * @return HostEntity[]
     */
    public function getHostsByOrganizationId(Request $request, $organizationId, $page = 1, $perpage = 10, $expand = true)
    {
        $hosts = $this->query($request->db)
            ->filter($this->filters->byOwnerTypeId(EntityType::ORGANIZATION))
            ->filter($this->filters->byOwnerId($organizationId))
            ->filter($this->filters->isActive())
            ->paging($page, $perpage)
            ->get_entities($request);

        if ($expand)
            $this->postProcessHosts($request, $hosts);

        return $hosts;
    }

    /**
     * @param Request $request
     * @param $organizationId
     * @param bool $expand
     * @return HostEntity[]
     */
    public function getSelectableHostsByOrganizationId(Request $request, $organizationId, $expand = false)
    {
        $hosts = $this->query($request->db)
            ->filter($this->filters->byOwnerTypeId(EntityType::ORGANIZATION))
            ->filter($this->filters->byOwnerId($organizationId))
            ->filter($this->filters->isActive())
            ->sort_desc(DBField::IS_PROD)
            ->get_entities($request);

        if ($expand)
            $this->postProcessHosts($request, $hosts);

        return $hosts;
    }

    /**
     * @param Request $request
     * @param $userId
     * @param bool $expand
     * @return HostEntity[]
     */
    public function getAllAvailableHostsToUserId(Request $request, $userId, $expand = true)
    {
        $organizationsUsersManager = $request->managers->organizationsUsers();
        $joinOrganizationsUsersFilter = $this->filters->And_(
            $this->filters->byOwnerTypeId(EntityType::ORGANIZATION),
            $organizationsUsersManager->filters->isActive(),
            $organizationsUsersManager->filters->byUserId($userId)
        );

        $organizationsManager = $request->managers->organizations();
        $joinOrganizationsFilter = $this->filters->And_(
            $organizationsManager->filters->byPk($organizationsUsersManager->field(DBField::ORGANIZATION_ID)),
            $organizationsManager->filters->isActive()
        );

        $organizationsBaseRightsManager = $request->managers->organizationsBaseRights();
        $joinOrganizationsBaseRightsFilter = $this->filters->And_(
            $organizationsBaseRightsManager->filters->byName(OrganizationsBaseRightsManager::RIGHT_ORG_HOSTS_INSTANCES_PROD)
        );

        $organizationsRightsManager = $request->managers->organizationsRights();
        $joinOrganizationsRightsFilter = $this->filters->And_(
            $organizationsRightsManager->filters->byOrganizationId($organizationsManager->createPkField()),
            $organizationsRightsManager->filters->byOrganizationBaseRightId($organizationsBaseRightsManager->createPkField())
        );

        $organizationsPermissionsManager = $request->managers->organizationsPermissions();
        $joinOrganizationsPermissionsFilter = $this->filters->And_(
            $organizationsPermissionsManager->filters->byOrganizationId($organizationsUsersManager->field(DBField::ORGANIZATION_ID)),
            $organizationsPermissionsManager->filters->byOrganizationRightId($organizationsRightsManager->createPkField()),
            $organizationsPermissionsManager->filters->byOrganizationRoleId($organizationsUsersManager->field(DBField::ORGANIZATION_ROLE_ID)),
            $organizationsPermissionsManager->filters->BitAnd(DBField::ACCESS_LEVEL, Rights::getAccessLevel(Rights::USE)),
            $organizationsPermissionsManager->filters->isActive()
        );

        $whereFilter = $this->filters->Or_(
            $this->filters->And_(
                $this->filters->byOwnerTypeId(EntityType::USER),
                $this->filters->byOwnerId($userId)
            ),
            $this->filters->And_(
                $this->filters->byOwnerTypeId(EntityType::ORGANIZATION),
                $this->filters->byOwnerId($organizationsUsersManager->field(DBField::ORGANIZATION_ID)),
                $organizationsPermissionsManager->filters->IsNotNull($organizationsPermissionsManager->createPkField())
            )
        );

        $hosts = $this->query($request->db)
            ->left_join($organizationsUsersManager, $joinOrganizationsUsersFilter)
            ->left_join($organizationsManager, $joinOrganizationsFilter)
            ->left_join($organizationsBaseRightsManager, $joinOrganizationsBaseRightsFilter)
            ->left_join($organizationsRightsManager, $joinOrganizationsRightsFilter)
            ->left_join($organizationsPermissionsManager, $joinOrganizationsPermissionsFilter)
            ->sort_asc($this->field(DBField::OWNER_TYPE_ID))
            ->sort_asc($organizationsManager->field(DBField::DISPLAY_NAME))
            ->filter($this->filters->isActive())
            ->filter($whereFilter)
            ->get_entities($request);

        if ($expand)
            $this->postProcessHosts($request, $hosts);

        return $hosts;
    }

    /**
     * @param Request $request
     * @param $userId
     * @return int
     */
    public function getHostCountByUserId(Request $request, $userId)
    {
        return $this->query($request->db)
            ->filter($this->filters->byOwnerTypeId(EntityType::USER))
            ->filter($this->filters->byOwnerId($userId))
            ->filter($this->filters->isActive())
            ->count($this->getPkField());
    }

    /**
     * @param Request $request
     * @param $hostIds
     * @param bool $expand
     * @return HostEntity[]
     */
    public function getHostsByIds(Request $request, $hostIds, $expand = false)
    {
        $hosts = $this->getEntitiesByPks($request, $hostIds);

        if ($expand)
            $this->postProcessHosts($request, $hosts);

        return $hosts;
    }
}

class HostsDevicesManager extends BaseEntityManager
{
    protected $entityClass = HostDeviceEntity::class;
    protected $table = Table::HostDevice;
    protected $table_alias = TableAlias::HostDevice;
    protected $pk = DBField::HOST_DEVICE_ID;

    protected $foreign_managers = [
    ];

    public static $fields = [
        DBField::HOST_DEVICE_ID,
        DBField::UUID,
        DBField::PLATFORM_ID,
        DBField::DISPLAY_NAME,
        DBField::CREATE_TIME,
        DBField::IS_ACTIVE,
        DBField::CREATED_BY,
        DBField::MODIFIED_BY,
        DBField::DELETED_BY
    ];

    /**
     * @param HostDeviceEntity $data
     * @param Request $request
     */
    public function processVFields(DBManagerEntity $data, Request $request)
    {
        $url = $request->getWwwUrl("/admin/host-devices/view/{$data->getPk()}");
        $data->updateField(VField::URL, $url);
    }

    /**
     * @param Request $request
     * @param HostDeviceEntity[]|HostDeviceEntity $hostDevices
     */
    public function postProcessHostDevices(Request $request, $hostDevices)
    {
        $hostDeviceComponentsManager = $request->managers->hostsDevicesComponents();

        if ($hostDevices) {
            if ($hostDevices instanceof HostDeviceEntity)
                $hostDevices = [$hostDevices];

            /** @var HostDeviceEntity[] $hostDeviceIds */
            $hostDevices = $this->index($hostDevices);
            $hostDeviceIds = array_keys($hostDevices);

            $hostDeviceComponents = $hostDeviceComponentsManager->getHostDeviceComponentsByHostDeviceIds($request, $hostDeviceIds);

            foreach ($hostDeviceComponents as $hostDeviceComponent) {
                $hostDevices[$hostDeviceComponent->getHostDeviceId()]->setHostDeviceComponent($hostDeviceComponent);
            }
        }
    }

    /**
     * @param Request $request
     * @param $uuid
     * @param $platformId
     * @param null $displayName
     * @return HostDeviceEntity
     */
    public function createNewHostDevice(Request $request, $uuid, $platformId, $displayName = null)
    {
        $data = [
            DBField::UUID => $uuid,
            DBField::PLATFORM_ID => $platformId,
            DBField::DISPLAY_NAME => $displayName,
            DBField::CREATE_TIME => $request->getCurrentSqlTime(),
            DBField::IS_ACTIVE => 1,
            DBField::CREATED_BY => $request->requestId
        ];

        /** @var HostDeviceEntity $hostDevice */
        $hostDevice = $this->query($request->db)->createNewEntity($request, $data);

        return $hostDevice;
    }

    /**
     * @param Request $request
     * @param $uuid
     * @return HostDeviceEntity
     */
    public function getHostDeviceByUuid(Request $request, $uuid)
    {
        /** @var HostDeviceEntity $hostDevice */
        $hostDevice = $this->query($request->db)
            ->filter($this->filters->byUuid($uuid))
            ->get_entity($request);

        return $hostDevice;
    }

    /**
     * @param Request $request
     * @param $hostDeviceId
     * @return array|HostDeviceEntity
     */
    public function getHostDeviceById(Request $request, $hostDeviceId, $expand = false)
    {
        /** @var HostDeviceEntity $hostDevice */
        $hostDevice = $this->query($request->db)
            ->filter($this->filters->byPk($hostDeviceId))
            ->get_entity($request);

        if ($expand)
            $this->postProcessHostDevices($request, $hostDevice);

        return $hostDevice;
    }

    /**
     * @param Request $request
     * @param $hostDeviceIds
     * @return HostDeviceEntity[]
     */
    public function getHostDevicesByIds(Request $request, $hostDeviceIds, $index = true)
    {
        /** @var HostDeviceEntity[] $hostDevices */
        $hostDevices = $this->query($request->db)
            ->filter($this->filters->byPk($hostDeviceIds))
            ->get_entities($request);

        $this->postProcessHostDevices($request, $hostDevices);

        return $index ? $this->index($hostDevices) : $hostDevices;
    }

}

class HostsDevicesComponentsManager extends BaseEntityManager
{
    protected $entityClass = HostDeviceComponentEntity::class;
    protected $table = Table::HostDeviceComponent;
    protected $table_alias = TableAlias::HostDeviceComponent;
    protected $pk = DBField::HOST_DEVICE_COMPONENT_ID;

    protected $foreign_managers = [
    ];

    public static $fields = [
        DBField::HOST_DEVICE_COMPONENT_ID,
        DBField::HOST_DEVICE_ID,
        DBField::DISPLAY_NAME,
        DBField::VALUE,
        DBField::IS_ACTIVE,
        DBField::CREATED_BY,
        DBField::MODIFIED_BY,
        DBField::DELETED_BY
    ];

    /**
     * @param HostDeviceComponentEntity $data
     * @param Request $request
     */
    public function processVFields(DBManagerEntity $data, Request $request)
    {
        $data->updateField(VField::PROCESSED_VALUES, json_decode($data->getValue(), true));
    }

    /**
     * @param Request $request
     * @param $hostDeviceId
     * @param $displayName
     * @param array $value
     * @return HostDeviceComponentEntity
     */
    public function createNewHostDeviceComponent(Request $request, $hostDeviceId, $displayName, $value = [])
    {
        $data = [
            DBField::HOST_DEVICE_ID => $hostDeviceId,
            DBField::DISPLAY_NAME => $displayName,
            DBField::VALUE => json_encode($value),
            DBField::IS_ACTIVE => 1,
            DBField::CREATED_BY => $request->requestId
        ];

        /** @var HostDeviceComponentEntity $hostDeviceComponent */
        $hostDeviceComponent = $this->query($request->db)->createNewEntity($request, $data);

        return $hostDeviceComponent;
    }

    /**
     * @param Request $request
     * @param $hostDeviceIds
     * @return HostDeviceComponentEntity[]
     */
    public function getHostDeviceComponentsByHostDeviceIds(Request $request, $hostDeviceIds)
    {
        /** @var HostDeviceComponentEntity[] $hostDeviceComponents */
        $hostDeviceComponents = $this->query($request->db)
            ->filter($this->filters->byHostDeviceId($hostDeviceIds))
            ->get_entities($request);

        return $hostDeviceComponents;
    }
}

class ScreensManager extends BaseEntityManager
{

    protected $entityClass = ScreenEntity::class;
    protected $table = Table::Screen;
    protected $table_alias = TableAlias::Screen;
    protected $pk = DBField::SCREEN_ID;

    protected $foreign_managers = [
        NetworksManager::class => DBField::NETWORK_ID
    ];

    public static $fields = [
        DBField::SCREEN_ID,
        DBField::HOST_ID,
        DBField::NETWORK_ID,
        DBField::DISPLAY_NAME,

        DBField::IS_ACTIVE,
        DBField::CREATED_BY,
        DBField::MODIFIED_BY,
        DBField::DELETED_BY
    ];

    /**
     * @param ScreenEntity $data
     * @param Request $request
     */
    public function processVFields(DBManagerEntity $data, Request $request)
    {

    }

    /**
     * @param Request $request
     * @param bool $includeFields
     * @return SQLQuery
     */
    protected function queryJoinNetworks(Request $request, $includeFields = true)
    {
        $networksManager = $request->managers->networks();

        $queryBuilder = $this->query($request->db)
            ->inner_join($networksManager);

        if ($includeFields)
            $queryBuilder->fields($this->selectAliasedManagerFields($networksManager));

        return $queryBuilder;
    }


    /**
     * @param $addresses AddressEntity[]
     * @param bool $requirePk
     * @return FormField[]
     */
    public function getFormFields($hosts, $networks, $requirePk = true)
    {
        $fields = [
            new IntegerField(DBField::ID, 'Screen ID', $requirePk),
            new SelectField(DBField::HOST_ID, 'Host ID', $hosts, true),
            new SelectField(DBField::NETWORK_ID, 'Network', $networks, true),
            new CharField(DBField::DISPLAY_NAME, 'Name', 100, true)
        ];

        return $fields;
    }

    /**
     * @param Request $request
     * @param $hostId
     * @param $networkId
     * @param $displayName
     * @return ScreenEntity
     */
    public function createNewScreen(Request $request, $hostId, $networkId, $displayName = null)
    {
        $data = [
            DBField::HOST_ID => $hostId,
            DBField::NETWORK_ID => $networkId,
            DBField::DISPLAY_NAME => $displayName
        ];

        /** @var ScreenEntity $screen */
        $screen = $this->query($request->db)->createNewEntity($request, $data);

        return $screen;
    }

    /**
     * @param Request $request
     * @param $screenId
     * @return array|ScreenEntity
     */
    public function getScreenById(Request $request, $screenId)
    {
        return $this->queryJoinNetworks($request)
            ->filter($this->filters->byPk($screenId))
            ->filter($this->filters->isActive())
            ->get_entity($request);
    }

    /**
     * @param Request $request
     * @param $hostId
     * @return ScreenEntity[]
     */
    public function getScreensByHostId(Request $request, $hostId)
    {
        /** @var ScreenEntity[] $screens */
        $screens = $this->query($request->db)
            ->filter($this->filters->byHostId($hostId))
            ->filter($this->filters->isActive())
            ->get_entities($request);

        return $screens;
    }
}

class NetworksManager extends BaseEntityManager {

    protected $entityClass = NetworkEntity::class;
    protected $table = Table::Network;
    protected $table_alias = TableAlias::Network;
    protected $pk = DBField::NETWORK_ID;

    public static $fields = [
        DBField::NETWORK_ID,
        DBField::HOST_ID,
        DBField::DISPLAY_NAME,
        DBField::SSID,
        DBField::PASSWORD,

        DBField::IS_ACTIVE,
        DBField::CREATED_BY,
        DBField::MODIFIED_BY,
        DBField::DELETED_BY
    ];

    /**
     * @param NetworkEntity $data
     * @param Request $request
     */
    public function processVFields(DBManagerEntity $data, Request $request)
    {

    }

    /**
     * @param Request $request
     * @param $hostId
     * @param $displayName
     * @param $ssid
     * @param $password
     * @return NetworkEntity
     */
    public function createNewNetwork(Request $request, $hostId, $displayName = null, $ssid = null, $password = null)
    {
        $data = [
            DBField::HOST_ID => $hostId,
            DBField::DISPLAY_NAME => $displayName,
            DBField::SSID => $ssid,
            DBField::PASSWORD => $password
        ];

        /** @var NetworkEntity $network */
        $network = $this->query($request->db)->createNewEntity($request, $data);

        return $network;
    }

    /**
     * @param $addresses AddressEntity[]
     * @param bool $requirePk
     * @return FormField[]
     */
    public function getFormFields($hosts, $requirePk = true)
    {
        $fields = [
            new IntegerField(DBField::ID, 'Network ID', $requirePk),
            new SelectField(DBField::HOST_ID, 'Host Id', $hosts, true),
            new CharField(DBField::DISPLAY_NAME, 'Name', 100, true),
            new CharField(DBField::SSID, 'SSID', 32, true),
            new CharField(DBField::PASSWORD, 'Password', 64, false)
        ];

        return $fields;
    }

    /**
     * @param Request $request
     * @param $networkId
     * @return array|NetworkEntity
     */
    public function getNetworkById(Request $request, $networkId)
    {
        return $this->query($request->db)
            ->filter($this->filters->byPk($networkId))
            ->filter($this->filters->isActive())
            ->get_entity($request);
    }

    /**
     * @param Request $request
     * @param $hostId
     * @return NetworkEntity[]
     */
    public function getNetworksByHostId(Request $request, $hostId)
    {
        return $this->query($request->db)
            ->filter($this->filters->byHostId($hostId))
            ->filter($this->filters->isActive())
            ->get_entities($request);
    }
}

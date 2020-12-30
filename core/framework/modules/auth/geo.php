<?php
/**
 * Mapping ips to country
 *
 * @package auth
 */

class GeoIpMapperManager extends BaseEntityManager
{
    protected $entityClass = GeoIpMapperEntity::class;
    protected $table = Table::GeoIpMap;
    protected $table_alias = Table::GeoIpMap;
    protected $pk = DBField::GEO_IP_MAP_ID;

    const GNS_KEY_PREFIX = GNS_ROOT.'geo-ip';

    const COOKIE = 'gcGeoMapping';

    public static $fields = [
        DBField::GEO_IP_MAP_ID,
        DBField::IP_FROM,
        DBField::IP_TO,
        DBField::COUNTRY_ID,
        DBField::REGION_NAME,
        DBField::CITY_NAME,
        DBField::LATITUDE,
        DBField::LONGITUDE,
        DBField::ZIP_CODE,
        DBField::TIME_ZONE
    ];

    /**
     * @param $id
     * @return string
     */
    public function generateEntityIdCacheKey($id)
    {
        return self::GNS_KEY_PREFIX.".id-{$id}";
    }

    /**
     * @param $intIp
     * @return string
     */
    public function generateCacheKeyForIntIp($intIp)
    {
        return self::GNS_KEY_PREFIX.".ip.{$intIp}";
    }

    /**
     * @param $ip
     * @param bool|true $failSilently
     * @return string
     * @throws ObjectNotFound
     */
    public static function getCountryByIp($ip, $failSilently = true)
    {
        $decimal_ip = ip2long($ip);
        try {
            $country = GeoIpMapperManager::objects()
                ->filter(Q::Lte(DBField::START_IP, $decimal_ip))
                ->sort_desc(DBField::START_IP)
                ->get_value(DBField::COUNTRY);

        } catch (ObjectNotFound $e) {
            $country = '';
            if (!$failSilently)
                throw $e;
        }
        return $country;
    }

    /**
     * @param Request $request
     * @param $id
     * @return GeoIpMapperEntity
     * @throws ObjectNotFound
     */
    public function getGeoIpMappingById(Request $request, $id)
    {
        return $this->query($request->db)
            ->local_cache($this->generateEntityIdCacheKey($id), ONE_DAY)
            ->filter($this->filters->byPk($id))
            ->get_entity($request);
    }

    /**
     * @param Request $request
     * @param $ip
     * @return GeoIpMapperEntity
     * @throws ObjectNotFound
     */
    public function getGeoIpMappingByIp(Request $request, $ip)
    {
        $ipAsInteger = ip2long($ip);

        $ipMapping =  $this->query($request->db)
            ->local_cache($this->generateCacheKeyForIntIp($ipAsInteger), FIFTEEN_MINUTES, false)
            ->filter($this->filters->Lte(DBField::IP_FROM, $ipAsInteger))
            ->sort_desc(DBField::IP_FROM)
            ->get_entity($request);

        if (!$ipMapping) {
            $ipMapping = $this->createNulledEntity($request);
            $ipMapping[DBField::COUNTRY_ID] = CountriesManager::ID_UNITED_STATES;
        }


        return $ipMapping;
    }
}

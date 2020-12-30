<?php

/**
 * Locale Managers
 *
 * @package managers
 */

/**
 * @param Request $request
 * @param $lang
 * @return string
 */
function get_flag_url($lang)
{
    global $CONFIG;
    return $CONFIG[ESCConfiguration::IMAGES_URL].LanguagesManager::FLAG_DIRECTORY.'/'.$lang.'.'.Image::FILE_TYPE_PNG;
}

Modules::uses(Modules::MANAGERS);
Entities::uses("locale");

class LanguagesManager extends BaseEntityManager
{
    // Languages Flag Directory
    const FLAG_DIRECTORY = 'langs';

    // Language IDs
    const LANGUAGE_NORWEGIAN = 'no';
    const LANGUAGE_JAPANESE = 'jp';
    const LANGUAGE_CHINESE_MANDARIN = 'cn';
    const LANGUAGE_CHINESE_TAIWANESE = 'zw';
    const LANGUAGE_RUSSIAN = 'ru';
    const LANGUAGE_SPANISH = 'es';
    const LANGUAGE_ALBANIAN = 'al';
    const LANGUAGE_ARABIC = 'ar';
    const LANGUAGE_BULGARIAN = 'bg';
    const LANGUAGE_BRAZILIAN_PORTUGUESE = 'br';
    const LANGUAGE_CZECH = 'cz';
    const LANGUAGE_DANISH = 'dk';
    const LANGUAGE_ENGLISH = 'en';
    const LANGUAGE_FRENCH = 'fr';
    const LANGUAGE_GERMAN = 'de';
    const LANGUAGE_GREEK = 'el';
    const LANGUAGE_PERSIAN = 'fa';
    const LANGUAGE_FINNISH = 'fi';
    const LANGUAGE_FILIPINO = 'fo';
    const LANGUAGE_HINDI = 'hi';
    const LANGUAGE_HUNGARIAN = 'hu';
    const LANGUAGE_INDONESIAN = 'id';
    const LANGUAGE_ITALIAN = 'it';
    const LANGUAGE_HEBREW = 'iw';
    const LANGUAGE_KOREAN = 'kr';
    const LANGUAGE_LATVIAN = 'lv';
    const LANGUAGE_MALAY = 'my';
    const LANGUAGE_DUTCH = 'nl';
    const LANGUAGE_POLISH = 'pl';
    const LANGUAGE_PORTUGUESE = 'pt';
    const LANGUAGE_ROMANIAN = 'ro';
    const LANGUAGE_SLOVAK = 'sk';
    const LANGUAGE_THAI = 'th';
    const LANGUAGE_TURKISH = 'tr';
    const LANGUAGE_UKRANIAN = 'ua';
    const LANGUAGE_URDU = 'ur';
    const LANGUAGE_VIETNAMESE = 'vi';

    protected $entityClass = LanguageEntity::class;
    protected $table = Table::Languages;
    protected $table_alias = TableAlias::Langs;
    protected $pk = DBField::LANGUAGE_ID;

    public static $fields = [
        DBField::LANGUAGE_ID,
        DBField::DISPLAY_NAME,
        DBField::I18N_ACTIVE,
        DBField::I18N_PUBLIC
    ];

    /** @var LanguageEntity[] */
    public static $source_languages = [];


    /**
     * @param LanguageEntity $data
     * @param Request $request
     * @return LanguageEntity
     */
    public function processVFields(DBManagerEntity $data, Request $request)
    {
        return $data;
    }

    /**
     * @return string
     */
    public static function generateCacheKey()
    {
        return SettingsManager::GNS_KEY_PREFIX.'.all-languages';
    }

    /**
     * @return string
     */
    public function generateI18nLangIdsCacheKey($canSuggestTranslations = false)
    {
        $canSuggestSuffix = $canSuggestTranslations ? 'all' : 'public';
        return SettingsManager::GNS_KEY_PREFIX.".i18n-langIds.{$canSuggestSuffix}";
    }

    /**
     * @return array
     */
    public static function bustAllLanguagesCache(Request $request)
    {
        self::$source_languages = [];
        $cache_keys = [self::generateCacheKey()];
        $request->cache->deleteKeys($cache_keys, true);
        return $cache_keys;
    }

    /**
     * @param $lang_id
     * @return string
     */
    public function getFlagUrlByLangId($lang_id)
    {
        return get_flag_url($lang_id);
    }

    /**
     * @param Request $request
     * @return LanguageEntity[]
     */
    public function getAllLangs(Request $request)
    {
        if (!empty(self::$source_languages))
            return self::$source_languages;

        $languages = $this->query($request->db)
            ->cache($this->generateCacheKey(), ONE_WEEK)
            ->get_entities($request);

        /** @var LanguageEntity[] $languages */
        if ($languages)
            $languages = array_index($languages, $this->getPkField());

        self::$source_languages = $languages;

        return $languages;
    }

    /**
     * @param Request $request
     * @param $lang
     * @param bool|true $translate
     * @return null|string
     */
    public function getLanguageNameById(Request $request, $lang, $translate = true)
    {
        if (empty(self::$source_languages[$lang]))
            $this->getAllLangs($request);

        if ($translate)
            return isset(self::$source_languages[$lang]) ? self::$source_languages[$lang][DBField::NAME] : null;
        else
            return isset(self::$source_languages[$lang]) ? $request->translations[self::$source_languages[$lang][DBField::NAME]] : null;
    }

    /**
     * @param Request $request
     * @param $langId
     * @return LanguageEntity|array
     */
    public function getLanguageById(Request $request, $langId)
    {
        if (empty(self::$source_languages[$langId]))
            $this->getAllLangs($request);

        return isset(self::$source_languages[$langId]) ? self::$source_languages[$langId] : [];
    }

    /**
     * @param Request $request
     * @return array
     */
    public function getActiveI18nLanguageIds(Request $request)
    {
        $canSuggestTranslations = $request->user->permissions->has(RightsManager::RIGHT_I18N, Rights::USE);

        $query = $this->query($request->db)
            ->cache($this->generateI18nLangIdsCacheKey($canSuggestTranslations), ONE_WEEK)
            ->filter($this->filters->isI18nActive());

        if (!$canSuggestTranslations)
            $query->filter($this->filters->isI18nPublic());

        $langIds = $query->get_values($this->getPkField());

        $defaultLangId = $request->settings()->getContentDefaultLang();

        if (!in_array($defaultLangId, $langIds))
            $langIds[] = $defaultLangId;

        return $langIds;
    }

    /**
     * @param Request $request
     * @return LanguageEntity[]|array
     */
    public function getActiveI18nLanguages(Request $request)
    {
        $langs = $this->getAllLangs($request);

        $ui_langs = [];
        foreach ($langs as $lang) {
            if ($lang->is_i18n_active()) {
                if ($lang->is_i18n_public() || $request->user->permissions->has(RightsManager::RIGHT_I18N, Rights::MODERATE)) {
                    $ui_langs[$lang->getPk()] = $lang;
                }
            }
        }

        return $ui_langs;
    }

    /**
     * @param Request $request
     * @param $langIds
     * @return LanguageEntity[]|array
     */
    public function getLanguagesByIds(Request $request, $langIds)
    {
        $langs = [];

        $languages = $this->getAllLangs($request);

        foreach ($langIds as $langId) {
            if (array_key_exists($langId, $languages))
                $langs[$langId] = $languages[$langId];
        }
        return $langs;
    }
}


class CountriesManager extends BaseEntityManager
{
    protected $entityClass = CountryEntity::class;
    protected $table = Table::Countries;
    protected $table_alias = TableAlias::Countries;
    protected $pk = DBField::COUNTRY_ID;

    /** @var CountryEntity[]|array */
    public static $countries_source = [];

    const ID_UNITED_STATES = 'us';

    const CACHE_TIME = TWO_WEEKS;

    public static $fields = [
        DBField::COUNTRY_ID,
        DBField::ISO3,
        DBField::PHONE_CODE,
        DBField::DISPLAY_NAME,
        DBField::GEO_REGION_ID,
        DBField::CURRENCY_ID,
        DBField::DEFAULT_LANGUAGE_ID,
    ];

    public $removed_json_fields = [];

    /**
     * @param $request
     * @return array|CountryEntity[]
     */
    public function getAllCountries(Request $request)
    {
        /** @var CountryEntity[] $countries */
        $countries = $this->query($request->db)
            ->local_cache($this->generateCacheKey(), ONE_WEEK)
            ->get_entities($request);

        if ($countries)
            $countries = array_index($countries, $this->getPkField());

        return $countries;
    }

    /**
     * @return string
     */
    public static function generateCacheKey()
    {
        return SettingsManager::GNS_KEY_PREFIX.'.all-countries-data';
    }

    /**
     * @param Request $request
     * @param $country_id
     * @return array|CountryEntity
     */
    public function getCountryById(Request $request, $country_id)
    {
        $countries = $this->getAllCountries($request);

        if (array_key_exists($country_id, $countries))
            return $countries[$country_id];

        else
            return $this->createEntity([
                    DBField::COUNTRY_ID => 0,
                    DBField::PHONE_CODE => null,
                    DBField::ISO3 => null,
                    DBField::DISPLAY_NAME => 'Unknown',
                    DBField::GEO_REGION_ID => 1
                ],
                $request);
    }

    /**
     * @param Request $request
     * @param $geo_region_id
     * @return array|CountryEntity[]
     */
    public static function getCountriesByGeoRegionId(Request $request, $geo_region_id) {
        if (empty(static::$countries_source))
            self::$countries_source = $countries = self::getAllCountries($request);
        $countries = [];
        foreach (static::$countries_source as $country) {
            if ($country[DBField::GEO_REGION_ID] == $geo_region_id)
                $countries[] = $country;
        }
        return $countries;
    }


}

class GeoRegionsManager extends BaseEntityManager
{
    protected $entityClass = GeoRegionEntity::class;
    protected $table = Table::GeoRegions;
    protected $table_alias = TableAlias::GeoRegions;
    protected $pk = DBField::GEO_REGION_ID;
    const CACHE_TIME = ONE_DAY;

    /** @var array|GeoRegionEntity[] */
    public static $geo_regions_source = [];

    /** @var array */
    public static $fields = [
        DBField::GEO_REGION_ID,
        DBField::DISPLAY_NAME,
        DBField::IS_ACTIVE,
        DBField::CREATE_TIME,
        DBField::CREATOR_ID,
    ];

    /** @var array */
    public $removed_json_fields = [DBField::CREATOR_ID, DBField::CREATE_TIME];

    // Geo Region Ids
    const GEO_ID_UNKNOWN = 0;
    const GEO_ID_ALL = 1;
    const GEO_ID_NORTH_AMERICA = 2;
    const GEO_ID_LATIN_AMERICA = 3;
    const GEO_ID_SOUTH_AMERICA = 4;
    const GEO_ID_UK_IRELAND = 5;
    const GEO_ID_NORTHERN_EUROPE = 6;
    const GEO_ID_WESTERN_EUROPE = 7;
    const GEO_ID_SOUTHERN_EUROPE = 8;
    const GEO_ID_EASTERN_EUROPE = 9;
    const GEO_ID_MIDDLE_EAST = 10;
    const GEO_ID_RUSSIA = 11;
    const GEO_ID_JAPAN = 12;
    const GEO_ID_CHINA = 13;
    const GEO_ID_SOUTHEAST_ASIA_MIDDLE_ASIA = 14;
    const GEO_ID_AUSTRALIA_PACIFIC = 15;
    const GEO_ID_AFRICA = 16;

    /**
     * @param GeoRegionEntity $data
     * @param Request $request
     * @return mixed
     */
    public function processVFields(DBManagerEntity $data, Request $request)
    {
        return $data;
    }


    /**
     * @param Request $request
     * @param $countryId
     * @param bool|false $includeCountries
     * @return GeoRegionEntity
     */
    public function getGeoRegionByCountryId(Request $request, $countryId)
    {
        $countriesManager = $request->managers->countries();


        if ($countryId) {
            $country = $countriesManager->getCountryById($request, $countryId);

            $region = $this->getGeoRegionById($request, $country ? $country->getGeoRegionId() : self::GEO_ID_ALL);
        } else {
            $region = $this->getGeoRegionById($request, self::GEO_ID_ALL);
        }
        return $region;
    }

    /**
     * @param $request
     * @return array|GeoRegionEntity[]
     */
    public function getAllGeoRegions(Request $request, $includeCountries = false)
    {
        $countriesManager = $request->managers->countries();

        if (!empty(self::$geo_regions_source))
            return self::$geo_regions_source;

        /** @var GeoRegionEntity[] $geo_regions */
        $geo_regions = $this->query($request->db)
            ->filter($this->filters->isActive())
            ->local_cache($this->generateCacheKey(), self::CACHE_TIME)
            ->get_entities($request);


        foreach ($geo_regions as $key => $region) {
            if ($includeCountries)
                $region[VField::COUNTRIES] = $countriesManager->getCountriesByGeoRegionId($request, $region->getPk());

            self::$geo_regions_source[$region->getPk()] = $region;
        }

        return $geo_regions;
    }

    public static function generateCacheKey()
    {
        return SettingsManager::GNS_KEY_PREFIX.'.all-geo-regions';
    }

    /**
     * @param Request $request
     * @param $geo_region_id
     * @return GeoRegionEntity
     */
    public function getGeoRegionById(Request $request, $geo_region_id)
    {

        if (empty(self::$geo_regions_source))
            self::$geo_regions_source = $geo_regions = $this->getAllGeoRegions($request);
        else
            $geo_regions = self::$geo_regions_source;

        foreach ($geo_regions as $region) {
            if ($region->getPk() == $geo_region_id)
                return $region;
        }

        if (empty(self::$geo_regions_source[$geo_region_id]))
            $geoRegion = $this->createEntity(
                [
                    DBField::GEO_REGION_ID => 1,
                    DBField::DISPLAY_NAME => 'Global',
                    DBField::IS_ACTIVE => 1
                ],
                $request
            );
        else
            $geoRegion = self::$geo_regions_source[$geo_region_id];

        return $geoRegion;
    }

    /**
     * @param Request $request
     * @param $geo_region_id
     * @param bool|true $translate
     * @return mixed|null|string
     */
    public function getRegionNameById(Request $request, $geo_region_id, $translate = false)
    {
        $region = $this->getGeoRegionById($request, $geo_region_id);
        return $translate ? $request->translations[$region[DBField::DISPLAY_NAME]] : $region[DBField::DISPLAY_NAME];
    }

}

class CurrenciesManager extends BaseEntityManager {

    const TYPE_USD = 1;

    protected $entityClass = CurrencyEntity::class;
    protected $table = Table::Currencies;
    protected $table_alias = TableAlias::Currencies;

    protected $pk = DBField::CURRENCY_ID;
    protected $foreign_managers = [];

    public static $fields = [
        DBField::CURRENCY_ID,
        DBField::NAME,
        DBField::DISPLAY_NAME,
        DBField::IS_ACTIVE,
        DBField::UPDATER_ID,
        DBField::UPDATE_TIME,
        DBField::CREATOR_ID,
        DBField::CREATE_TIME
    ];

}

class AddressesManager extends BaseEntityManager {

    protected $entityClass = AddressEntity::class;
    protected $table = Table::Addresses;
    protected $table_alias = TableAlias::Addresses;

    protected $pk = DBField::ADDRESS_ID;
    protected $foreign_managers = [
        AddressesTypesManager::class => DBField::ADDRESS_TYPE_ID,
        CountriesManager::class => DBField::COUNTRY_ID,
    ];

    public static $fields = [
        DBField::ADDRESS_ID,
        DBField::ADDRESS_TYPE_ID,
        DBField::CONTEXT_ENTITY_TYPE_ID,
        DBField::CONTEXT_ENTITY_ID,
        DBField::IS_PRIMARY,
        DBField::IS_ACTIVE,
        DBField::DISPLAY_NAME,
        DBField::FIRSTNAME,
        DBField::LASTNAME,
        DBField::PHONE_NUMBER,
        DBField::ADDRESS_LINE1,
        DBField::ADDRESS_LINE2,
        DBField::ADDRESS_LINE3,
        DBField::CITY,
        DBField::STATE,
        DBField::ZIP,
        DBField::POSTAL_CODE_LOW,
        DBField::COUNTRY_ID,
        DBField::UPDATER_ID,
        DBField::UPDATE_TIME,
        DBField::CREATOR_ID,
        DBField::CREATE_TIME
    ];

    /**
     * @param AddressEntity $data
     * @param Request $request
     * @return
     */
    public function processVFields(DBManagerEntity $data, Request $request)
    {
        if (!$data->hasField(VField::COUNTRY))
            $data->updateField(VField::COUNTRY, []);
    }

    /**
     * @param Request $request
     * @param $contextEntityTypeId
     * @param $contextEntityId
     * @param int $addressTypeId
     * @param string $countryId
     * @param int $isPrimary
     * @param null $displayName
     * @param null $firstName
     * @param null $lastName
     * @param null $phoneNumber
     * @param null $addressLine1
     * @param null $addressLine2
     * @param null $addressLine3
     * @param null $city
     * @param null $state
     * @param null $zip
     * @return AddressEntity
     */
    public function createNewAddress(Request $request, $contextEntityTypeId, $contextEntityId, $countryId = 'us',
                                     $addressTypeId = AddressesTypesManager::ID_PRIVATE, $isPrimary = 1, $displayName = null,
                                     $firstName = null, $lastName = null, $phoneNumber = null, $addressLine1 = null,
                                     $addressLine2 = null, $addressLine3 = null, $city = null, $state = null, $zip = null)
    {
        $addressData = [
            DBField::ADDRESS_TYPE_ID => $addressTypeId,
            DBField::CONTEXT_ENTITY_TYPE_ID => $contextEntityTypeId,
            DBField::CONTEXT_ENTITY_ID => $contextEntityId,
            DBField::IS_PRIMARY => $isPrimary,
            DBField::IS_ACTIVE => 1,
            DBField::DISPLAY_NAME => $displayName,
            DBField::FIRSTNAME => $firstName,
            DBField::LASTNAME => $lastName,
            DBField::PHONE_NUMBER => $phoneNumber,
            DBField::ADDRESS_LINE1 => $addressLine1,
            DBField::ADDRESS_LINE2 => $addressLine2,
            DBField::ADDRESS_LINE3 => $addressLine3,
            DBField::CITY => $city,
            DBField::STATE => $state,
            DBField::ZIP => $zip,
            DBField::POSTAL_CODE_LOW => null,
            DBField::COUNTRY_ID => $countryId,
        ];

        /** @var AddressEntity $address */
        $address = $this->query($request->db)->createNewEntity($request, $addressData);

        return $address;
    }

    /**
     * @param Request $request
     * @param bool $includeFields
     * @return SQLQuery
     */
    protected function queryJoinAddressTypes(Request $request, $includeFields = true)
    {
        $addressTypesManager = $request->managers->addressesTypes();
        $countriesManager = $request->managers->countries();

        $queryBuilder = $this->query($request->db)->inner_join($addressTypesManager);

        if ($includeFields) {

            $queryBuilder->left_join($countriesManager)
                ->fields($this->selectAliasedManagerFields($addressTypesManager, $countriesManager));
        }

        return $queryBuilder;
    }

    /**
     * @param Request $request
     * @param $addressId
     * @return AddressEntity|[]
     */
    public function getAddressById(Request $request, $addressId)
    {
        return $this->queryJoinAddressTypes($request)
            ->filter($this->filters->byPk($addressId))
            ->get_entity($request);
    }

    /**
     * @param Request $request
     * @param $locationIds
     * @return array
     */
    public function getAddressesByLocationIds(Request $request, $locationIds)
    {
        /** @var AddressEntity[] $addresses */
        $addresses = $this->queryJoinAddressTypes($request)
            ->filter($this->filters->byContextEntityTypeId(EntityType::LOCATION))
            ->filter($this->filters->byContextEntityId($locationIds))
            ->get_entities($request);

        $groupedAddresses = [];

        foreach ($addresses as $address) {
            if (!array_key_exists($address->getContextEntityId(), $groupedAddresses))
                $groupedAddresses[$address->getContextEntityId()] = [];

            $groupedAddresses[$address->getContextEntityId()][$address->getPk()] = $address;
        }

        return $groupedAddresses;
    }

    /**
     * @param Request $request
     * @param $ownerTypeId
     * @param $ownerId
     * @param $countryId
     * @param $state
     * @param $zipCode
     * @param $city
     * @param $addressLine1
     * @return AddressEntity
     */
    public function getAddressByContextOwnerAndValues(Request $request, $ownerTypeId, $ownerId, $countryId, $state,
                                                      $zipCode, $city, $addressLine1)
    {
        /** @var AddressEntity $address */
        $address = $this->query($request->db)
            ->filter($this->filters->byContextEntityTypeId($ownerTypeId))
            ->filter($this->filters->byContextEntityId($ownerId))
            ->filter($this->filters->byCountryId($countryId))
            ->filter($this->filters->byState($state))
            ->filter($this->filters->byZip($zipCode))
            ->filter($this->filters->byCity($city))
            ->filter($this->filters->byAddressLine1($addressLine1))
            ->filter($this->filters->isActive())
            ->get_entity($request);

        return $address;
    }

    /**
     * @param Request $request
     * @param $ownerTypeId
     * @param $ownerId
     * @param $addressTypeId
     * @return AddressEntity
     */
    public function getAddressByContextOwnerAndType(Request $request, $ownerTypeId, $ownerId, $addressTypeId)
    {
        /** @var AddressEntity $address */
        $address = $this->query($request->db)
            ->filter($this->filters->byContextEntityTypeId($ownerTypeId))
            ->filter($this->filters->byContextEntityId($ownerId))
            ->filter($this->filters->byAddressTypeId($addressTypeId))
            ->filter($this->filters->isActive())
            ->get_entity($request);

        return $address;
    }

    /**
     * @param Request $request
     * @param $ownerId
     * @return array
     */
    public function getAddressIdsByOwner(Request $request, $ownerId)
    {
        return $this->query($request->db)
            ->filter($this->filters->byContextEntityTypeId(EntityType::USER))
            ->filter($this->filters->byContextEntityId($ownerId))
            ->filter($this->filters->isActive())
            ->get_values($this->getPkField());
    }

}

class AddressesTypesManager extends BaseEntityManager {

    const ID_PRIVATE = 1;
    const ID_BUSINESS = 2;

    protected $entityClass = AddressTypeEntity::class;
    protected $table = Table::AddressType;
    protected $table_alias = TableAlias::AddressType;

    protected $pk = DBField::ADDRESS_TYPE_ID;
    protected $foreign_managers = [];

    public static $fields = [
        DBField::ADDRESS_TYPE_ID,
        DBField::NAME,
        DBField::DISPLAY_NAME,
        DBField::DISPLAY_ORDER,
        DBField::IS_ACTIVE,
        DBField::UPDATER_ID,
        DBField::UPDATE_TIME,
        DBField::CREATOR_ID,
        DBField::CREATE_TIME
    ];
}
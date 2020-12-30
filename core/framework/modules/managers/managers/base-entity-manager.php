<?php
/**
 * Created by PhpStorm.
 * User: ccarter
 * Date: 1/1/16
 * Time: 6:36 AM
 */


class BaseManagerEntityException extends Exception {}

class BaseEntityManager extends Manager {

    /** @var DB */
    public $db;

    /** @var string */
    protected $entityClass;

    /** @var $table_alias string */
    protected $table_alias = '';

    const GNS_KEY_PREFIX = GNS_ROOT.'.';

    const METHOD_GET_ENTITY_CACHE_KEY_BY_ID = 'generateEntityIdCacheKey';
    const METHOD_GET_ENTITY_CACHE_KEY_BY_STRING = 'generateEntityStringCacheKey';
    const METHOD_PROCESS_VIRTUAL_FIELDS = 'processVFields';
    const METHOD_BUST_BASIC_CACHE = 'bustBasicCache';
    const METHOD_GET_EDIT_FORM_FIELDS = 'getEditEntityFormFields';

    const METHOD_PREFIX_PROCESS_VIRTUAL_FIELD = 'process_virtual_field';

    public $entity_image_settings = [];

    public $base_removed_json_fields = [
        VField::CURRENT_TIMESTAMP,

        DBField::CREATED_BY,
        DBField::MODIFIED_BY,
        DBField::DELETED_BY,
    ];

    public static $fields = [];

    public $removed_json_fields = [

    ];

    public $localTimeZoneSourceFieldMappings = [
        DBField::START_TIME => VField::LOCAL_START_TIME,
        DBField::END_TIME => VField::LOCAL_END_TIME,
        DBField::DELETE_TIME => VField::LOCAL_DELETE_TIME,
        DBField::CREATE_TIME => VField::LOCAL_CREATE_TIME,
        DBField::LAST_PING_TIME => VField::LOCAL_LAST_PING_TIME,
        DBField::JOIN_DATE => VField::LOCAL_JOIN_DATE,
        DBField::OPENED_TIME => VField::LOCAL_OPENED_TIME,
        DBField::CLICKED_TIME => VField::LOCAL_CLICKED_TIME,
        DBField::SENT_TIME => VField::LOCAL_SENT_TIME,
    ];

    /** @var BaseDBQueryFiltersLocator */
    public $filters;

    /** @var Q $q */
    public $q;

    /** @var  ReflectionClass $reflection */
    public $reflection;

    protected $foreign_managers = [];

    /**
     * BaseEntityManager constructor.
     */
    public function __construct()
    {
        $this->filters = new DBQueryFilters($this);
        $this->name = str_replace('Entity', '', $this->entityClass);
    }

    /**
     * @return mixed
     */
    public function getEntityClass()
    {
        return $this->entityClass;
    }

    /**
     * @return string
     * @throws EntityFieldAccessException
     */
    public function getSlugField()
    {
        if ($this->hasField(DBField::SLUG))
            return DBField::SLUG;

        if ($this->hasField(DBField::USERNAME))
            return DBField::USERNAME;

        if ($this->hasField(DBField::NAME))
            return DBField::NAME;

        throw new EntityFieldAccessException("Slug Field undefined for Manager: ".get_called_class());
    }

    /**
     * @param DB|null $db
     * @return BaseEntityManager
     */
    public function setDataSource(DB $db) {
        $this->db = $db;
        return $this;
    }

    /**
     * @param Request $request
     * @return SQLQuery
     */
    public function query($db = null)
    {
        if ($this->db && !$db)
            $db = $this->db;
        elseif ($db && !$this->db)
            $this->db = $db;

        return $this->objects($db);
    }

    /**
     * @return array
     */
    public function getForeignManagerClasses()
    {
        return !empty($this->foreign_managers) ? array_keys($this->foreign_managers) : [];
    }

    /**
     * @param BaseEntityManager $foreign_manager
     * @return AliasedDBField|DBField
     */
    public function getJoinForeignManagerField(BaseEntityManager $foreign_manager)
    {
        if (!isset($this->foreign_managers[$foreign_manager->getClass()]))
            throw new BaseManagerEntityException("Manager Join Foreign Field not set for: Local({$this->getClass()}) - Foreign ({$foreign_manager->getClass()})");

        return $this->field($this->foreign_managers[$foreign_manager->getClass()]);
    }

    /**
     * @param string $table_alias
     * @return string
     */
    public function buildSqlSelectEntityFields($table_alias = null, $last = false, $default = false)
    {
        if (!$table_alias)
            $table_alias = $this->getTableAlias($default, $table_alias);

        $fields = $this->getDBFields();

        $sql = '';

        if (!$last)
            foreach ($fields as $field)
                $sql .= "{$table_alias}.{$field} as {$field},\r\n";

        else
            $sql = join(", \r\n", $fields)."\r\n";

        return $sql;
    }

    /**
     * @param array|BaseEntityManager[] ...$remote_managers
     * @return array|DBField[]
     */

    public function selectAliasedManagerFields(... $remote_managers)
    {
        $fields = $this->createDBFields();

        foreach ($remote_managers as $manager) {
            if ($manager instanceof BaseEntityManager)
                $fields = array_merge($fields, $manager->createJoinAliasedDBFields());
        }

        return $fields;
    }

    /**
     * @return string
     */
    public function buildCacheKey($suffix = "")
    {
        return static::GNS_KEY_PREFIX.$suffix;
    }

    public function generateCacheKeyForEntity(DBManagerEntity $entity, $suffix = "")
    {
        return $entity->generateCacheKeyPrefix($suffix);
    }


    /**
     * @param $id
     * @param Request $request
     * @param int $cache_time
     * @param DBFilter[]|null $extra_filters
     * @return DBManagerEntity
     * @throws BaseEntityException
     * @throws Http404
     * @throws ObjectNotFound
     */
    public function getEntityByPk($id, Request $request, $cache_time = HALF_AN_HOUR, $extra_filters = null)
    {
        if (!Validators::int($id))
            throw new BaseEntityException("Supplied PK is not an integer");

        // Generate Base Query Object
        $dbQuery = $this->query($request->db)->byPk($id);

        if ($this->hasField(DBField::IS_VISIBLE))
            $dbQuery->filter($this->filters->isVisible());

        if ($this->hasField(DBField::IS_DELETED))
            $dbQuery->filter($this->filters->isNotDeleted());

        if ($extra_filters) {
            if ($extra_filters instanceof DBFilter)
                $dbQuery->filter($extra_filters);
            elseif (is_array($extra_filters)) {
                foreach ($extra_filters as $extra_filter) {
                    if ($extra_filter instanceof DBFilter)
                        $dbQuery->filter($extra_filter);
                }
            }
        }

        if (method_exists($this, $method = self::METHOD_GET_ENTITY_CACHE_KEY_BY_ID))
            $dbQuery->cache($this->$method($id), $cache_time);

        $entity = $dbQuery->get_entity($request);

        return $entity;
    }


    /**
     * This method intercepts an entity "IN" query by primary key, attempts to fetch results from cache, hits DB
     * for any missing results, then stores the fetched data from DB in the cache using entity PK cache keys.
     *
     * If passed a query builder, it will use that query builder instead of it's default one.
     *
     * @param Request $request
     * @param array $ids
     * @param int $cacheTime
     * @param SQLQuery|null $queryBuilder
     * @param null $extra_filters
     * @return array
     */
    public function getEntitiesByPks(Request $request, $ids = [], $cacheTime = DONT_CACHE, SQLQuery $queryBuilder = null, $extra_filters = null)
    {
        $methodGenerateCacheKey = self::METHOD_GET_ENTITY_CACHE_KEY_BY_ID;
        $uncachedPks = [];
        $cachedPks = [];
        $results = [];
        $cache_keys = [];
        $uncachedResults = [];
        $cachedResults = [];

        if ($ids) {
            // If this manager has a cache key generation for entities by primary key
            if (method_exists($this, $methodGenerateCacheKey)) {
                // First, let's generate all the cache keys for the requested entities by their primary key
                foreach ($ids as $id)
                    $cache_keys[] = $this->$methodGenerateCacheKey($id);

                // Submit a Memcached Multi-get request for all cache keys and extract all un-cached entity PKs so
                // we can fetch the data for these from DB instead.
                /** @var array $cachedResults */
                if ($cachedResults = $request->cache->multi_get($cache_keys, $cacheTime)) {
                    $cachedResults = array_index($cachedResults, $this->getPkField());
                    $cachedPks = array_keys($cachedResults);
                    foreach ($ids as $id) {
                        if (!in_array($id, $cachedPks))
                            $uncachedPks[] = $id;
                    }
                } else {
                    $uncachedPks = $ids;
                }
            } else {
                $uncachedPks = $ids;
            }

            if ($uncachedPks) {
                // Create Query Builder Object and apply visibility field where filters as applicable, then set cache keys.
                if (!$queryBuilder) {
                    $queryBuilder = $this->query()->filter($this->filters->byPk($uncachedPks));
                }

                if ($extra_filters)
                    $queryBuilder->filter($extra_filters);

                $uncachedResults = $queryBuilder->get_list();
                if ($cacheTime) {
                    foreach ($uncachedResults as $uncachedResult)
                        $request->cache->set($this->$methodGenerateCacheKey($uncachedResult[$this->getPkField()]), $uncachedResult, $cacheTime, true);
                }

                $uncachedResults = array_index($uncachedResults, $this->getPkField());
            }

            // Combine cached and un-cached results to single array and preserve order of original ID list
            $results = array_merge($cachedResults, $uncachedResults);

        }

        // Convert the raw result data into their respective entity types.
        foreach ($results as $key => $result)
            $results[$key] = $this->createEntity($result, $request);

        return array_values($results);
    }

    /**
     * @param Request $request
     * @param $filterField
     * @param $indexField
     * @param array $fieldValues
     * @param Closure|null $cacheKeyGenerator
     * @param SQLQuery|null $queryBuilder
     * @param int $cacheTime
     * @return array|DBManagerEntity[]
     */
    public function getCachableEntitiesByField(Request $request, $filterField, $indexField, $fieldValues = [],
                                               Closure $cacheKeyGenerator, SQLQuery $queryBuilder,
                                               $cacheTime = DONT_CACHE)
    {
        $uncachedKeys = [];
        $results = [];
        $cacheKeys = [];
        $uncachedResults = [];
        $cachedResults = [];


        if ($fieldValues) {
            // If this manager has a cache key generation for entities by primary key
            // First, let's generate all the cache keys for the requested entities by their primary key
            foreach ($fieldValues as $fieldValue) {
                $cacheKeys[] = $cacheKeyGenerator($fieldValue);
            }

            // Submit a Memcached Multi-get request for all cache keys and extract all un-cached entity PKs so
            // we can fetch the data for these from DB instead.
            /** @var array $cachedResults */
            if ($rawCachedResults = $request->cache->multi_get($cacheKeys, $cacheTime)) {
                foreach ($rawCachedResults as $cacheKey => $resultSet) {
                    $cachedResults = array_merge($cachedResults, $resultSet);
                }
                $cachedResults = array_index($cachedResults, $indexField);
                $cachedKeys = array_keys($rawCachedResults);
                foreach ($fieldValues as $fieldValue) {
                    $cacheKey = $cacheKeyGenerator($fieldValue);
                    if (!in_array($cacheKey, $cachedKeys))
                        $uncachedKeys[] = $cacheKey;
                }
            } else {
                $uncachedKeys = $fieldValues;
            }

            if ($uncachedKeys) {
                $uncachedResults = $queryBuilder->get_list();
                if ($cacheTime) {

                    $resultsToCache = [];
                    foreach ($uncachedResults as $uncachedResult) {
                        $filterId = $uncachedResult[$filterField];

                        if (!array_key_exists($filterId, $resultsToCache))
                            $resultsToCache[$filterId] = [];

                        $resultsToCache[$filterId][] = $uncachedResult;
                    }

                    foreach ($resultsToCache as $cacheKeyId => $resultsList) {
                        $cacheKey = $cacheKeyGenerator($cacheKeyId);
                        $request->cache->set($cacheKey, $resultsList, $cacheTime, true);
                    }
                }

                $uncachedResults = array_index($uncachedResults, $indexField);
            }
            // Combine cached and un-cached results to single array and preserve order of original ID list
            foreach ($cachedResults as $indexKey => $result) {
                if (!array_key_exists($indexKey, $results))
                    $results[$indexKey] = $result;
            }
            foreach ($uncachedResults as $indexKey => $result) {
                if (!array_key_exists($indexKey, $results))
                    $results[$indexKey] = $result;
            }
        }
        // Convert the raw result data into their respective entity types.
        foreach ($results as $key => $result) {
            $results[$key] = $this->createEntity($result, $request, [], $queryBuilder->getEntityClass());
        }


        return array_values($results);
    }

    /**
     * @param $slug
     * @param Request $request
     * @param int $cache_time
     * @return DBManagerEntity
     * @throws Http404
     */
    public function getEntityBySlug($slug, Request $request, $cache_time = HALF_AN_HOUR, $extra_filters = null)
    {
        if (!Validators::string($slug))
            throw new BaseEntityException("Supplied Slug is not a string");

        $db = $request->db;

        // Generate Base Query Object
        $dbQuery = $this->query($db)
            ->bySlug($db->convertSlug($slug));

        if ($this->hasField(DBField::IS_VISIBLE))
            $dbQuery->filter($this->filters->isVisible());

        if ($this->hasField(DBField::IS_DELETED))
            $dbQuery->filter($this->filters->isNotDeleted());

        if ($extra_filters) {
            if ($extra_filters instanceof DBFilter)
                $dbQuery->filter($extra_filters);
            elseif (is_array($extra_filters)) {
                foreach ($extra_filters as $extra_filter) {
                    if ($extra_filter instanceof DBFilter)
                        $dbQuery->filter($extra_filter);
                }
            }
        }

        if (method_exists($this, $method = self::METHOD_GET_ENTITY_CACHE_KEY_BY_STRING))
            $dbQuery->cache($this->$method($slug), $cache_time);

        return $dbQuery->get_entity_or_404($request);
    }

    /**
     * @param $results
     * @param null $field
     */
    public function index($results, $field = null)
    {
        if ($field === null)
            $field = $this->getPkField();

        if ($results)
            $results = array_index($results, $field);

        return $results;
    }

    /**
     * This is reimplemented in each manager - and you want custom handling!
     *
     * @param $data
     * @param Request $request
     */
    public function processVFields(DBManagerEntity $data, Request $request)
    {
        return $data;
    }


    /**
     * @param $data
     * @param Request $request
     * @param array $foreignManagers
     * @return DBManagerEntity
     * @throws BaseManagerEntityException
     */
    public function createEntity($data, Request $request, $foreignManagers = [], $entityClass = null)
    {
        if (!$entityClass)
            $entityClass = $this->getEntityClass();

        if (class_exists($entityClass))
            return new $entityClass($data, $request, get_called_class(), $foreignManagers);
        else
            throw new BaseManagerEntityException('Manager Class: '.get_called_class().' with defined Entity Class: '.$entityClass .' not found' );
    }

    /**
     * @param Request $request
     * @return DBManagerEntity
     * @throws BaseManagerEntityException
     */
    public function createNulledEntity(Request $request)
    {
        $data = [
            $this->getPkField() => 0
        ];

        foreach ($this->getDBFields(true) as $field) {
            $data[$field] = null;
        }

        return $this->createEntity($data, $request);
    }

    /**
     * @param Request $request
     * @param DBManagerEntity $entity
     */
    public function deactivateEntity(Request $request, DBManagerEntity $entity)
    {
        $deletedData = [
            DBField::DELETED_BY => $request->requestId,
            DBField::MODIFIED_BY => $request->requestId,
            DBField::IS_ACTIVE => 0
        ];

        $entity->assign($deletedData)->saveEntityToDb($request);
    }

    /**
     * @param Request $request
     * @param DBManagerEntity $entity
     */
    public function reActivateEntity(Request $request, DBManagerEntity $entity)
    {
        $updatedData = [
            DBField::IS_ACTIVE => 1,
            DBField::DELETED_BY => null
        ];

        $entity->assign($updatedData)->saveEntityToDb($request);
    }


    /**
     * @param DBManagerEntity[]|DBManagerEntity $results
     */
    protected function preProcessResultAsResultArray($results)
    {
        if ($results instanceof DBManagerEntity)
            $results = [$results];

        $results = array_index($results, $this->getPkField());

        return $results;
    }

    /**
     * @param DBManagerEntity $entity
     * @return array
     */
    public function getUpdatedDbFieldsFromEntity(DBManagerEntity $entity)
    {
        $managerSpecificUpdatedData = [];

        foreach ($entity->getUpdatedData() as $field => $value) {
            if (in_array($field, static::$fields))
                $managerSpecificUpdatedData[$field] = $value;
        }

        return $managerSpecificUpdatedData;
    }

}


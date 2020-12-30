<?php
/**
 * Query
 * builds queries using functions
 *
 * @package managers
 */

class DBFilter {

    protected $field;
    protected $value;

    public function __construct($field, $value = null)
    {
        $this->field = $field;
        $this->value = $value;
    }
}
class DBError extends Exception {}

abstract class Query
{
    /**
    * Registered backends are stored here
    * so that we know which files have been loaded
    */
    private static $backends = [];

    protected $entities = [];
    protected $table;
    protected $entity = null;
    protected $local_cache;
    protected $results_cache_key = null;
    protected $results_cache_duration = null;
    protected $results_cache_set_empty = false;
    protected $connection     = null;

    /** @var $manager BaseEntityManager  */
    protected $manager        = null;
    protected $mapper_manager = null;
    protected $fields         = [];
    protected $where          = null;
    protected $offset         = 0;
    protected $perpage        = 0;
    protected $groupby        = null;
    protected $index          = null;
    protected $clean          = false;
    protected $orderby        = [];
    protected $extra_tables   = [];
    protected $extra_fields   = [];
    protected $virtual_fields = [];
    protected $foreign_keys   = [];

    /** @var Manager[]  */
    protected $foreign_managers = [];

    /** @var DB $db  */
    public $db             = null;

    public function set_db($db)
    {
        $this->db = $db;
        return $this;
    }

    public function set_connection(DBBackend $conn)
    {
        $this->connection = $conn;
        return $this;
    }

    /**
     * @param $key
     * @param $timeout
     * @param bool $set_empty
     * @return $this
     */
    public function cache($key, $timeout, $set_empty = true)
    {
        if ($timeout > DONT_CACHE) {
            $this->results_cache_key = $key;
            $this->results_cache_duration = $timeout;
            $this->results_cache_set_empty = $set_empty;
        }
        return $this;
    }

    /**
     * @param $key
     * @param $timeout
     * @return SQLQuery
     */
    public function local_cache($key, $timeout, $set_empty = true)
    {
        $this->local_cache = true;
        return $this->cache($key, $timeout, $set_empty);
    }

    /**
    * Registers backend (when loading backend file)
    *
    * @param string backend name
    * @param string class name
    */
    public static function register_backend($name, $klass)
    {
        self::$backends[$name] = $klass;
    }

    /**
     * Factory
     *
     * @param database to use
     * @param backend to use
     * @return Query
     */
    public static function start($backend_name = null)
    {
        if ($backend_name === null) {
            global $CONFIG;
            $backend_name = $CONFIG['query_backend'];
        }

        require_once "backends/${backend_name}/query.php";
        require_once "backends/${backend_name}/fields.php";
        require_once "backends/${backend_name}/filters.php";
        if (!array_key_exists($backend_name, self::$backends))
            throw new DBError("Query backend not registered: ".$backend_name);

        return new self::$backends[$backend_name]();
    }

    public function manager($manager)
    {
        $this->manager = $manager;
        return $this;
    }

    /**
     * Set table to fetch results from
     *
     * @param $table String
     * @retSQLQueryuery
     */
    public function table($table)
    {
        $this->table = $table;
        return $this;
    }

    /**
     * Force index to use
     *
     * @param table table to fetch results from
     * @return $this
     */
    public function index($index)
    {
        $this->index = func_get_args();
        return $this;
    }

    /**
     * Order results by field
     *
     * @param int max number of results fetched
     * @return SQLQuery
     */
    public function order_by($field, $table = null)
    {
        //default_to($table, $this->table);
        $this->orderby[] = [$table, $field];
        return $this;
    }

    /**
     * @param $field
     * @param null $table
     * @return SQLQuery
     */
    public function sort_desc($field, $table = null, $index = null)
    {
        if ($index)
            $this->index($index);

        if ($field instanceof DBField) {
            if ($field->has_table())
                $table = $field->getTable();

            $field = $field->getField();
        }

        return $this->order_by('-'.$field, $table);
    }

    /**
     * @param $field
     * @param null $table
     * @return SQLQuery
     */
    public function sort_asc($field, $table = null)
    {
        if ($field instanceof DBField) {
            if ($field->has_table())
                $table = $field->getTable();

            $field = $field->getField();
        }

        return $this->order_by($field, $table);
    }

    /**
     * Group results by field
     *
     * @param int max number of results fetched
     * @return SQLQuery
     */
    public function group_by($field, $table = null)
    {
        default_to($table, $this->table);

        if (is_int($field)) {
            $table = null;
        }


        if ($field instanceof DBField) {
            if ($field->has_table())
                $table = $field->getTable();

            $field = $field->getField();
        }

        $this->groupby[] = [$table, $field];
        return $this;
    }

    /**
     * Converts $where to a DBFilter object
     * Accepts:
     * - a string or integer which will be considered the primary key value
     * - a DBFilter object
     * - an array of DBFilters
     *
     * @param mixed $where
     * @return DBFilter
     */
    protected function clean_where($where)
    {
        if (is_array($where)) {
            if (count($where) > 0) {
                $el = array_pop($where);
                $where = Q::And_(
                    $this->clean_where($el),
                    $this->clean_where($where)
                );
            }
            else {
                $where = null;
            }
        } elseif (is_string($where) || is_int($where)) {
            $where = $this->get_pk_filter($where);
        } elseif ($where instanceof DBFilter) {
            $where = $where;
        } else {
            $where = null;
        }

        return $where;
    }

    /**
     * Converts $where to a DBFilter object
     * Accepts:
     * - a string or integer which will be considered the primary key value
     * - a DBFilter object
     * - an array of DBFilters
     *
     * @param mixed $where
     * @return DBFilter
     */
    protected function clean_join_where($where, $source_table, $target_table)
    {
        if (is_array($where)) {
            if (count($where) > 0) {
                $el = array_pop($where);
                $where = Q::And_(
                    $this->clean_join_where($el, $source_table, $target_table),
                    $this->clean_join_where($where, $source_table, $target_table)
                );
            }
            else {
                $where = null;
            }
        } elseif (is_string($where) || is_int($where)) {
            $where = $this->get_join_pk_filter($where);
        } elseif ($where instanceof DBFilter) {
            $where = $where;
        } else {
            $where = null;
        }

        return $where;
    }

    /**
     * Filter results
     * Accepts:
     * - a string which will be considered the primary key value
     * - a DBFilter object
     * - an array of DBFilters
     *
     * @param DBFilter $where filters
     * @return SqlQuery
     */
    public function filter($where = null)
    {
        // Clean
        $where = $this->clean_where($where);

        // Combine
        if ($this->where !== null)
            $this->where = Q::And_($this->where, $where);
        else
            $this->where = $where;

        return $this;
    }

    /**
     * @param $id
     * @return SqlQuery
     */
    public function byPk($id)
    {
        return $this->filter($this->manager->filters->byPk($id))->limit(1);
    }

    /**
     * @param $slug
     * @return SqlQuery
     */
    public function bySlug($slug)
    {
        return $this->filter($this->manager->filters->bySlug($slug))->limit(1);
    }

    /**
     * @return $this
     */
    public function flush()
    {
        $this->where = null;
        $this->perpage = 0;
        $this->local_cache = false;
        $this->results_cache_duration = null;
        $this->results_cache_key = null;
        $this->orderby = [];
        $this->index = null;
        $this->fields = [];
        //$this->clean = null;
        $this->extra_tables = [];
        $this->extra_fields = [];
        //$this->manager = null;
        $this->offset = 0;
        $this->groupby = null;
        $this->virtual_fields = [];
        $this->foreign_keys = [];
        $this->entities = [];
        return $this;
    }

    /**
     * Paging
     *
     * @param int page
     * @param int per page
     * @return $this
     */
    public function paging($page = 1, $perpage = DEFAULT_PERPAGE)
    {
        $this->offset = ($page - 1) * $perpage;
        $this->perpage = $perpage;
        return $this;
    }

    /**
     * Set the number of records to skip
     *
     * @param int max number of results fetched
     * @return Query
     */
    public function offset($offset)
    {
        $this->offset = $offset;
        return $this;
    }

    /**
     * Limit number of rows fetched
     *
     * @param int max number of results fetched
     * @return SQLQuery
     */
    public function limit($perPage = SELECT_DEFAULT_LIMIT)
    {
        $this->perpage = $perPage;
        return $this;
    }

    /**
     * @param $fields
     * @return $this
     */
    public function fields($fields)
    {
        if (!is_array($fields))
            $fields = func_get_args();

        if ($fields)
            $this->fields = array_merge($this->fields, $fields);
        return $this;
    }

    /**
     * @param BaseEntityManager $mapper_manager
     * @return $this
     */
    public function mapper(BaseEntityManager $mapper_manager, $fields = null)
    {
        $this->mapper_manager = $mapper_manager;

        if (!$fields)
            $this->fields($mapper_manager->createDBFields());
        else
            $this->fields($fields);

        return $this;
    }

    /**
     * @param string $entity
     * @return $this
     */
    public function entity($entity)
    {
        $this->entity = $entity;
        return $this;
    }

    /**
     * @return null|string
     */
    public function getEntityClass()
    {
        return $this->entity;
    }

    public function virtual_fields($fields)
    {
        if (!is_array($fields))
            $fields = func_get_args();
        $this->virtual_fields = array_merge($this->virtual_fields, $fields);
        return $this;
    }

    /**
     * Updates database rows with specified values
     *
     * @param array values
     * @param bool escape data
     * @return int last updated id
     */
    public function update($data)
    {
        $sqli = $this->get_connection();

        if (method_exists($this->manager, 'convert_data'))
            $this->manager->convert_data($data);
        if (method_exists($this->manager, 'pre_update'))
            $this->manager->pre_update($data);

        $updates = $sqli->quote_array($data);

        $where_clause = $this->where_clause($sqli, $this->where);
        $order_clause = $this->order_clause($sqli, $this->orderby);
        $limit_clause = $this->limit_clause($this->offset, $this->perpage);

        $r = $sqli->query_write(
            'UPDATE '.$sqli->quote_field($this->table)."\n".
            'SET '.join(',', $updates)."\n"
            .$where_clause
            .$order_clause
            .$limit_clause
        );
        return $r->lastid();
    }

    /**
     * @return int
     */
    public function soft_delete(DBManagerEntity $entity = null)
    {
        if ($entity)
            return $this->update($entity->getUpdatedDbFieldData());

        if ($this->manager->hasField(DBField::IS_DELETED))
            $deletedField = DBField::IS_DELETED;
        else
            $deletedField = DBField::IS_ACTIVE;

        return $this->update([$deletedField => 1]);
    }

    /**
     * @return int
     */
    public function deactivate()
    {
        return $this->update([DBField::IS_ACTIVE => 0]);
    }

    /**
     * Deletes database rows
     *
     * @return bool
     */
    public function delete()
    {
        if (method_exists($this->manager, 'pre_delete'))
            $this->manager->pre_delete($data);

        $r = $this->_delete();

        if (method_exists($this->manager, 'post_delete'))
            $this->manager->post_delete($data);

        return $r;
    }

    /**
     * Increment field by x
     *
     * @return bool
     */
    public function increment($field, $amount = 1)
    {
        if (!Validators::int($amount))
            throw new DBError("increment: $amount is not an int");

        $sqli = $this->get_connection();
        $escaped_field = $sqli->quote_field($field);

        $where_clause = $this->where_clause($sqli, $this->where);
        $order_clause = $this->order_clause($sqli, $this->orderby);
        $limit_clause = $this->limit_clause($this->offset, $this->perpage);

        return $sqli->query_write(
            'UPDATE '.$sqli->quote_field($this->table)."\n".
            'SET '.$escaped_field.' = '.$escaped_field.' + '.$amount."\n"
            .$where_clause
            .$order_clause
            .$limit_clause
        );
    }

    /**
     * Increment field by x
     *
     * @return bool
     */
    public function decrement($field, $amount = 1)
    {
        if (!Validators::int($amount))
            throw new DBError("decrement: $amount is not an int");

        $sqli = $this->get_connection();
        $escaped_field = $sqli->quote_field($field);

        $where_clause = $this->where_clause($sqli, $this->where);
        $order_clause = $this->order_clause($sqli, $this->orderby);
        $limit_clause = $this->limit_clause($this->offset, $this->perpage);

        return $sqli->query_write(
            'UPDATE '.$sqli->quote_field($this->table)."\n".
            'SET '.$escaped_field.' = '.$escaped_field.' - '.$amount."\n"
            .$where_clause
            .$order_clause
            .$limit_clause
        );
    }

    /**
     * Inserts a row into the database
     *
     * @param array values
     * @return int created row id
     */
    public function add($data, $on_duplicate = null, $ignore = false)
    {
        if (method_exists($this->manager, 'convert_data'))
            $this->manager->convert_data($data);
        if (method_exists($this->manager, 'pre_add'))
            $this->manager->pre_add($data);

        $sqli = $this->get_connection();

        $field_names  = join(',', $sqli->quote_fields(array_keys($data)));
        $field_values = join(',', $sqli->quote_values(array_values($data)));

        $r = $sqli->query_write(
            'INSERT'.($ignore ? ' IGNORE' : '').' INTO '.$sqli->quote_field($this->table)
            .' ('.$field_names.') VALUES ('.$field_values.')'
            .($on_duplicate ? ' ON DUPLICATE KEY UPDATE '.$on_duplicate : '')
        );

        if (method_exists($this->manager, 'post_add'))
            $this->manager->post_add($data);

        return $r->lastid();
    }

    /**
     * @param array[] $data
     * @param null $on_duplicate
     * @param bool $ignore
     * @return mixed
     */
    public function add_multiple(array $data, $on_duplicate = null, $ignore = false)
    {
        if (method_exists($this->manager, 'convert_data'))
            $this->manager->convert_data($data);
        if (method_exists($this->manager, 'pre_add'))
            $this->manager->pre_add($data);

        $sqli = $this->get_connection();

        $fieldNames = array_keys($data[0]);

        $values = [];
        for ($i = 0; $i < count($data); $i++) {
            $values[] = '('.join(',', $sqli->quote_values(array_values($data[$i]))).')';
        }

        $fieldNames = join(',', $sqli->quote_fields($fieldNames));

        $field_values = join(',', $values);

        $r = $sqli->query_write(
            'INSERT'.($ignore ? ' IGNORE' : '').' INTO '.$sqli->quote_field($this->table)
            .' ('.$fieldNames.') VALUES '.$field_values.''
            .($on_duplicate ? ' ON DUPLICATE KEY UPDATE '.$on_duplicate : '')
        );

        if (method_exists($this->manager, 'post_add'))
            $this->manager->post_add($data);

        return $r->lastid();
    }

    /*
     * @param $data
     * @param Request $request
     * @return DBManagerEntity
     * @throws BaseManagerEntityException
     * @throws Exception
     */
    public function createNewEntity(Request $request, $data, $fetch = true)
    {
        // Manager Directing this Query
        $manager = $this->getManager();

        if ($manager->hasField(DBField::CREATOR_ID))
            $data[DBField::CREATOR_ID] = $request->user->id;

        if ($manager->hasField(DBField::CREATE_TIME)) {
            $dateTime = new DateTime();
            $data[DBField::CREATE_TIME] = $dateTime->format(SQL_DATETIME);
        }
        if ($manager->hasField(DBField::CREATED_BY)) {
            $data[DBField::CREATED_BY] = $request->requestId;
        }

        $addedData = [];

        foreach ($data as $key => $value) {
            if (in_array($key, $manager::$fields))
                $addedData[$key] = $value;
        }

        if ($manager->hasPkField()) {

            $pk = $this->add($addedData);

            if ($fetch) {
                return $manager->getEntityByPk($pk, $request);
            } else {
                $data[$manager->getPkField()] = $pk;
            }

        } else
            $this->add($addedData);

        return $manager->createEntity($data, $request, $this->foreign_managers, $this->entity);
    }

    /**
     * Random row
     *
     * @return array row
     */
    public function random($fields = null)
    {
        if (!Validators::id($this->perpage))
            throw new DBError("random: perpage is not an int: ".$this->perpage);

        if ($fields !== null && !is_array($fields))
            $fields = func_get_args();
        elseif ($fields === null)
            $fields = $this->manager->getDBFields();

        $foreign_keys = $this->foreign_keys;
        $this->foreign_keys = [];
        $total_rows = $this->count();
        $this->foreign_keys = $foreign_keys;

        $offsets = [];
        for ($i = 0; $i < $this->perpage; $i++) {
            $offset = rand(1, $total_rows - 1);
            if (array_key_exists($offset, $offsets))
                $offsets[$offset]++;
            else
                $offsets[$offset] = 1;
        }

        $rows = [];
        foreach ($offsets as $o => $limit) {
            $this->offset = $o;
            $this->limit = $limit;
            $rows = array_merge($rows, $this->get_list($fields));
        }

        return $rows;
    }

    /**
     * Count rows
     *
     * @return int Total Rows
     */
    public function count($field = '*', $distinct = false)
    {
        return $this->get_int(new CountDBField('count', $field, $this->manager->getTable(), $distinct));
    }

    /**
     * @param $field
     * @return int|float|mixed
     */
    public function sum($field, $table = null)
    {
        try {
            $table = $table ? $table : $this->manager->getTable();

            $result = $this->get(new SumDBField('sum', $field, $table));
            $sum = $result['sum'] ?? 0;
        } catch (ObjectNotFound $e) {
            $sum = 0;
        }
        return $sum;
    }

    /**
     * @param $field
     * @return mixed|null
     */
    public function max($field)
    {
        try {
            $result = $this->get(new MaxDBField('max', $field, $this->manager->getTable()));
            $max = $result['max'] ?? null;
        } catch (ObjectNotFound $e) {
            $max = null;
        }

        return $max;
    }

    /**
     * Replaces a row into the database
     *
     * @param array values
     * @return int Insert id
     */
    public function replace($data)
    {
        $sqli = $this->get_connection();

        if (method_exists($this->manager, 'convert_data'))
            $this->manager->convert_data($data);

        $field_names = join(',', $sqli->quote_fields(array_keys($data)));
        $field_values = join(',', $sqli->quote_values(array_values($data)));

        $r = $sqli->query_write('
            REPLACE INTO '.$sqli->quote_field($this->table).' ('.$field_names.')
            VALUES ('.$field_values.')
        ');

        return $r->get_affected_rows();
    }

    /**
     * Selects database row with specified id
     *
     * @param string $where
     * @return bool
     */
    public function exists()
    {
        try {
            $this->get(new RawDBField('1'));
            $exists = true;
        } catch (ObjectNotFound $e) {
            $exists = false;
        }

        return $exists;
    }


    /**
     * Selects rows from the database
     *
     * @param array fields to select
     * @param bool clean results
     * @return array
     */
    public function get_list($fields = null)
    {
        // The var $fields is not stored in $this because you don't expect
        // get_list() to modify the query object.
        if ($fields !== null && !is_array($fields))
            $fields = func_get_args();
        elseif ($fields === null && !$this->fields) {
            $fields = $this->manager->getDBFields();
            //echo 'hi - '.get_class($this->manager);
        }

        if ($this->fields && $fields)
            $fields = array_merge($fields, $this->fields);
        elseif ($this->fields && !$fields)
            $fields = $this->fields;

        $objects = $this->_get_objects($fields);

        // Fetch foreign key info
        foreach ($this->foreign_keys as $fk_prefix => $fk_fields) {
            $this->manager->fkQuery($this->db, $fk_prefix)
                ->fields($fk_fields[0])
                ->virtual_fields($fk_fields[1])
                ->merge_foreign_key($objects, $fk_prefix);
        }

        if ($this->virtual_fields && method_exists($this->manager, 'clean_data')) {
            foreach ($objects as &$obj)
                $this->manager->clean_data($obj, $this->virtual_fields);
            unset($obj);
        }

        return $objects;
    }


    /**
     * Fetch object from the database
     *
     * @param array $fields fields to select
     * @param bool clean results
     * @return array
     */
    public function get($fields = null)
    {
        if ($fields !== null && !is_array($fields))
            $fields = func_get_args();

        $results = $this->limit(1)->get_list($fields);

        if (!$results)
            throw new ObjectNotfound($this->table);

        return $results[0];
    }

    /**
     * Wrapper around get()
     * to get a signle value
     *
     * @param string DBField to fetch
     * @return string
     */
    public function get_value($field)
    {
        $row = $this->get($field);
        return array_pop($row);
    }

    /**
     * @param $field
     * @return array
     */
    public function get_values($field, $distinct = false, Request $request = null)
    {
        if (is_string($field))
            $dbfield = $this->getManager()->field($field);
        else
            $dbfield = $field;

        if ($request instanceof Request) {
            $rows = $this->get_objects($request, $dbfield);
        } else {
            $rows = $this->get_list($dbfield);
        }

        if ($rows) {
            $results = array_extract($field, $rows);
        } else
            $results = [];

        if (!$results)
            return $results;

        return $distinct ? array_unique($results) : $results;
    }

    /**
     * Wrapper around get()
     * to get a signle boolean value
     *
     * @param string DBField to fetch
     * @return string
     */
    public function get_bool($field)
    {
        $row = $this->get($field);
        return array_pop($row) == '1';
    }

    /**
     * Wrapper around get()
     * to get a signle integer value
     *
     * @param string DBField to fetch
     * @return string
     */
    public function get_int($field)
    {
        $row = $this->get($field);

        if ($field instanceof DBField)
            $fieldName  = $field->getField();
        else
            $fieldName = $field;

        $count = isset($row[$fieldName]) && intval($row[$fieldName]) ? $row[$fieldName] : 0;
        return $count;
    }

    /**
     * @return int
     */
    public function get_pk()
    {
        return $this->get_int($this->manager->getPkField());
    }

    /**
     * Wrapper around get()
     * throws a 404 if the object is not found
     *
     * @param string $where where clause
     * @param array $fields fields to select
     * @return array
     */
    public function get_or_404($fields = null, $clean = true)
    {
        if ($fields !== null && !is_array($fields))
            $fields = func_get_args();

        try {
            $object = $this->get($fields, $clean);
        } catch(ObjectNotFound $e) {
            throw new Http404(get_class($e).'-'.$this->getManager()->getName());
        }
        return $object;
    }

    /**
     * Selects rows from the database and
     * return them in a format compatible
     * with SelectField like options
     *
     * @param string pk field
     * @param string descriptive field
     * @return array
     */
    public function get_options($id_field, $name_field)
    {
        $objects = $this->get_list($id_field, $name_field);
        $options = [];
        foreach ($objects as $o)
            $options[] = [$o[$id_field], $o[$name_field]];
        return $options;
    }

    /**
     * Fetch foreign key data and add retreived to the data array
     * This is used instead of joins to palliate mysql scheduler shortcomings
     */
    public function fetch_foreign_key(&$data, $prefix, $id_field = 'id')
    {
        $id_field = $prefix ? $prefix.'_'.$id_field : $id_field;
        $pk_field = $this->manager->getPkField();
        $ids = array_unique(array_column($data, $id_field));
        return $this->filter(Q::In($pk_field, $ids))
                    ->get_list($pk_field);
    }

    /**
     * Add foreign key data to $objects array
     * This is used instead of joins to palliate mysql scheduler shortcomings
     */
    public function merge_foreign_key(&$data, $prefix, $id_field = DBField::ID)
    {
        $new_data = $this->fetch_foreign_key($data, $prefix, $id_field);
        $indexed_data = array_index($new_data, $id_field);

        foreach ($data as $key => &$row) {
            $fk_id = $row[$id_field];
            if (!array_key_exists($fk_id, $indexed_data)) {
                unset($data[$key]);
                continue;
            }
            $fk_row = $indexed_data[$fk_id];
            foreach ($this->fields as $field)
                $row[$prefix.'_'.$field] = $fk_row[$field];
            foreach ($this->virtual_fields as $field)
                $row[$prefix.'_'.$field] = $fk_row[$field];
        }
    }

    /**
     * Add related data to be fetched when querying
     */
    public function foreign_key($prefix, $fields, $virtual_fields = [])
    {
        $this->foreign_keys[$prefix] = [$fields, $virtual_fields];
        return $this;
    }

    /**
     * @return MySQLBackend
     */
    abstract protected function get_connection();

    /**
     * @return BaseEntityManager|Manager
     */
    protected function getManager()
    {
        return $this->manager;
    }

    /**
     * @param Request $request
     * @param null $fields
     * @param bool|true $clean
     * @return array
     */
    public function get_objects(Request $request, $fields = null, $clean = true)
    {
        if ($this->shouldCache()) {
            try {
                $cache = $this->local_cache ? $request->local_cache : $request->cache;
                $objects = $cache[$this->results_cache_key];

            } catch (CacheEntryNotFound $c) {
                $objects = $this->get_list($fields);
                $c->set($objects, $this->results_cache_duration);
            }
        } else
            $objects = $this->get_list($fields);

        return $objects;
    }

    /**
     * @return BaseEntityManager|Manager|null
     */
    protected function getMapper()
    {
        if (!$this->mapper_manager instanceof BaseEntityManager)
            return $this->getManager();
        else
            return $this->mapper_manager;
    }

    /**
     * @param Request $request
     * @param null $fields
     * @param bool|true $clean
     */
    public function get_entities(Request $request, $fields = null, $clean = true)
    {
        $objects = $this->get_objects($request, $fields);

        $entities = [];

        foreach ($objects as $object)
            $entities[] = $this->getMapper()->createEntity($object, $request, $this->foreign_managers, $this->entity);

        return $entities;
    }


    /**
     * @param Request $request
     * @param null $fields
     * @param bool|true $clean
     * @return DBDataEntity[]
     */
    public function get_json_entities(Request $request, $fields = null, $clean = true)
    {
        return DBDataEntity::extractJsonDataArrays($this->get_entities($request, $fields, $clean));
    }

    /**
     * @return bool
     */
    protected function shouldCache()
    {
        return !is_null($this->results_cache_key) && !is_null($this->results_cache_duration);
    }

    /**
     * @param Request $request
     * @param null $fields
     * @param bool|true $clean
     * @throws ObjectNotFound
     */
    public function get_entity(Request $request, $handle_fail = true, $fields = null, $clean = true)
    {
        $entity = [];
        try {
            if (!is_null($this->results_cache_key) && is_numeric($this->results_cache_duration) && $this->results_cache_duration > 0) {
                try {
                    // Try to get the raw data for the entity from cache.
                    $cache = $this->local_cache ? $request->local_cache : $request->cache;
                    $object = $cache[$this->results_cache_key];
                } catch (CacheEntryNotFound $c) {
                    // If we didn't have a cache entry for this entity, we have to get from database.
                    $object = $this->get($fields, $clean);
                    // Set cache for this result.
                    $c->set($object, $this->results_cache_duration);

                    // For single entity, we check if we need to pre-warm this cache by slug/username also.
                    //$this->handleSetEntityCacheByStringForManager($request, $object);
                }

            } else {
                // Else fetch directly from database.
                $object = $this->get($fields, $clean);
            }
            $entity = $this->getMapper()->createEntity($object, $request, $this->foreign_managers, $this->entity);
        } catch (ObjectNotFound $e) {
            if (!$handle_fail)
                throw ($e);
        }

        return $entity;
    }


    /**
     * Wrapper around get_entity()
     * throws a 404 if the object is not found
     *
     */
    public function get_entity_or_404(Request $request)
    {
        try {
            $entity = $this->get_entity($request, false);
        } catch (ObjectNotFound $e) {
            throw new Http404("{$this->getManager()->getName()} Not Found");
        }
        return $entity;
    }


    /**
     * @param Request $request
     * @param $object
     */
    protected function handleSetEntityCacheByStringForManager(Request $request, $object)
    {
        if (method_exists($this->getManager(), $getEntityCacheKeyByString = BaseEntityManager::METHOD_GET_ENTITY_CACHE_KEY_BY_STRING)) {
            $cache_key = $this->getManager()->$getEntityCacheKeyByString($object);
            if ($cache_key != $this->results_cache_key)
                $request->cache->set($cache_key, $object);
        }
    }

}

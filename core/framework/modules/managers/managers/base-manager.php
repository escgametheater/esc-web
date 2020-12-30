<?php

/**
 * Base Abstract Managers Classes
 * - Includes Manager (array object results) and BaseEntityManager (entity results)
 *
 * Class Manager
 */

abstract class Manager
{

    protected $table_alias = '';

    const METHOD_GET_DB_FIELDS = 'getDBFields';
    const METHOD_CLEAN_DATA = 'clean_data';

    /**
     * Model Name
     */
    protected $name;

    protected $backend = 'sql';

    /**
     * DB connection
     */
    protected $conn = null;

    /**
     * SQL Table
     */
    protected $table;

    /**
     * PK Field
     */
    protected $pk = DBField::ID;

    /**
     * URL root to access news
     */
    protected $root = HOMEPAGE;

    /**
     * Section -- overridden in other manager -- defines their first URL section key - defaults to Homepage
     */
    protected $section = HOMEPAGE;

    /**
     * @var array|null
     */
    public static $fields = [];

    /**
     * Access rights required for the admin and edit
     */
    protected static $right_required = Rights::ADMINISTER;

    /**
     * Field storing the author id for the object
     */
    protected $author_field = null;

    /**
     * Field storing the foreign keys you can fetch
     * @see Query::foreign_key()
     */
    protected $foreign_keys = [];

    /**
     * Exception to throw when object was not found
     */
    protected $exception = null;

    /**
     * Class instance created only once
     * We need to create an instance of this class
     * because PHP doesn't support late binding as of 5.2
     *
     * @var BaseEntityManager[]
     */
    protected static $inst = [];

    /**********
     * Accessors
     ***********/


    /**
     * @param bool|true $lowercase
     * @return string
     */
    public function getName($snake_case = false)
    {
        if (!$this->name) {
            $this->name = get_called_class();
        }

        return $snake_case ? camel_to_snake_case($this->name) : $this->name;
    }

    /**
     * @return string
     */
    public function getClass()
    {
        return get_called_class();
    }

    /**
     * $root accessor
     *
     * @return string
     */
    public function getRoot()
    {
        return $this->root;
    }

    /**
     * $section accessor
     *
     * @return string
     */
    public function getSection()
    {
        return $this->section;
    }

    /**
     * Defaults to DESCENDING
     *
     * @param $field
     * @param bool|true $desc
     * @return string
     */
    public function format_sort_field($field, $desc = false)
    {
        return $desc ? '-'.$field : $field;
    }

    /**
     * @param $field
     * @return string
     */
    public function format_sort_desc($field)
    {
        return $this->format_sort_field($field, true);
    }

    /**
     * $exception accessor
     *
     * @return string
     */
    public function getException()
    {
        return $this->exception;
    }

    /**
     * Get default fields for get/get_list
     *
     * @return array
     */
    public function getDBFields($removePk = false)
    {
        $fields = static::$fields;

        if ($removePk && in_array($this->getPkField(), $fields)) {
            foreach ($fields as $key => $field) {
                if ($field == $this->getPkField())
                    unset($fields[$key]);
            }
        }

        return $fields;
    }

    /**
     * @param bool|false $removePk
     * @return DBField[]
     */
    public function createDBFields($removePk = false, $field_alias_prefix = null) {

        $raw_fields = $this->getDBFields($removePk);

        $fields = [];

        foreach ($raw_fields as $field_name)
            $fields[] = $this->field($field_name, $field_alias_prefix);

        return $fields;
    }

    /**
     * @param bool|false $removePk
     * @return DBField[]
     */
    public function createJoinAliasedDBFields($removeFields = false)
    {
        if ($alias = $this->getTableAlias())
            $alias .= '_';
        return $this->createDBFields($removeFields, $alias);
    }

    /**
     * @param $field
     * @param null $field_alias_prefix
     * @return DBField|AliasedDBField
     */
    public function field($field, $field_alias_prefix = null)
    {
        return $field_alias_prefix
            ? new AliasedDBField($field, $field_alias_prefix.$field, $this->getTable())
            : new DBField($field, $this->getTable());
    }

    /**
     * @param $field
     * @param $alias
     * @return AliasedDBField
     */
    public function aliasField($field, $alias)
    {
        return new AliasedDBField($field, $alias, $this->getTable());
    }

    /**
     * @param null $field_alias
     * @return AliasedDBField|DBField
     */
    public function createPkField($field_alias = null)
    {
        return $this->field($this->getPkField(), $field_alias);
    }

    /**
     * @param $field_alias
     * @return AliasedDBField
     */
    public function createAliasedPkField($field_alias)
    {
        return $this->aliasField($this->getPkField(), $field_alias);
    }

    /**
     * @param $field
     * @param string $alias_prefix
     * @return RawDBField
     */
    public function createMaxField($field, $alias_prefix = 'max_of_')
    {
        return new RawDBField("MAX(`{$this->getTable()}`.`{$field}`) as ".$alias_prefix.$field);
    }


    /**
     * @param $field_name
     * @return bool
     */
    public function hasField($field_name)
    {
        return in_array($field_name, $this->getDBFields());
    }

    /**
     * Get primary key field
     *
     * @return string
     */
    public function getPkField()
    {
        return $this->pk;
    }

    /**
     * @return bool
     */
    public function hasPkField()
    {
        return $this->hasField($this->getPkField());
    }

    /**
     * @return string
     */
    public function getTable()
    {
        return $this->table;
    }

    /**
     * @param string $table_alias
     * @return Manager|BaseEntityManager
     */
    public function alias($table_alias = '')
    {
        if (!$table_alias)
            return $this;

        /** @var Manager|BaseEntityManager $clone */
        $clone = clone $this;
        $clone->setTableAlias($table_alias);
        return $clone;
    }

    /**
     * @return string
     */
    public function getTableAlias($default = false, $alias_override = null)
    {
        if ($alias_override)
            $this->table_alias = $alias_override;

        if (!$this->table_alias && $default)
            $this->table_alias = $this->getTable();

        return $this->table_alias;
    }

    /**
     * @param $fieldName
     * @return string
     */
    public function getAliasedFieldName($fieldName = '')
    {
        return $this->getTableAlias().'_'.$fieldName;
    }

    /**
     * @param $alias
     * @return mixed
     */
    public function setTableAlias($alias)
    {
        return $this->table_alias = $alias;
    }


    /**
     * Get object primary key
     *
     * @return string
     */
    public function getPk($object)
    {
        return $object[$this->getPkField()];
    }

    /**
     * Get a query for a foreign key
     *
     * @return SqlQuery
     */
    public function fkQuery($db, $name)
    {
        if (!array_key_exists($name, $this->foreign_keys))
            throw new DBException($this->entityClass.': invalid foreign key '.$name);
        /** @var Manager $class */
        $class = $this->foreign_keys[$name];
        return $class::objects($db);
    }

    /********
     * Rights
     *********/

    /**
     * True if the user has the right to edit the given object
     *
     * @param array $user User reqesting permissions
     * @param array $object Target object
     * @return boolean
     */
    protected function _can_edit(User $user, $object)
    {
        $is_author = ($this->author_field !== null) ? $user->id == (int)$object[$this->author_field] : false;
        return $is_author || $user->permissions->has($this->right_required, Rights::MODERATE);
    }

    /******************
     * Database Access
     *******************/

    /**
     * Gets multiple objects
     * @param int $page page #
     * @param int $perpage per page #
     * @param string $where where clause
     * @param string $orderby order by field
     * @param bool $orderby order by direction
     * @param array $fields fields to fetch
     * @return Query
     */

    public function _objects($db)
    {
        return Query::start($this->backend)
            ->set_db($db)
            ->manager($this)
            ->table($this->table);
    }
    /**
     * @return $this
     */
    public static function getInstance(DB $db = null)
    {
        $class = get_called_class();

        if (!array_key_exists($class, self::$inst))
            self::$inst[$class] = new $class();

        /** @var BaseEntityManager $manager */
        $manager = self::$inst[$class];

        if ($db) {
            $manager->setDataSource($db);

        }
        return $manager;
    }
    /**
     * @param null $db
     * @return SQLQuery
     */
    public static function objects($db = null)
    {
        $inst = self::getInstance();
        return $inst->_objects($db);
    }

}

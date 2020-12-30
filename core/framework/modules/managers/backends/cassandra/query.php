<?php
/**
 * SQL Generation Functions
 *
 * @version 1
 * @package esc
 * @subpackage sql
 */
Modules::uses('cassandra');
require "filters.php";

class CassandraQuery extends Query
{
    public function __construct($db)
    {
        global $CONFIG;
        $this->keyspace = $CONFIG['cassandra'][$db]['keyspace'];
    }

    public function get_connection()
    {
        // TODO: Implement get_connection() method.
    }

    /**********
    * Escapes
    **********/

    /**
     * Escapes an array of fields
     * (in case they contain special caracters or are sql keywords)
     *
     * @return string
     */
    public static function quote_fields($fields, $conn)
    {
        if (!$conn instanceof DBBackend)
            throw new Exception('Invalid DB Connection');

        if (!is_array($fields))
            $fields = [$fields];

        $escaped_fields = [];
        foreach ($fields as $field)
            $escaped_fields[] = '`'.$conn->escape_string($field).'`';

        return $escaped_fields;
    }

    /**
     * Escapes an array of values
     * (in case they contain special caracters or are sql keywords)
     *
     * @return string
     */
    public static function quote_values($values, $conn)
    {
        if (!$conn instanceof DBBackend)
            throw new Exception('Invalid DB Connection');

        if (!is_array($values))
            $fields = [$values];

        $escaped_fields = [];
        foreach ($values as $value)
            $escaped_fields[] = self::quote_value($value, $conn);
        return $escaped_fields;
    }

    /**
     * Escapes an array of data to prevent SQL Injections
     *
     * @return string
     */
    protected static function quote_array($data, $conn)
    {
        if (!$conn instanceof DBBackend)
            throw new Exception('Invalid DB Connection');

        $new_data = [];
        foreach ($data as $key => $value) {
            $field = self::quote_field($key, $conn);
            $value = self::quote_value($value, $conn);
            $new_data[] = "$field = $value";
        }
        return $new_data;
    }

    /**
     * Escapes a single field
     *
     * @return string
     */
    public static function quote_field($string, $conn)
    {
        if (!$conn instanceof DBBackend)
            throw new Exception('Invalid DB Connection');

        return '`'.$conn->escape_string($string).'`';
    }

    /**
     * Escapes a single value
     *
     * @return string
     */
    public static function quote_value($value, $conn)
    {
        if (!$conn instanceof DBBackend)
            throw new Exception('Invalid DB Connection');

        if ($value === null)
            $escaped_value = 'NULL';
        elseif (is_bool($value))
            $escaped_value = $value ? '1' : '0';
        elseif (is_int($value))
            $escaped_value = (string)$value;
        else
            $escaped_value = '"'.$conn->escape_string($value).'"';

        return $escaped_value;
    }

    /***************
    * SQL Generation
    ****************/
    /**
     * Makes select clause for SQL queries
     *
     * @return string
     */
    protected function select_clause($conn, $fields, $table)
    {
    }

    /**
     * Makes where clause for SQL queries
     *
     * @return string
     */
    protected function where_clause($conn, $where)
    {
    }

    /**
     * Makes limit clause for SQL queries
     *
     * @return string
     */
    protected function limit_clause($offset, $perpage)
    {
    }

    /**
     * Makes group by clause for SQL queries
     *
     * @return string
     */
    protected static function groupby_clause($conn, $data)
    {
    }

    protected function get_pk_filter($pk)
    {
        return new KeyFilter($pk);
    }

    /**
     * Actions
     */
    protected function _get($fields)
    {

        $o = new PandraColumnFamily();
        $o->setKeySpace($this->keyspace);
        $o->setName($this->table);
        $o->setAutoCreate(false);

        $key = $this->where->render();

        foreach ($fields as $f)
            $o->addColumn($f, 'string');

        if (!$o->load($key))
            throw new ObjectNotFound($o->lastError());

        return $o->toArray();
    }
}

Query::register_backend('cassandra', 'CassandraQuery');

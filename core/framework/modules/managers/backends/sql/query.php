<?php
/**
 * SQL Generation Functions
 *
 * @version 1
 * @package esc
 * @subpackage sql
 */
Modules::uses(Modules::DB);
Modules::uses(Modules::MANAGERS);

class SQLQuery extends Query
{
    const ON = 'ON';
    const IN = 'IN';
    const FROM = 'FROM';
    const SELECT = 'SELECT';
    const WHERE = 'WHERE';
    const JOIN_LEFT = 'LEFT';
    const JOIN_INNER = 'INNER';
    const JOIN_RIGHT = 'RIGHT';

    /***************
    * SQL Generation
    ****************/
    /**
     * Makes select clause for SQL queries
     *
     * @return string
     */
    /**
     * @param MySQLBackend $conn
     * @param $fields
     * @param $table
     * @return string
     * @throws Exception
     */
    protected function select_clause(MySQLBackend $conn, $fields, $table)
    {
        if ($fields == null) {
            $escaped_fields = '*';
        } else {
            $escaped_table = $conn->quote_field($table);
            $escaped_fields = [];
            foreach ($fields as $field) {
                if (is_string($field)) {
                    $escaped_fields[] = $escaped_table.'.' .$conn->quote_field($field);
                } elseif ($field instanceof DBField) {
                    $escaped_fields[] = $field->render($conn);
                } else {
                    throw new Exception("Invalid field: $field");
                }
            }
            $escaped_fields = join(', ', $escaped_fields);
        }
        return $escaped_fields."\n";
    }

    /**
     * Makes where clause for SQL queries
     *
     * @return string
     */
    protected function where_clause(MySQLBackend $conn, SQLFilter $where = null, $remove_values = false, $prefix = self::WHERE, $table = '')
    {
        return $where ? " $prefix ".$where->render($conn, $remove_values, $table) : "";
    }

    /**
     * Makes where clause for SQL queries
     *
     * @return string
     */
    protected function join_where_clause(MySQLBackend $conn, $where, $remove_values = false, $prefix = self::ON)
    {
        //$quoted_table = $quoted_table ? $quoted_table.'.' : '';

        return $where ? " $prefix ".$where->render($conn, $remove_values) : "";
    }


    /**
     * Makes order clause for SQL queries
     *
     * @return string
     */
    /**
     * @param SqlConnection $conn
     * @param $fields
     * @return string
     */
    protected function order_clause(MySQLBackend $conn, $fields)
    {
        $orders = [];
        if (is_array($fields)) {
            foreach($fields as $field) {
                if ($field[1] == '?') {
                    $orders[] = 'RAND()';
                } elseif ($field[1] instanceof DBField) {
                    $orders[] = $field[1]->render($conn);
                } else {
                    // Order
                    if ($field[1][0] == '-') {
                        $f = substr($field[1], 1);
                        $desc = true;
                    } else {
                        $f = $field[1];
                        $desc = false;
                    }

                    if ($field[0])
                        $left_side = $conn->quote_field($field[0]).'.';
                    else
                        $left_side = '';

                    $right_side = $conn->quote_field($f);

                    $orders[] = $left_side . $right_side .($desc ? ' DESC' : ' ASC');
                }
            }
        }

        if ($orders)
            $order_clause = ' ORDER BY '.join(', ', $orders)."\n";
        else
            $order_clause = '';

        return $order_clause;
    }

    /**
     * Makes limit clause for SQL queries
     *
     * @param $offset
     * @param $perPage
     * @param bool $remove_values
     * @return string
     */
    protected function limit_clause($offset, $perPage, $remove_values = false)
    {
        if (!$perPage || $perPage < 0) {
            $perPage = SELECT_DEFAULT_LIMIT ;
        }

        if ($remove_values) {
            $limit_clause = " LIMIT $perPage\n";
        } else if ($offset || $offset < 0) {
            $limit_clause = " LIMIT $offset, $perPage\n";
        } else {
            $limit_clause = " LIMIT $perPage\n";
        }
        return $limit_clause;
    }

    /**
     * Makes group by clause for SQL queries
     *
     * @return string
     */
    protected function groupby_clause(MySQLBackend $conn, $data)
    {
        if ($data !== null) {

            $groupby_clause = ' GROUP BY ';

            $fields = [];
            foreach ($data as $r) {
                $table = $r[0];
                $field = $r[1];

                $temp_groupby_clause = '';

                if ($table)
                    $temp_groupby_clause .= $conn->quote_field($table).'.';

                if (is_int($field))
                    $temp_groupby_clause .= $field;
                else
                    $temp_groupby_clause .= $conn->quote_field($field);

                $fields[] = $temp_groupby_clause;

            }

            $groupby_clause .= join(',', $fields);

        } else {
            $groupby_clause = '';
        }
        return $groupby_clause;
    }

    protected function join_clause(MySQLBackend $sqli, array $tables, $remove_values = false)
    {
        //echo 'join clause!';
        $extra_tables = [];
        foreach($tables as $table) {
            // Roughly $extra_tables = LEFT JOIN mytable ON <cond>
            $join_type = $table[2];
            $quoted_table = $sqli->quote_field($table[0]);
            $on_clause = $this->join_where_clause($sqli, $table[1], $remove_values, "ON", $quoted_table);
            $extra_tables[] = " $join_type JOIN $quoted_table $on_clause\r\n";
        }
        return join('', $extra_tables);
    }

    /**
     * @param $pk
     * @return EqFilter
     */
    protected function get_pk_filter($pk)
    {
        return $filter = Q::Eq($this->manager->getPkField(), $pk);
    }

    /**
     * @param $pk
     * @param $source_table
     * @param $target_table
     * @return EqFilter
     */
    protected function get_join_pk_filter($pk)
    {
        return $this->manager->filters->Eq($this->manager->createPkField(), $pk);
    }


    /**
     * Query template for app performance
     *
     * Basically the query but without the values so that we can group
     * multiple queries of the same type together
     */
    /**
     * @param MySQLBackend $sqli
     * @param array $fields
     * @param bool $remove_values
     * @return string
     * @throws Exception
     */
    function build_select_query(MySQLBackend $sqli, array $fields, $remove_values = false)
    {
        // Needed
        $select_clause = $this->select_clause($sqli, $fields, $this->table);
        $quoted_table = $sqli->quote_field($this->table);

        $where_clause = $this->where_clause($sqli, $this->where, $remove_values, "WHERE", $this->table);

        // The ifs are for speed

        if ($this->index)
            $index = "FORCE INDEX (".join(", ", $sqli->quote_fields($this->index)).")\n";
        else
            $index = "";

        if ($this->extra_tables)
            $join_clause = $this->join_clause($sqli, $this->extra_tables, $remove_values);
        else
            $join_clause = "";

        if ($this->groupby)
            $groupby_clause = $this->groupby_clause($sqli, $this->groupby);
        else
            $groupby_clause = "";

        if ($this->orderby)
            $order_clause = $this->order_clause($sqli, $this->orderby);
        else
            $order_clause = "";

        if ($this->perpage || $this->offset) {
            $limit_clause = $this->limit_clause($this->offset, $this->perpage, $remove_values);
        }
        else
            $limit_clause = "";

        $sql_query = "
            SELECT {$select_clause}
            FROM {$quoted_table} {$index}
            $join_clause
            $where_clause
            $groupby_clause
            $order_clause
            $limit_clause;
        ";

        return $sql_query;
    }

    /**
     * Fetch objects
     * @param array $fields
     * @return array
     * @throws Exception
     */
    protected function _get_objects($fields)
    {
        $sqli = $this->get_connection();
        $query = $this->build_select_query($sqli, $fields);
        $r = $sqli->query_read($query);
        return $r->fetch_all();
    }

    /**
     * Inject custom sql
     * used to have the data cleaned by the clean_data function
     *
     * @param string sql to execute
     * @return array
     */
    public function sql($sql, $clean = true)
    {
        $sqli = $this->get_connection();

        $objects = $sqli->query_read($sql)->fetch_all();

        if ($clean && method_exists($this->manager, $method = Manager::METHOD_CLEAN_DATA) && is_array($objects)) {
            foreach ($objects as &$obj)
                $this->manager->$method($obj, $this->virtual_fields);
        }

        return $objects;
    }

    /**
     * Run custom sql with cache support & automatic entity creation
     * used to have the data cleaned by the clean_data function
     *
     * @param string $sql
     * @param Request $request
     * @param bool $clean
     * @return DBManagerEntity[]
     */
    public function sql_entities(Request $request, $sql, $clean = true)
    {

        if ($this->shouldCache()) {
            try {
                $cache = $this->local_cache ? $request->local_cache : $request->cache;
                $objects = $cache[$this->results_cache_key];

            } catch (CacheEntryNotFound $c) {

                $objects = $this->sql($sql, $clean);
                $c->set($objects, $this->results_cache_duration);
            }
        } else {
            $objects = $this->sql($sql, $clean);
        }

        $entities = [];

        foreach ($objects as $object)
            $entities[] = $entity = $this->getManager()->createEntity($object, $request, $this->foreign_managers, $this->entity);

        return $entities;
    }

    /**
     * @param Request $request
     * @param $sql
     * @param bool|true $clean
     * @return array
     */
    public function sql_json_entities(Request $request, $sql, $clean = true)
    {
        $entities = $this->sql_entities($request, $sql, $clean);

        return DBDataEntity::extractJsonDataArrays($entities);
    }

    /**
     * @param Request $request
     * @param $sql
     * @param bool|true $clean
     * @return array|DBManagerEntity
     */
    public function sql_entity(Request $request, $sql, $clean = true)
    {
        $entities = $this->sql_entities($request, $sql, $clean);

        return isset($entities[0]) ? $entities[0] : [];
    }


    /**
     * Join Tables
     *
     * @param BaseEntityManager|string $table
     * @param $on
     * @param string $type
     * @param string $prefix
     * @param array $fields
     * @return $this
     */
    protected function _join($table, $on = null, $type = '')
    {
        $table_data = [$table, $this->clean_join_where($on, $this->table, $table), $type];

        $this->extra_tables[] = $table_data;

        return $this;
    }

    /**
     * @param BaseEntityManager $remoteManager
     * @param DBFilter $on_field
     * @return $this
     */
    public function left_join(BaseEntityManager $remoteManager, SQLFilter $on_field = null)
    {
        if (!$on_field)
            $on_field = $this->manager->filters->join($remoteManager);

        $this->foreign_managers[$remoteManager->getTableAlias()] = $remoteManager;
        return $this->_join($remoteManager->getTable(), $on_field, SQLQuery::JOIN_LEFT);
    }


    /**
     * @param BaseEntityManager $remoteManager
     * @param SQLFilter|null $on_field
     * @return $this
     */
    public function inner_join(BaseEntityManager $remoteManager, SQLFilter $on_field = null)
    {
        if (!$on_field)
            $on_field = $this->manager->filters->join($remoteManager);

        $this->foreign_managers[$remoteManager->getTableAlias()] = $remoteManager;
        return $this->_join($remoteManager->getTable(), $on_field, SQLQuery::JOIN_INNER);
    }

    /**
     * @param BaseEntityManager $remoteManager
     * @param DBFilter $on_field
     * @return $this
     */
    public function right_join(BaseEntityManager $remoteManager, SQLFilter $on_field = null)
    {
        if (!$on_field)
            $on_field = $this->manager->filters->join($remoteManager);

        $this->foreign_managers[$remoteManager->getTableAlias()] = $remoteManager;
        return $this->_join($remoteManager->getTable(), $on_field, SQLQuery::JOIN_RIGHT);
    }

    /**
     * Get a database connection object
     *
     * @return MySQLBackend|null
     */

    public function get_connection()
    {
        if ($this->connection)
            $conn = $this->connection;
        elseif ($this->db instanceof DB)
            $conn = $this->db->get_connection();
        else
            $conn = DB::inst();
        return $conn;
    }

    /**
     * Deletes database rows
     *
     * @return bool
     */
    public function _delete()
    {
        $sqli = $this->get_connection();
        return $sqli->query_write(
            'DELETE FROM '.$sqli->quote_field($this->table)
            .$this->where_clause($sqli, $this->where, false, SQLQuery::WHERE, $this->table)
            .$this->limit_clause($this->offset, $this->perpage)
        );
    }
}

Query::register_backend('sql', 'SQLQuery');

<?php
/**
 * SQL Result
 *
 * @version 1
 * @package esc
 * @subpackage sql
 */
class SQLResult
{
    /**
     * Last db connection used
     * (used to retreive the affected rows)
     *
     * @access private
     * @var boolean
     */
    private $conn;

    /**
     * Affected rows
     *
     * @access private
     * @var integer
     */
    private $affected_rows;

    /**
     * Parameters bound for the current query
     *
     */
    private $r_ref;

    /**
     * SQL query
     *
     * @var string
     */
    private $sql;

    /**
     * MySQL Result for the current query
     *
     */
    private $result = false;

    /**
     * @var MySQLBackend
     */
    protected $backend;

    /**
     * Constructor
     *
     */

    public function __construct($backend, $conn, $sql, $result)
    {
        $this->backend  = $backend;
        $this->conn     = $conn;
        $this->sql      = $sql;
        $this->result   = $result;
    }

    /**
     * Handle bind_result like mysqli
     * 9 parameters possible
     *
     * @param mixed $el1 reference
   * etc
     */
    public function bind_result(&$el1, &$el2 = NULL, &$el3 = NULL, &$el4 = NULL,
       &$el5 = NULL, &$el6 = NULL, &$el7 = NULL, &$el8 = NULL, &$el9 = NULL)
    {
        $sizefunc = func_num_args();
        $sizefields = $this->backend->count_fields($this->result);

        if ($sizefields != $sizefunc) {
            elog("DB fields count is different
            from number of arguments (${sizefields} <> ${sizefunc})");
        }

        $min = min($sizefunc, $sizefields);

        $this->r_ref = [];
        for ($i = 1; $i < $min; $i++)
            $this->r_ref[] =& ${"el$i"};
    }

    /**
     * Returns true if the query has results
     *
     * @return boolean
     */
    public function have()
    {
        if (!$this->result)
            return false;

        if (is_bool($this->result))
            return $this->result;

        return $this->backend->count_rows($this->result) > 0;
    }

    /**
     * Last insert_id
     *
     * @return integer
     */
    public function lastid()
    {
        return $this->backend->lastid($this->conn);
    }

    /**
     * Affected Rows
     *
     * @return integer
     */
    public function get_affected_rows()
    {
        return $this->backend->affected_rows($this->conn);
    }

    /**
     * Fetch result in an array
     *
     * @return array
     */
   public function fetch_assoc()
   {
      return $this->backend->fetch_assoc($this->result);
   }

    /**
     * Fetch an entry of result
     *
     * @param boolean $oneshot just one answer and close
     * @return array
     */
    public function fetch()
    {
        if (!$this->result)
            return false;

        $array = $this->backend->fetch_array($this->result);
        if (!$array)
            return false;

        $size = safe_count($array);
        for ($i = 0; $i < $size; $i++)
            $this->r_ref[$i] = $array[$i];

        return true;
    }

    /**
     * Fetch all results in an array
     *
     * @return array
     */
    public function fetch_all()
    {
        $results = [];
        if ($this->result) {
            if (is_bool($this->result))
                $results = $this->result;
            else {
                while ($array = $this->backend->fetch_assoc($this->result))
                    $results[] = $array;
            }
        }
        $this->close();
        return $results;
    }

    /**
     * @return string
     */
    public function getSqlQuery()
    {
        return $this->sql;
    }

    /**
     * Close statement
     *
     */
    public function close()
    {
        if ($this->result && !is_bool($this->result)) {
            $this->backend->free_result($this->result);
            $this->result = false;
        }
    }

    /**
     * Destructor
     *
     */
    function __destruct()
    {
        $this->close();
    }

}

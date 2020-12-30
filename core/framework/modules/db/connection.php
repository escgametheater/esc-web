<?php
/**
 * SQL Connection
 * to deal with binding params
 *
 * @version 1
 * @package esc
 * @subpackage sql
 */
class SQLConnection
{
    /**
     * Connection to the db
     *
     * @access private
     * @var
     */
    private $conn = null;

    /**
     * SQL query
     *
     * @var string
     */
    private $sql;


    public function __construct($backend, $conn, $sql = null)
    {
        $this->conn = $conn;
        $this->sql = $sql;
        $this->backend = $backend;
    }

    /**
     * Handle bind_param like mysqli
     *
     * @param string $type parameters type
     * @param mixed $el1 reference
     * etc
     */
    public function bind_param()
    {
        $size = func_num_args() - 1;

        if ($size < 1) {
            elog("Not enough argument for bind_param (min 2) got ${size}");
            return false;
        }

        $args = func_get_args();

        $format_string = $args[0];

        if (strlen($format_string) != $size) {
            elog("Invalid length for format string for bind_param: ${args['0']}");
            $size = min(strlen($format_string), $size);
        }

        $sql =& $this->sql;

        $start = 0;
        for ($i = 1; $i < $size + 1; $i++) {
            $pos = strpos($sql, '?', $start);

            if ($pos === false) {
                $real_size = $i - 1;
                elog("Trying to bind too many params: expected ${real_size} got ${size}");
                return false;
            }

            switch ($format_string[$i - 1]) {
                case 'i':
                    $val = (int)$args[$i];
                    break;
                case 'f':
                    $val = (float)$args[$i];
                    break;
                default:
                    $val = $this->conn->quote_value($args[$i]);
                    break;
            }

            $start = $pos + strlen($val);

            // Replace the question mark by $val
            $sql = ($pos > 1 ? substr($sql, 0, $pos) : '')
                .$val.($pos > 1 ? substr($sql, $pos + 1) : '');
        }
        return true;
    }

    /**
     * Execute the statement
     *
     */
    public function execute()
    {
        $this->query_count++;
        return $this->backend->do_query($this->conn, $this->sql);
    }

    /**
     * Destructor
     * free connections
     *
     */
    public function __destruct()
    {
        if ($this->conn !== null)
            $this->backend->free_connection($this->conn);
    }

}

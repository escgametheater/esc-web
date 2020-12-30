<?php
/**
 * MySQL Backend
 *
 * @package db
 */
class MySQLBackend extends DBBackend
{
    /**
     * Key to identify a connection
     */
    protected function get_connection_key()
    {
        $socket = $this->conf['socket'];
        $host = $this->conf['host'];
        $port = $this->conf['port'];
        $user = $this->conf['user'];
        $dbcg = $this->conf['db'];
        return "$host-$port-$socket-$user-$dbcg";
    }

    /**
     * Open a mysql connection to a host
     *
     * @param string $host
     */
    protected function _get_connection()
    {
        global $CONFIG;

        $isProd = $CONFIG['is_prod'];

        $mi = mysqli_init();

        if ($isProd) {
            //$mi->options(MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, true);
            $mi->ssl_set(
                NULL,
                NULL,
                $this->conf['pem'],
                NULL,
                NULL
            );
        }

        if ($mi == null)
            throw new DBConnectionFailed('Failed to initialize MySQLi extension');

        $mi->options(MYSQLI_CLIENT_INTERACTIVE, "180");

        if (!$mi->real_connect($this->conf['host'],
                               $this->conf['user'],
                               $this->conf['pass'],
                               $this->conf['db'],
                               $this->conf['port'],
                               $this->conf['socket'],
                               $isProd ? MYSQLI_CLIENT_SSL : null))
            throw new DBConnectionFailed('Connection to MySQL failed: '.mysqli_connect_error());
        // Set encoding
        $mi->set_charset(array_get($this->conf, 'encoding', 'utf8'));

        return $mi;
    }

    /**********
    * Escapes
    **********/

    /**
     * Escapes a single field
     *
     * @return string
     */
    public function quote_field($string)
    {
        return '`'.$this->escape_string($string).'`';
    }

    /**
     * Escapes a single value
     *
     * @return string
     */
    public function quote_value($value)
    {
        if ($value === null)
            $escaped_value = 'NULL';
        elseif (is_bool($value))
            $escaped_value = $value ? '1' : '0';
        elseif (is_int($value))
            $escaped_value = (string)$value;
        else
            $escaped_value = '"'.$this->escape_string($value).'"';

        return $escaped_value;
    }

    /**
     * Escape a string
     *
     * @param string
     * @return string
     */
    public function escape_string($str)
    {
      // Use a slave connection because, we are sure we will
      // need at least one read thus the connection is not
      // wasted
        return mysqli_real_escape_string($this->get_slave_connection(), $str);
    }

    /**
     * @param mysqli $conn
     * @param string $sql
     * @return mixed|SQLResult
     * @throws DBConnectionLost
     */
    public function _query($conn, $sql)
    {
        $result = mysqli_query($conn, $sql);
        if (!$result) {
            if ($conn->errno == 1169 || $conn->errno == 1062 || $conn->errno == 1061) {

                $ex = DBDuplicateKeyException::class;

            } elseif ($conn->errno == 2006 || $conn->errno == 2013) {

                if (!$this->connectionLost) {

                    try {
                        $this->connectionLost = true;

                        $conn = $this->get_connection();

                        $sqlResult = $this->_query($conn, $sql);

                        $this->connectionLost = false;

                        return $sqlResult;

                    } catch (DBConnectionLost $e) {
                        $this->connectionLost = true;
                    }

                }

                $ex = DBConnectionLost::class;

            }  else {
                $ex = DBException::class;
            }

            $error = mysqli_error($conn);

            throw new $ex("Query error code ({$conn->errno}) failed: {$error} \n\n For sql: \n\n {$sql}");
        }

        return new SQLResult($this, $conn, $sql, $result);
    }

    /**
     * Last insert_id
     *
     * @return integer
     */
    public function lastid($conn = null)
    {
        if ($conn === null)
            $conn = $this->get_master_connection();
        return mysqli_insert_id($conn);
    }

    /**
     * Autocommit state
     *
     * @param boolean $b
     * @return boolean
     */
    protected function autocommit($conn, $new_value)
    {
        return mysqli_autocommit($conn, $new_value);
    }

    /**
     * Commit (end transaction)
     *
     * @return boolean
     */
    public function _commit()
    {
        return mysqli_commit($this->get_master_connection());
    }

    /**
     * Rollback
     *
     * @return boolean
     */
    public function rollback()
    {
        return mysqli_rollback($this->get_master_connection());
    }

    /**
     * @param $conn
     * @return bool
     */
    protected function close_connection($conn)
    {
        return mysqli_close($conn);
    }

    /**
     * Count fields in resultset
     *
     * @return integer
     */
    public function count_fields($result)
    {
        return mysqli_num_fields($result);
    }

    /**
     * Count rows in resultset
     *
     * @return integer
     */
    public function count_rows($result)
    {
        return mysqli_num_rows($result);
    }

    /**
     * Count affected rows from last query
     *
     * @param $conn MySQL connection
     * @return integer
     */
    public function affected_rows($conn)
    {
        return mysqli_affected_rows($conn);
    }

    /**
     * Fetch associative array from result set
     *
     * @param $result MySQL resultset
     * @return array
     */
    public function fetch_assoc($result)
    {
        if (!$result)
            return false;
        return mysqli_fetch_assoc($result);
    }

    /**
     * Fetch array from result set
     *
     * @param $result array resultset
     * @return array
     */
    public function fetch_array($result)
    {
        if (!$result)
            return false;
        return mysqli_fetch_array($result, MYSQLI_NUM);
    }

    /**
     * Free resultset
     *
     * @return boolean
     */
    public function free_result($result)
    {
        return mysqli_free_result($result);
    }

    /**
     * Ping (and reconnect if needed)
     */
    public function ping()
    {
        foreach ($this->connections as $host => $conn) {
            $conn->ping();
        }
    }


}

DB::register_backend('mysql', 'MySQLBackend');

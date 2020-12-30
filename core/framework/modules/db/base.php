<?php
/**
 * SQL Wrapper
 *
 * @version 1
 * @package esc
 * @subpackage sql
 */
abstract class DBBackend {
	/**
	 * Used by query()
	 */
	const WRITE_QUERY = 0;
	const READ_QUERY = 1;

	protected $connectionLost = false;

	/**
	 * Connections
	 * 1 connection per host right now.
	 *
	 * @var MySQLBackend[]
	 */
	protected $connections = [];

	/**
	 * Information needed to
	 * to connect to database
	 *
	 * @var array
	 */
	protected $conf = null;

	/**
	 *  Number of queries run
	 *  so far for this request
	 *
	 * @var integer
	 */
	public $query_count = 0;

	/**
	 * All the queries are stored here
	 * in debug mode
	 *
	 * @var array
	 */
	public $queries = [];

	/**
	 * Connection to a db master
	 *
	 * @var object mysql connection
	 */
	private $master = null;

	/**
	 * Connection to a db slave
	 *
	 * @var object mysql connection
	 */
	private $slave = null;

	/**
	 * Enable profiling
	 *
	 * @var object mysql connection
	 */
	protected $profiling = true;

	/**
	 * Constructor
	 *
	 * @param string $name connexion name (ex:SQLN_SITE);
	 */
	public function __construct($conf) {
		$this->conf = $conf;
	}

	/**
	 * Destructor
	 * close connection
	 */
	public function __destruct() {
		/** @var MySQLBackend $conn */
		foreach ($this->connections as $conn) {
			//$this->close_connection($conn);
		}

	}

	/**
	 * Start profiling
	 */
	public function setProfiling($new_value) {
		$this->profiling = $new_value;
	}

	/*********
		    * Escapes
	*/

	/**
	 * Escapes an array of fields
	 * (in case they contain special characters or are sql keywords)
	 *
	 * @return array
	 */
	public function quote_fields($fields) {
		if (!is_array($fields)) {
			$fields = [$fields];
		}

		$escaped_fields = [];
		foreach ($fields as $field) {
			$escaped_fields[] = $this->quote_field($field);
		}

		return $escaped_fields;
	}

	/**
	 * Escapes an array of values
	 * (in case they contain special characters or are sql keywords)
	 *
	 * @return array
	 */
	public function quote_values($values) {
		if (!is_array($values)) {
			$values = [$values];
		}

		$escaped_fields = [];
		foreach ($values as $value) {
			$escaped_fields[] = $this->quote_value($value);
		}

		return $escaped_fields;
	}

	/**
	 * Escapes an array of data to prevent SQL Injections
	 *
	 * @return string
	 */
	public function quote_array($data) {
		$new_data = [];
		foreach ($data as $key => $value) {
			$field = $this->quote_field($key);
			$value = $this->quote_value($value);
			$new_data[] = "$field = $value";
		}
		return $new_data;
	}

	/**********
		    * Queries
	*/

	/**
	 * Prepare a read sql statement
	 * (can use a slave for the query)
	 *
	 * @param string $sql
	 * @return object SQLConnection
	 */
	public function prepare_read($sql) {
		$conn = $this->get_slave_connection();
		return new SQLConnection($conn, $sql);
	}

	/**
	 * Prepare a read sql statement
	 * (uses a master for the query)
	 *
	 * @param string $sql
	 * @return object SQLConnection
	 */
	public function prepare_write($sql) {
		$conn = $this->get_master_connection();
		return new SQLConnection($conn, $sql);
	}

	/**
	 * Open a connection to the master db
	 */
	protected function get_master_connection() {
		if (!isset($this->master)) {
			$this->master = $this->get_connection($this->conf['host']);
		}

		return $this->master;
	}

	/**
	 * Open a connection to a random slave
	 */
	protected function get_slave_connection() {
		if (!isset($this->slave)) {
			if (array_key_exists('slaves', $this->conf) && !empty($this->conf['slaves'])) {
				$slaves = $this->conf['slaves'];
				$slave_host = array_rand($slaves);
			} else {
				$slave_host = $this->conf['host'];
			}
			$this->slave = $this->get_connection($slave_host);
		}
		return $this->slave;
	}

	/**
	 * Open a connection
	 *
	 * @return mixed
	 */
	protected function get_connection() {

		$key = $this->get_connection_key();

		if ($this->connectionLost || !array_key_exists($key, $this->connections)) {

			if ($this->profiling) {
				$start_time = microtime(true);
			}

			$conn = $this->_get_connection();

			if ($this->profiling) {
				$end_time = microtime(true);
				$time = round(($end_time - $start_time) * 1000, 2);
				$host = $this->conf['host'];
				$this->queries[] = [$time, "CONNECT $host"];
			}

			$this->connections[$key] = $conn;
		}

		return $this->connections[$key];
	}

	/**
	 * Execute a write query
	 *
	 * @param string $sql
	 * @return object SQLResult
	 */
	public function query_write($sql) {
		return $this->query($sql, DBBackend::WRITE_QUERY);
	}

	/**
	 * Execute a read query
	 *
	 * @param string $sql
	 * @return SQLResult
	 */

	public function query_read($sql) {
		return $this->query($sql, DBBackend::READ_QUERY);
	}

	/**
	 * Query
	 *
	 * @param string $sql
	 * @param int $type
	 * @return SQLResult
	 */
	protected function query($sql, $type) {
		if ($type == DBBackend::WRITE_QUERY) {
			$conn = $this->get_master_connection();
		} else {
			$conn = $this->get_slave_connection();
		}

		if ($this->profiling) {
			$start_time = microtime(true);
		}

		$this->query_count++;

		$result = $this->_query($conn, $sql);

		if ($this->profiling) {
			$end_time = microtime(true);
			$time = round(($end_time - $start_time) * 1000, 2);
			$this->queries[] = [$time, $sql];
			if (count($this->queries) > MAX_DEBUG_QUERIES_LOG) {
				array_pop($this->queries);
			}

		}

		return $result;
	}

	/**
	 * Get the first result from the query
	 *
	 * @param string $sql
	 * @return array
	 */
	public function query_first($sql) {
		$result = $this->query($sql, DBBackend::READ_QUERY);
		return $result->fetch_assoc();
	}

	/**
	 * Begin transaction
	 *
	 * @return SQLConnection
	 */
	public function begin() {
		$conn = $this->get_master_connection();
		$this->autocommit($conn, false);
		return new SQLConnection($this, $conn);
	}

	/**
	 * Begin transaction
	 *
	 * @return boolean
	 */
	public function commit() {
		$r = $this->_commit();
		$conn = $this->get_master_connection();
		$this->autocommit($conn, true);
		return $r;
	}

	/**
	 * Cleaning
	 *
	 * @param db connection $conn
	 * @return boolean
	 */
	public function free_connection($conn) {
		// Re-add connection to pool
		// nothing to do since connection is not removed from pool yet
		// since there is no pool yet
		return true;
	}

	/**
	 * ----------------------------------------------
	 * Abstract Functions implemented in each backend
	 * ----------------------------------------------
	 */

	/**
	 * Open a mysql connection to a host
	 *
	 * @param string $host
	 */
	abstract protected function _get_connection();

	/**
	 * Escapes a single field
	 *
	 * @return string
	 */
	abstract public function quote_field($string);

	/**
	 * Escapes a single value
	 *
	 * @return string
	 */
	abstract public function quote_value($value);

	/**
	 * Escape a string
	 *
	 * @param string
	 * @return string
	 */
	abstract public function escape_string($str);

	/**
	 * Simple query
	 *
	 * @param string $sql
	 * @return mixed
	 */
	abstract public function _query($conn, $sql);

	/**
	 * Last insert_id
	 *
	 * @return integer
	 */
	abstract public function lastid();

	/**
	 * Commit (end transaction)
	 *
	 * @return boolean
	 */
	abstract public function _commit();

	/**
	 * Rollback
	 *
	 * @return boolean
	 */
	abstract public function rollback();

	/**
	 * Autocommit state
	 *
	 * @param boolean $b
	 * @return boolean
	 */
	abstract protected function autocommit($conn, $new_value);

	/**
	 * Close connection
	 *
	 * @return boolean
	 */
	abstract protected function close_connection($conn);

	/**
	 * Count fields in resultset
	 *
	 * @return integer
	 */
	abstract public function count_fields($result);

	/**
	 * Count rows in resultset
	 *
	 * @return integer
	 */
	abstract public function count_rows($result);

	/**
	 * Count affected rows from last query
	 *
	 * @param $conn MySQL connection
	 * @return integer
	 */
	abstract public function affected_rows($conn);

	/**
	 * Fetch associative array from result set
	 *
	 * @param $result MySQL resultset
	 * @return array
	 */
	abstract public function fetch_assoc($result);

	/**
	 * Fetch array from result set
	 *
	 * @param $result MySQL resultset
	 * @return array
	 */
	abstract public function fetch_array($result);

	/**
	 * Free resultset
	 *
	 * @return boolean
	 */
	abstract public function free_result($result);

	/**
	 * Ping (and reconnect if needed)
	 */
	abstract public function ping();
}

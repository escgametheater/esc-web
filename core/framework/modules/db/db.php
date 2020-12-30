<?php
/**
 * Proxy Requests to SQL Backend
 *
 * @version 1
 * @package esc
 * @subpackage sql
 */

class DB {
	/**
	 * Database to access by default
	 */
	const DEFAULT_NAME = SQLN_SITE;

	/**
	 * Containing the configuration
	 */
	protected $config;

	/**
	 * Registered backends are stored here
	 * so that we know which files have been loaded
	 */
	private static $backends = [];

	/**
	 * Instances
	 * One instance for each
	 * configuration name
	 *
	 * @var DBBackend[]|array
	 */
	private static $instances = [];

	/**
	 * DB constructor.
	 * @param $config
	 */
	public function __construct($config) {
		$this->config = $config['sql'];
	}

	/**
	 * Registers backend (when loading backend file)
	 *
	 * @param string backend name
	 * @param string class name
	 */
	public static function register_backend($name, $klass) {
		self::$backends[$name] = $klass;
	}

	/**
	 * Create instances
	 *
	 * @static
	 * @param string $name
	 * @return DB class instance
	 */
	public function create_instance($name, $backend_name = null) {
		if (!array_key_exists($name, $this->config)) {
			throw new eConfiguration("No DB config set for name: ${name}");
		}

		$conf = $this->config[$name];

		if ($backend_name === null) {
			$backend_name = $conf['backend'];
		}

		require_once "backends/${backend_name}.php";
		if (!array_key_exists($backend_name, self::$backends)) {
			throw new DBException("DB backend not registered: " . $backend_name);
		}

		return new self::$backends[$backend_name]($conf);
	}

	/**
	 * @param null $name
	 * @return DBBackend
	 * @throws DBException
	 * @throws eConfiguration
	 */
	public static function inst($name = null) {
		if ($name == null) {
			$name = self::DEFAULT_NAME;
		}

		if (!array_key_exists($name, self::$instances)) {
			global $CONFIG;
			$db = new DB($CONFIG);
			self::$instances[$name] = $db->create_instance($name);
		}

		return self::$instances[$name];
	}

	/**
	 * Get connection to the database
     * @param null $name
     * @return DBBackend
     */
	public function get_connection($name = null) {
		if ($name == null) {
			$name = self::DEFAULT_NAME;
		}

		if (!array_key_exists($name, self::$instances)) {
			self::$instances[$name] = $this->create_instance($name);
		}

		return self::inst($name);
	}

	/**
	 * Clear all stored instances
	 *
	 * This is not static because we want to move self::$instances to
	 * non static too.
	 *
	 * @static
	 */
	public function clear_instances() {
		foreach (self::$instances as $i) {
			unset($i);
		}

		self::$instances = [];
	}

	/**
	 * Enable profiling on all stored instances
	 *
	 * This is not static because we want to move self::$instances to
	 * non static too.
	 *
	 * @static
	 */
	public function setProfiling($new_value) {
		foreach (self::$instances as $i) {
			$i->setProfiling($new_value);
		}

	}

	/**
	 * Fetch executed queries from all stored instances
	 *
	 * This is not static because we want to move self::$instances to
	 * non static too.
	 *
	 * @static
	 */
	public static function get_sql_queries() {
		$queries = [];

		foreach (self::$instances as $i) {
			$queries = array_merge($queries, $i->queries);
		}

		return $queries;
	}

	/**
	 * @param $slug
	 * @return string
	 */
	public function convertSlug($slug) {
		return iconv('UTF-8', 'ASCII//IGNORE', $slug);
	}
}

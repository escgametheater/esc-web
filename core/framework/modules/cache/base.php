<?php
/**
 * Cache Base Class
 *
 * @package cache
 */
abstract class CacheBackend implements ArrayAccess {
	/**
	 * Used to store the request cache
	 *
	 */
	protected $local_cache = [];

	/**
	 * Used to store get queries in debug mode
	 *
	 */
	protected $get_queries = [];

	/**
	 * Used to store set queries in debug mode
	 *
	 */
	protected $set_queries = [];

	/**
	 * Enable profiling
	 *
	 * @var object mysql connection
	 */
	protected $profiling = true;

	/**
	 * Cache refresh time
	 */
	protected $refresh_time;

	protected $connectTime = 0;

	/**
	 * Fetch a cache entry
	 *
	 * @param string $key
	 */
	abstract public function read($key);

	/**
	 * Create a cache entry
	 *
	 * @param string $key
	 * @param mixed $data
	 * @param integer $timeout
	 * @return boolean
	 */
	abstract public function write($key, $data, $timeout);

	/**
	 * Lock a cache entry
	 *
	 * @param string $key
	 * @return boolean
	 */
	abstract public function lock($key);

	/**
	 * Unlock a cache entry
	 *
	 * @param string $key
	 * @return boolean
	 */
	abstract public function unlock($key);

	/**
	 * Delete entry (internal use)
	 *
	 * @param string $key
	 * @return boolean
	 */
	abstract protected function _delete($key);

	public function __construct($profiling = true, $refresh_time = CACHE_REFRESH_TIME) {
		$this->profiling = $profiling;
		$this->refresh_time = $refresh_time;
	}

    /**
     * @return string
     */
	public function getBackendName()
    {
        $name = str_replace('Backend', '', get_called_class());
        return $name;
    }

    /**
     * @return int
     */
    public function getConnectTime()
    {
        return $this->connectTime;
    }

    /**
	 * Start profiling
	 */
	public function setProfiling($new_value) {
		$this->profiling = $new_value;
	}

	/**
	 * Delete entry
	 *
	 * @param string $key
	 * @return boolean
	 */
	public function delete($key, $has_lock = false) {
		$r = $this->_delete($key);
		if ($has_lock) {
			$this->unlock($key);
		}

		if (array_key_exists($key, $this->local_cache)) {
			unset($this->local_cache[$key]);
		}

		return $r;
	}

	/**
	 * @param $cache_keys
	 * @param bool|false $has_lock
	 */
	public function deleteKeys($cache_keys, $has_lock = false) {
		foreach ($cache_keys as $cache_key) {
			$this->delete($cache_key, $has_lock);
		}
	}

	/**
	 * Removes all entries from the cache (internal use)
	 *
	 * @return boolean
	 */
	abstract protected function _flush();

	/**
	 * Removes all entries from the cache
	 *
	 * @return boolean
	 */
	public function flush() {
		$r = $this->_flush();
		$this->flush_local();
		return $r;
	}

	/**
	 * Increment variable in cache
	 *
	 * @return boolean
	 */
	public function increment($key) {
		throw new Exception('Not Implemented');
	}

	/**
	 * Removes all entries from the local cache
	 *
	 * @return boolean success
	 */
	public function flush_local() {
		unset($this->local_cache);
		$this->local_cache = [];
	}

	/**
	 * Get information about the cache
	 * like the path, the server, the cache size
	 *
	 * @return array
	 */
	abstract public function get_info();

	/**
	 * First part of the get/set system, this function return a CacheResult
	 * The first variable ($var) is passed by reference.
	 *
	 * Here an exemple:
	 * <code>
	 * $c = Cache::get($data, 'myname');
	 * if ($c->shouldset)
	 * {
	 *      // Some operation to get cache data in $newdata
	 *      // (...)
	 *
	 *      $c->set($newdata);
	 * }
	 *
	 * var_dump($data);
	 * </code>
	 *
	 * The timeout is not needed but can override the set timeout
	 * @see set()
     * @param $var
     * @param $key
     * @param null $timeout
     * @param bool $lock
     * @return CacheResult
     * @throws CacheKeyInvalid
     */
	public function get(&$var, $key, $timeout = null, $lock = true) {
		if ($this->profiling) {
			$start_time = microtime(true);
		}

		if ($key == '') {
			throw new CacheKeyInvalid("invalid key: $key");
		}

		$cacheResult = new CacheResult($this, $key, $timeout);
		// First check if we have the data stored from
		// a precedent cache query
		if ($this->has_local_cache($key)) {
			$var = $this->local_cache[$key];
			$cacheResult->isset = true;
			$cacheResult->needsset = false;
			$cacheResult->shouldset = false;
			return $cacheResult;
		}

		$raw_data = $this->read($key);
		if ($raw_data !== false) {
			// The key is stored

			// Extract the retreived data
			$raw_data = unserialize($raw_data);

			$timestamp = (int) $raw_data[0];
			$timeout = $timeout == null ? (int) $raw_data[1] : $timeout;

			// Check if the key is about to expire
			$time_left = ($timestamp + $timeout) - TIME_NOW;
			if ($time_left < 0) {
				// Then entry is invalid
				$cacheResult->isset = false;
			} else {
				// The entry is stored
				$cacheResult->isset = true;

				$var = $raw_data[2];
				// Save in the request to avoid querying the cache
				// twice for the same key during one request
				$this->local_cache[$key] = $var;
			}

			if ($time_left < $this->refresh_time) {
				// The key is about to expire, so we should refresh it
				$cacheResult->needsset = true;
				$cacheResult->has_lock = $lock ? $this->lock($key) : false;
			} else {
				// The key is not expiring soon, so just use it
				$cacheResult->needsset = false;
			}
		} else {
			// The key is not stored
			$cacheResult->isset = false;
			$cacheResult->needsset = true;
			$cacheResult->has_lock = $lock ? $this->lock($key) : false;
		}
		$cacheResult->shouldset = $cacheResult->isset == false || $cacheResult->needsset && ($cacheResult->has_lock || $lock == false);

		if ($this->profiling) {
			$end_time = microtime(true);
			$time = round(($end_time - $start_time) * 1000, 2);
			$this->get_queries[] = [
				$time,
				$key,
				isset($timeout) ? $timeout : '-',
				isset($time_left) ? $time_left : '-',
				$cacheResult->isset,
				$cacheResult->needsset,
				$cacheResult->has_lock,
				$cacheResult->shouldset,
			];
			if (count($this->get_queries) > MAX_DEBUG_QUERIES_LOG) {
				array_pop($this->get_queries);
			}

		}

		return $cacheResult;
	}

	/**
	 * Cache multi get
	 *
	 * @param string $var reference
	 * @param array $keys
	 * @param integer $timeout seconds
	 * @return array
	 */
	public function multi_get(array $keys = [], $timeout = null, $lock = true) {
		$start_time = microtime(true);
		$results = [];

		$uncached_keys = [];

		foreach ($keys as $cache_key) {
			if ($this->has_local_cache($cache_key)) {
				$results[$cache_key] = $this->local_cache[$cache_key];
			} else {
				$uncached_keys[] = $cache_key;
			}

		}

		if ($uncached_keys) {
            $raw_results = $this->read($uncached_keys);

            if ($raw_results) {
                foreach ($raw_results as $key => $raw_data) {
                    $raw_data = unserialize($raw_data);


                    $timestamp = (int) $raw_data[0];
                    $timeout = $timeout == null ? (int) $raw_data[1] : $timeout;

                    $cacheKey = $key;

                    $var = $raw_data[2];

                    if (is_int($key))
                        $cacheKey = $uncached_keys[$key];

                    // Check if the key is about to expire
                    $time_left = ($timestamp + $timeout) - TIME_NOW;
                    if ($time_left < 0) {
                        // Then entry is invalid
                        $isset = false;
                    } else {
                        // The entry is stored
                        $isset = true;

                        // Save in the request to avoid querying the cache
                        // twice for the same key during one request
                        $results[$cacheKey] = $var;
                        $this->local_cache[$cacheKey] = $var;
                    }

                    if ($time_left < $this->refresh_time) {
                        // The key is about to expire, so we should refresh it
                        $needsset = true;
                        $haslock = $lock ? $this->lock($key) : false;
                    } else {
                        // The key is not expiring soon, so just use it
                        $needsset = false;
                        $haslock = false;
                    }

                    if ($this->profiling) {
                        $end_time = microtime(true);
                        $shouldset = $isset == false || $needsset && ($haslock || $lock == false);
                        $time = round(($end_time - $start_time) * 1000, 2);
                        $this->get_queries[] = [
                            $time,
                            $cacheKey,
                            $timeout,
                            $time_left,
                            $isset,
                            $needsset,
                            $haslock,
                            $shouldset,
                        ];
                        if (count($this->get_queries) > MAX_DEBUG_QUERIES_LOG) {
                            array_pop($this->get_queries);
                        }

                    }
                }
            }
        }

		return $results;
	}

	/**
	 * Define new cache data
	 *
	 * @see get()
	 * @param string $key
	 * @param mixed $data reference
	 * @param integer $timeout seconds
	 * @return boolean
	 */
	public function set($key, $data, $timeout = DEFAULT_CACHE_TIME,
		$has_lock = false, $store_locally = true) {
		if ($this->profiling) {
			$start_time = microtime(true);
		}

		$raw_data = serialize([
			TIME_NOW,
			$timeout,
			$data,
		]);
		$this->write($key, $raw_data, $timeout);
		if ($has_lock) {
			$this->unlock($key);
		}

		if ($store_locally) {
			$this->local_cache[$key] = $data;
		}

		if ($this->profiling) {
			$end_time = microtime(true);
			$time = round(($end_time - $start_time) * 1000, 2);
			$this->set_queries[] = [
				$time,
				$key,
				$timeout,
			];
			if (count($this->set_queries) > MAX_DEBUG_QUERIES_LOG) {
				array_pop($this->set_queries);
			}

		}
	}

	/**
	 * Set local cache entries
	 * This is used to store multiple entries in 1 place
	 * then fetch them and multiple cache queries for entries
	 * already fetched.
	 *
	 *
	 * @see set()
	 * @param string $key
	 * @param string $data reference
	 * @param integer $timeout seconds
	 * @return boolean
	 */
	public function set_local($key, &$data, $timeout = DEFAULT_CACHE_TIME) {
		$this->local_cache[$key] = &$data;
	}

	/**
	 * $get_queries accessor
	 *
	 * @return array
	 */
	public function get_get_queries() {
		return $this->get_queries;
	}

	/**
	 * $set_queries accessor
	 *
	 * @return array
	 */
	public function get_set_queries() {
		return $this->set_queries;
	}

	public function offsetSet($key, $value) {
		$this->set($key, $value);
	}

	public function offsetExists($offset) {
		return $this->read($offset) !== null;
	}

    /**
     * @param mixed $key
     * @return mixed
     * @throws CacheEntryNotFound
     * @throws CacheKeyInvalid
     */
	public function offsetGet($key) {
		$r = $this->get($var, $key);
		if ($r->shouldset) {
			throw new CacheEntryNotFound($r);
		}

		return $var;
	}

	public function offsetUnset($key) {
		$this->delete($key);
	}

	/**
	 * @param $key
	 * @return bool
	 */
	public function has_local_cache($key) {
		return array_key_exists($key, $this->local_cache);
	}
}

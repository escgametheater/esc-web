<?php
/**
 * Caching
 *
 * @package cache
 */
class Cache
{
    const NO_CACHE_TIME = DONT_CACHE;

    /**
    * Registered caches are stored here
    * so that we know which files have been loaded
    */
    private static $caches = [];

    /**
    * Default_cache
    */
    private static $default_cache;

    /**
     * @var CacheBackend
     */
    private static $local_cache;

    /**
    * Creates cache instance
    *
    * @param string $cache_name
    * @return CacheBackend
    */
    public static function create_cache($cache_name = null, $options = null)
    {
        if ($cache_name === null) {
            global $CONFIG;
            $cache_name = $CONFIG['cache'];
        }

        require_once "backends/${cache_name}.php";
        if (!array_key_exists($cache_name, self::$caches))
            elog("Cache not registered: ".$cache_name, LOG_DEFAULT);
        return new self::$caches[$cache_name]($options);
    }

    /**
    * Get a cache instance
    * All instances are stored here so that
    * we create cache managers only once unless we need special
    * parameters
    *
    * @param string $cache_name
    * @return CacheBackend
    */
    public static function get_cache()
    {
        if (!isset(self::$default_cache))
            self::$default_cache = self::create_cache();
        return self::$default_cache;
    }

    public static function get_local_cache($profiling = false)
    {
        global $CONFIG;
        if (!isset(self::$local_cache))
            self::$local_cache = self::create_cache($CONFIG['local_cache'], $profiling);
        return self::$local_cache;
    }

    /**
    * Registers cache (when loading backend file)
    *
    * @param string Cache Name
    * @param string Class Name
    */
    public static function register_backend($name, $inst)
    {
        self::$caches[$name] = $inst;
    }

    /**
    * Relays get to specified or default cache
    *
    * @param mixed variable to store the value
    * @param string Key to get
    * @param integer Max cache age
    * @param boolean Activate/Disable cache locking system
    * @return CacheResult
    */
    public static function get(&$var, $key, $timeout = null, $lock = true)
    {
        $cache = self::get_cache();
        return $cache->get($var, $key, $timeout, $lock);
    }

    /**
    * Relays set to specified or default cache
    *
    * @param string $key
    * @param mixed value to store
    * @param integer Max cache age
    * @return boolean success
    */
    public static function set($key, $value, $timeout = DEFAULT_CACHE_TIME)
    {
        if ($timeout == 0)
            return false;

        $cache = self::get_cache();
        return $cache->set($key, $value, $timeout);
    }

    /**
    * Relays set_local to specified or default cache
    *
    * @param string $key
    * @param mixed value to store
    * @param integer Max cache age
    * @return boolean success
    */
    public static function set_local($key, $value, $timeout = DEFAULT_CACHE_TIME)
    {
        if ($timeout == 0)
            return false;

        $cache = self::get_cache();
        return $cache->set_local($key, $value, $timeout);
    }

    /**
    * Delete a cache entry
    *
    * @param string Key to delete
    * @return boolean success
    */
    public static function delete($key, $has_lock = false)
    {
        $cache = self::get_cache();
        return $cache->delete($key, $has_lock);
    }

    public static function deleteKeys($cache_keys, $has_lock = false)
    {
        $cache = self::get_cache();
        foreach ($cache_keys as $cache_key) {
            $cache->delete($cache_key, $has_lock);
        }
    }


    /**
    * Increment variable in cache
    *
    * @param string Key to delete
    * @return boolean success
    */
    public static function increment($key, $value, $fail_silently)
    {
        $cache = self::get_cache();
        return $cache->increment($key, $value, $fail_silently);
    }

    /**
    * Decrement variable in cache
    *
    * @param string Key to delete
    * @return boolean success
    */
    public static function decrement($key, $value, $fail_silently)
    {
        $cache = self::get_cache();
        return $cache->increment($key, $value, $fail_silently);
    }

}

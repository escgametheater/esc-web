<?php

/**
 * class APCBackend
 * stores the data in apc
 *
 * @package cache
 */
class APCBackend extends CacheBackend
{
    /**
     * Cache timeout
     */
    private $timeout;

    /**
     * Constructor saving configuration informations
     * (servers to connect to)
     *
     * @param array servers
     * @param int compress flag
     */
    public function __construct($options = null)
    {
        parent::__construct($options);
    }

    /**
     * @see CacheBackend::delete()
     */
    protected function _delete($key)
    {
        return apc_delete($key);
    }

    /**
     * @see CacheBackend::lock()
     */
    public function lock($key)
    {
        return apc_add("${key}-lock", true, CACHE_LOCK_TIME);
    }

    /**
     * @see CacheBackend::unlock()
     */
    public function unlock($key)
    {
        return apc_delete("${key}-lock");
    }

    /**
     * @see CacheBackend::get()
     */
    public function read($key)
    {
        return apc_fetch($key);
    }

    /**
     * Create cache entry
     *
     * @param key key
     * @param string data to store
     * @param integer time to keep to the data stored
     * @see CacheBackend::set()
     */
    public function write($key, $data, $timeout)
    {
        $r = apc_store($key, $data, $timeout);
        if ($r === false)
            elog("APC: Failed to set key: \"${key}\"");
    }

    /**
     * Increment cache entry
     *
     * @param key key
     * @param value to add to store values
     * @param if set, log error if fails
     * @see CacheBackend::set()
     */
    public function increment($key, $value = 1)
    {
        $r = apc_inc($key, $value);
        if ($r === false)
            $this->set($key, $value);
    }

    /**
     * @see CacheBackend::flush()
     */
    protected function _flush()
    {
        return apc_clear_cache('user');
    }

    /**
     * @see CacheBackend::get_info()
     */
    public function get_info()
    {
        $info = apc_sma_info();
        unset($info['block_lists']);
        $info['backend'] = 'apc';
        return $info;
    }

}

Cache::register_backend('apc', 'APCBackend');

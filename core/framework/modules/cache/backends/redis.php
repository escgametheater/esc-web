<?php

/**
 * class RedisBackend
 * stores the data in Redis
 *
 * @author <<<REDACTED>>> <mail@example.com>
 * @package cache
 */
class RedisBackend extends CacheBackend
{
    /**
     * Redis servers
     */
    private $servers;

    /**
     * Redis extension object
     * @var Redis $redis
     */
    private $redis;

    /**
     * Constructor saving configuration information
     * (servers to connect to)
     *
     * @param array $servers
     */
    public function __construct($servers = null, $compress = null)
    {
        parent::__construct();

        if (!class_exists('Redis'))
            throw new CacheInitialisationFailed('Redis module not installed');

        if ($servers === null) {
            global $CONFIG;
            if (!array_key_exists('redis', $CONFIG))
                throw new eConfiguration("Redis configuration not set");
            $servers = $CONFIG['redis'];
        }

        //$this->compress = $compress;
        $this->servers = $servers;
        $this->redis = new Redis();

        $masterHost = $this->servers['master']['host'];
        $masterPort = $this->servers['master']['port'];

        $startTime = microtime(true);
        $this->redis->pconnect($masterHost, $masterPort);
        $this->connectTime = get_milliseconds_elapsed($startTime);
        $this->redis->ping();


    }


    /**
     * @see CacheBackend::delete()
     */
    protected function _delete($key)
    {
        return $this->redis->del($key);
    }

    /**
     * @see CacheBackend::lock()
     */
    public function lock($key)
    {
        return $this->redis->set("${key}-lock", 1, CACHE_LOCK_TIME);
    }

    /**
     * @see CacheBackend::unlock()
     */
    public function unlock($key)
    {
        return $this->redis->del("${key}-lock");
    }

    /**
     * @see CacheBackend::get()
     */
    public function read($source)
    {
        if (is_array($source)) {
            $keys = [];
            foreach ($source as $k) {
                if (!$this->exceedsMaxLength($k))
                    $keys[] = $k;
                else
                    throw new CacheKeyInvalid('Cache key too long');
            }
            return $this->redis->getMultiple($keys);
        } else if ($this->exceedsMaxLength($source)) {
            throw new CacheKeyInvalid('Cache key too long');
        }

        return $this->redis->get($source);
    }

    /**
     * @param $source
     * @return bool
     */
    protected function exceedsMaxLength($source)
    {
        return is_string($source) && strlen($source) > 255;
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
        if (strlen($key) > 255)
            throw new Exception('Cache key too long');
        $r = $this->redis->set($key, $data, $timeout);
        if ($r === false)
            elog("Redis: Failed to set key: \"${key}\"");
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
        $r = $this->redis->incr($key);
        if ($r === false)
            $this->set($key, $value);
    }

    /**
     * @see CacheBackend::flush()
     */
    protected function _flush()
    {
        return $this->redis->flushAll();
    }

    public function get_stats()
    {
        return $this->redis->info();
    }

    /**
     * @see CacheBackend::get_info()
     */
    public function get_info()
    {

        return $this->redis->ping();
    }

}

Cache::register_backend('redis', 'RedisBackend');

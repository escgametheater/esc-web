<?php

/**
 * class MemCacheBackend
 * stores the data in memcached
 *
 * @package cache
 */
class MemCacheBackend extends CacheBackend
{
    /**
     * Memcache servers
     */
    private $servers;

    /**
     * Memcache extension object
     */
    private $mem;

    /**
     *  Flags (compress)
     */
    private $flags = 0;

    /**
     * Constructor saving configuration informations
     * (servers to connect to)
     *
     * @param array $servers
     * @param int $compress flag
     */
    public function __construct($servers = null, $compress = null)
    {
        parent::__construct();

        if (!class_exists('Memcache'))
            throw new CacheInitialisationFailed('Memcache module not installed');

        if ($servers === null) {
            global $CONFIG;
            if (!array_key_exists('memcache', $CONFIG))
                throw new eConfiguration("Memcache configuration not set");
            $servers = $CONFIG['memcache'];
        }
        if ($compress === null) {
            global $CONFIG;
            if (array_get($CONFIG, 'memcache_compress', false))
                $this->flags |= MEMCACHE_COMPRESSED;
        }

        $this->servers = $servers;
        $this->compress = $compress;

        $this->mem = new Memcache();
        foreach ($servers as $server) {
            $parts = explode(":", $server);
            $host = $parts[0];
            $port = 11211; // default port
            if (isset($parts[1]))
                $port = $parts[1];
            $this->mem->addServer($host, $port);
        }
        $this->mem->setCompressThreshold(20000, 0.2);
    }

    /**
     * @see CacheBackend::delete()
     */
    protected function _delete($key)
    {
        return $this->mem->delete($key);
    }

    /**
     * @see CacheBackend::lock()
     */
    public function lock($key)
    {
        return $this->mem->add("${key}-lock", 1, false, CACHE_LOCK_TIME);
    }

    /**
     * @see CacheBackend::unlock()
     */
    public function unlock($key)
    {
        return $this->mem->delete("${key}-lock");
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
            }
            $source = $keys;
        } else if ($this->exceedsMaxLength($source)) {
            throw new Exception('Cache key too long');
        }

        return $this->mem->get($source);
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
        $r = $this->mem->set($key, $data, $this->flags, $timeout);
        if ($r === false)
            elog("Memcache: Failed to set key: \"${key}\"");
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
        $r = $this->mem->increment($key);
        if ($r === false)
            $this->set($key, $value);
    }

    /**
     * @see CacheBackend::flush()
     */
    protected function _flush()
    {
        return $this->mem->flush();
    }

    public function get_stats()
    {
        return $this->mem->getExtendedStats();
    }

    /**
     * @see CacheBackend::get_info()
     */
    public function get_info()
    {
        $info = [
            'backend' => 'memcache',
        ];

        $output = "<ul>";
        foreach ($this->servers as $server) {
            $args = explode(":", $server);
            $status = $this->mem->getServerStatus($args[0], (int)$args[1]) > 0 ? "<span style=\"color: darkgreen;\">Up</span>" : "<span style=\"color: red;\">Down</span>";
            $output .= "<li>" . $args[0] . ":" . $args[1] . ": " . $status . "</li>";
        }
        $output .= "</ul>";
        $info['Servers Status'] = $output;

        $stats = $this->mem->getExtendedStats();
        foreach ($stats as $server => $server_stats) {
            $out = "<ul>";
            foreach ($server_stats as $name => $stat)
                $out .= "<li>" . $name . ": " . $stat . "</li>";
            $out .= "</ul>";
            $info[$server] = $out;
        }

        return $info;
    }

}

Cache::register_backend('memcache', 'MemCacheBackend');

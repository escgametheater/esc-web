<?php
/**
 * Caching
 *
 * @package cache
 */
class CacheFlushException extends Exception {}
class CacheInitialisationFailed extends Exception {}
class CacheKeyInvalid extends Exception {}

class CacheEntryNotFound extends Exception
{
    /** @var CacheResult */
    protected $cache_result;

    public function __construct($cache_result)
    {
        $this->cache_result = $cache_result;
    }

    public function set($value, $timeout = DEFAULT_CACHE_TIME, $set_empty = true)
    {
        if ($value || $set_empty && !$value)
            $this->cache_result->set($value, $timeout);
    }

    public function delete()
    {
        $this->cache_result->delete();
    }
}

require "result.php";
require "cache.php";
require "base.php";

if (Modules::is_loaded('http')) {
    require "middleware.php";
    Http::register_middleware(new CacheMiddleware());
}

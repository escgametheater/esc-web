<?php

/**
 * class DummyCache
 * Dummy Cache - does nothing
 * for debugging purposes
 *
 * @package cache
 */
class DummyCache extends CacheBackend
{
    public function read($key) { return false; }
    public function write($key, $data, $timeout) { return true; }

    protected function _delete($key) { return true; }
    protected function _flush() { return true; }

    public function lock($key) { return true; }
    public function unlock($key) { return true; }

    public function increment($key, $value = 1) {}

    public function get_info() { return ['backend' => 'dummy']; }
}

Cache::register_backend('dummy', 'DummyCache');

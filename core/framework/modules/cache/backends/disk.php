<?php

/**
 * class DiskCache
 * Disk Cache - stores the data on disk
 *
 * @package cache
 */
Modules::load_helper('file');

class DiskCache extends CacheBackend
{
    /**
     * Cache directory
     *
     * @var string
     */
    private $cdir;

    /**
     * For computing the max key len
     */
    private $max_key_len;

    /**
     * Create a cache instance for a directory path
     * If the directory does not exist, it tries to
     * create it
     *
     * @param string $cache_directory
     */
    public function __construct($cache_directory = '')
    {
        parent::__construct();

        if ($cache_directory == '') {
            global $CONFIG;
            $cache_directory = $CONFIG['cache_directory'];
            if ($cache_directory == '')
                throw new eConfiguration("Cache directory is not set");
        }
        $this->cdir = realpath($cache_directory);
        $this->max_key_len = 255 - strlen($this->cdir);

        if (is_dir($cache_directory) == false) {
            if (mkdir($cache_directory, 0700, true) == false)
                throw new eConfiguration("Failed to create: ${cache_directory}");
        }
    }

    /**
     * Checks if the cache is valid (timeout)
     *
     * @param integer $key optional
     * @param integer $timeout optional
     * @return boolean
     */
    private function is_valid($key, $timeout = DEFAULT_CACHE_TIME)
    {
        $path = $this->get_path($key);
        if (file_exists($path)) {
            clearstatcache(false, $path);
            $cache_age = TIME_NOW - filemtime($path);
            return $cache_age < $timeout;
        }
        return false;
    }

    /**
     * Generate a crc32 checksum
     *
     * @param string $val
     * @return string
     */
    private static function crc($val)
    {
        return sprintf('%32s', crc32($val));
    }

    /**
     * Check key for invalid characters
     * like / which could be used to acccess
     * files outside of the cache
     *
     * @param string $key
     */
    private static function check_key($key)
    {
        if ($key == '')
            throw new Exception('Key is an empty string');
        if (strpos($key, '/') !== false)
            throw new Exception("/ found in cache key: ${key}");
    }

    /**
     * Get filesystem path to store the files
     *
     * @param string $key
     */
    private function get_path($key)
    {
      return $this->cdir.'/'.$key;
    }

    /**
     * Read data and check crc32 checksum
     * This function override File's one
     *
     * @see CacheBackend::read()
     */
    public function read($key)
    {
        if (strlen($key) > 220)
            throw new Exception('Cache key too long');
        $path = $this->get_path($key);
        if (!file_exists($path))
            return false;
        $file = new File($path);
        if (!$file->open())
            return false;
        if (!$file->lockread())
            return false;
        $size = filesize($path);
        if ($size < 32) {
            $file->delete();
            return false;
        }
        $crc = $file->read(32);
        $raw_data = $file->read($size - 32);
        if ($raw_data === false) {
            elog("Reading file ${key} failed", LOG_DEFAULT);
            return false;
        }
        if ($crc != $this->crc($raw_data)) {
            $file->delete();
            elog("crc32 check failed for ${key}", LOG_DEFAULT);
            return false;
        }
        $file->close();

        return $raw_data;
    }

    /**
     * Write cache file with crc32 checksum
     *
     * @param string $key
     * @param string $data
     * @param int $timeout
     * @return string
     * @see CacheBackend::set()
     */
    public function write($key, $data, $timeout)
    {
        if (strlen($key) > 220)
            throw new Exception('Cache key too long');

        $path = $this->get_path($key);
        $tmp_path = $path.'-tmp';

        $file = new File($tmp_path);
        if (!$file->open('wb'))
            return false;
        if (!$file->lockwrite())
            return false;

        $crc = $this->crc($data);

        if (!$file->write($crc))
            return false;
        if (!$file->write($data))
            return false;
        if (!$file->close())
            return false;
        if (!@rename($tmp_path, $path))
            return false;

        return true;
    }

    /**
     * @see CacheBackend::delete()
     */
    protected function _delete($key)
    {
        $path = $this->get_path($key);
        $r = true;
        if (is_file($path))
            $r = @unlink($path);
    }

    /**
     * Removes all entries from the cache
     *
     * @see CacheBackend::flush()
     * @return int files deleted
     */
    protected function _flush()
    {
        $count = 0;

        $d = dir($this->cdir);
        while($entry = $d->read()) {
            if ($entry != "." && $entry != "..") {
                $path = "$this->cdir/${entry}";
                if (is_file($path)) {
                    if (@unlink($path))
                        $count += 1;
                    else
                        throw new CacheFlushException("Failed to delete $path");
                }
            }
        }
        $d->close();

        return $count;
    }

    /**
     * @see CacheBackend::lock()
     */
    public function lock($key)
    {
        $path = $this->get_path("$key-lock");

        $file = new File($path);
        $r = $file->open('a+');
        if (!$r) {
            // Failed to open the file
            // some other process has locked the file
            $file->close();
            return false;
        }
        $r = $file->lockwrite();
        if (!$r) {
            // Failed to acquire lock
            // some other process has locked the file
            $file->close();
            return false;
        }

        // Prune old lock files
        if (TIME_NOW - filemtime($path) < CACHE_LOCK_TIME) {
            $file->seek(0);
            $r = $file->read(1);
            if ($r == '1') {
                $file->close();
                return false;
            } elseif ($r != '') {
                elog('Invalid data in lock file: ${r}');
            }
            $file->seek(0);
        }
        $file->write('1');
        $file->close();
        return true;
    }

    /**
     * @see CacheBackend::unlock()
     */
    public function unlock($key)
    {
        $this->delete("$key-lock");
    }

    /**
     * @see CacheBackend::get_info()
     */
    public function get_info()
    {
        return [
            'backend'   => 'disk',
            'path'      => $this->cdir
        ];
    }

    /**
     * Increment cache entry
     *
     * @param key key
     * @param value to add to store values
     * @param if set, log error if fails
     * @see CacheBackend::set()
     */
    public function increment($key, $value = 1, $fail_silently = false)
    {
        $new_value = (int)$this->read($key) + $value;
        $r = $this->write($key, $new_value, null);
        if ($fail_silently == false && $r === false)
            elog("Memcache: Failed to increment key: \"${key}\"");
    }

    /**
     * Decrement cache entry
     *
     * @param key key
     * @param value to add to store values
     * @param if set, log error if fails
     * @see CacheBackend::set()
     */
    public function decrement($key, $value = 1, $fail_silently = false)
    {
        $new_value = (int)$this->read($key) - $value;
        $r = $this->write($key, $new_value, null);
        if ($fail_silently == false && $r === false)
            elog("Memcache: Failed to increment key: \"${key}\"");
    }
}

Cache::register_backend('disk', 'DiskCache');

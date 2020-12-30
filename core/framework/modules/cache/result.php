<?php
/**
 * Cache Query Result
 *
 * @package cache
 */

class CacheResult
{
    /**
     * Cache handler
     *
     */
  private $cache_inst;

    /**
     * Cache key
     *
     */
  private $key;

    /**
     * Is cache set
     *
     */
  public $isset = false;

    /**
     * True if cache needs to be refreshed
     * (the time left on it is less than CACHE_REFRESH_TIME)
     */
  public $needsset;

    /**
     * True if cache should be refreshed
     *
     */
  public $shouldset;

    /**
     * True if lock is already acquired
     *
     */
  public $has_lock = false;

    /**
     * Cache timeoout
     *
     */
    public $timeout;

  /**
   * Create a cache result
   *
   * @param CacheBackend $inst
   * @param string $key
   */
    public function __construct($inst, $key, $timeout = null)
    {
        $this->cache_inst = $inst;
        $this->key = $key;
        $this->timeout = $timeout !== null ? $timeout : DEFAULT_CACHE_TIME;
    }

    public function __destruct()
    {
        if ($this->has_lock)
            $this->cache_inst->unlock($this->key);
    }


    /**
     * Delete a cache entry
     */
    public function delete()
    {
        $this->cache_inst->delete($this->key);
    }

    /**
     * Set a cache entry
     *
     * @param cCache $inst
     * @param string $key
     */
    public function set($data, $timeout = null)
    {
        default_to($timeout, $this->timeout);
        $this->cache_inst->set($this->key, $data, $timeout, $this->has_lock);
    }

}

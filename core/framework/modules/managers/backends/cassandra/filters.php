<?php
/**
 * Query Filters
 *
 * @package managers
 */

abstract class CassandraFilter extends DBFilter
{
    abstract public function render();
}

class KeyFilter extends CassandraFilter
{
    protected $key;

    function __construct($key)
    {
        $this->key = $key;
    }

    public function render()
    {
        return $this->key;
    }
}

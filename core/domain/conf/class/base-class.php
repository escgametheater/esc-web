<?php
/**
 * Created by PhpStorm.
 * User: ccarter
 * Date: 12/5/15
 * Time: 3:17 AM
 */

class BaseAppConfiguration implements ArrayAccess, IteratorAggregate {

    /**
     * @var array|array[]
     */
    protected $dataArray = [];

    /**
     * @param $data
     */
    public function __construct($data = null) {
        if (is_array($data)) {
            foreach ($data as $key => $value)
                $this->dataArray[$key] = $value;
        }
    }

    // necessary for deep copies
    public function __clone() {
        foreach ($this->dataArray as $key => $value) {
            if ($value instanceof BaseAppConfiguration)
                $this->offsetSet($key, clone $value);
        }
    }

    /**
     * @param $offset
     * @param $value
     */
    public function offsetSet($offset, $value)
    {
        if (is_array($value))
            $value = new BaseAppConfiguration($value);
        if ($offset === null) {
            $this->dataArray[] = $value;
        } else {
            $this->dataArray[$offset] = $value;
        }
    }

    /**
     * @param $offset
     * @return bool
     */
    public function offsetExists($offset)
    {
        return isset($this->dataArray[$offset]);
    }

    /**
     * @param $offset
     */
    public function offsetUnset($offset)
    {
        if (isset($this->dataArray[$offset]))
            unset($this->dataArray[$offset]);
    }

    /**
     * @param mixed $offset
     * @return array|mixed|null
     */
    public function offsetGet($offset)
    {
        return isset($this->dataArray[$offset]) ? $this->dataArray[$offset] : null;
    }

    /**
     * @return ArrayIterator|Traversable
     */
    public function getIterator()
    {
        return new ArrayIterator($this->toArray());
    }

    /**
     * @return array|array[]
     */
    public function getDataArray()
    {
        return $this->dataArray;
    }

    /**
     * @return array|array[]
     */
    public function toArray() {
        $data = $this->dataArray;
        foreach ($data as $key => $value)
            if ($value instanceof BaseAppConfiguration)
                $data[$key] = $value->toArray();
        return $data;
    }

    /**
     * @param $name array|string
     * @param null $value
     * @throws Exception
     */
    public function assign($name, $value = null)
    {
        if (is_array($name)) {
            if ($value !== null)
                throw new Exception("value should null if name is an array");
            $this->dataArray = array_merge($this->dataArray, $name);
        } else {
            $this->dataArray[$name] = $value;
        }
    }
}

<?php
/**
 * Class for manipulating BITFIELDS
 *
 */

/**
 * BitField
 * i.e. array of booleans
 * internally stored as an int
 */
class BitField implements ArrayAccess
{
    protected $bitfield;

    public function __construct($bitfield)
    {
        $this->bitfield = $bitfield;
    }

    public static function create()
    {
        return new BitField(0);
    }

    /**
     * Toggle a bit(pattern),
     * so bits that are on are turned off,
     * and bits that are off are turned on.
     */
    public function toggle($offset)
    {
        $this->bitfield ^= pow(2, $offset);
    }

    /**
     * Get bitfield raw value
     * usefull for storing it
     */
    public function get_value()
    {
        return $this->bitfield;
    }

    /*
     * Force a specific bit(pattern) to on or off
     */
    public function offsetSet($offset, $value)
    {
        if ($value == 1)
            $this->bitfield |= pow(2, $offset);
        elseif ($value == 0)
            $this->bitfield &= ~pow(2, $offset);
        else
            throw new Exception('invalid value');
    }

    public function offsetExists($offset)
    {
        throw new Exception('not implemented');
    }

    public function offsetUnset($offset)
    {
        throw new Exception('not implemented');
    }

    /**
     * Return true or false, depending on if the bit is set
     */
    public function offsetGet($offset)
    {
        return $this->bitfield & pow(2, $offset) ? true : false;
    }
}

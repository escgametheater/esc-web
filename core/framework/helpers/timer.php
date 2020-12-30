<?php
/**
 * Timer
 *
 */
class Timer
{
    protected $start_time;
    protected $desc;

    /**
     * Starts the timer
     * @param string timer description
     */
    function __construct($string = 'nothing')
    {
        $this->desc = str_replace(["\n", "\t", "  "], [" ", "", " "], $string);

        $this->start_time = microtime(true);
    }

    /**
     * End a timer and display the result
     */
    function end()
    {
        $now = microtime(true);

        $result = round(($now - $this->start_time) * 1000, 2);

        if ($result < 0.1)
            $result = '< 0.1';

        echo '<!-- '.$this->desc.' took '.$result. ' ms -->'."\n";

        return $result;
    }
}

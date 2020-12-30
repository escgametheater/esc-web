<?php
/**
 * Log backend base class
 *
 * @package logs
 */
abstract class BaseLog
{
    /**
     * Log types
     *
     * @var array
     */
    protected $types;

    /**
     * Constructor
     * initializes log categories
     */
    public function __construct()
    {
        global $CONFIG;
        // Log categories
        $t = array_get($CONFIG, 'log_categories', []);
        if (!array_key_exists(0, $t))
            $t[0] = 'error.log';
        $this->types = $t;
    }

    /**
     * Insert log information
     *
     * @param string $type
     * @param string $msg
     * @param string $file
     * @param integer $line
     */
    abstract protected function insert($type, $msg, $file = null, $line = null);

    /**
     * Add a new log entry
     *
     * @param string $type
     * @param string $msg
     * @param string $file
     * @param integer $line
     */
    public function log($type, $msg, $file = null, $line = null)
    {
        if (!array_key_exists($type, $this->types)) {
            // Log type does not exist
            // default to the general log
            elog("Log type does not exist: $type", LOG_DEFAULT);
            $type = LOG_DEFAULT;
        }

        return $this->insert($type, $msg, $file, $line);
    }
}

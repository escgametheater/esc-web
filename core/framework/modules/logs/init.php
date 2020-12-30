<?php
/**
 * Log system
 *
 * @package logs
 */

require "base.php";

/**
 * Default function used for logging
 *
 * @param string String to log
 * @param int Type of log
 * @param int Stack offset: number of levels to ignore
 * 0 would show elog in the stack dump,
 * 1 usually is what you need,
 * 2 to be used when elog in a function whose only use error checking
 */
function elog($text, $type = LOG_DEFAULT, $stack_offset = 1) {
	global $FRAMEWORK_DIR;
	static $logger;

	$stack = debug_backtrace();
	$stack_info = array_get($stack, $stack_offset, []);
	$file = basename(array_get($stack_info, 'file', ''));
	$line = array_get($stack_info, 'line', null);

	if (!isset($logger)) {
		global $CONFIG;
		$log_class = array_get($CONFIG, 'log_class', 'FileLog');
		require_once "backends/" . strtolower($log_class) . ".php";
		$logger = new $log_class();
	}

	return $logger->log($type, $text, $file, $line);
}

/**
 * Used for logging output in scripts
 *
 * @param string String to log
 * @param string log level (INFO, ERROR, ...)
 * @param string destination to log to (uses last specified destination by default)
 */
function std_log($text, $level = 'INFO', $new_destination = null) {
	static $destination;

	if ($new_destination) {
		$destination = $new_destination;
	}

	$t = date('Y-m-d H:i:s');
	$msg = "$t - $level - $text\n";
	echo $msg;
	if (isset($destination) && $destination != '/dev/stdout') {
		file_put_contents($destination, $msg, FILE_APPEND);
	}
}

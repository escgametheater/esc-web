<?php
/**
 * File logging
 *
 * @package logs
 */

class FileLog extends BaseLog {
	/**
	 * Log directory
	 *
	 * @var string
	 */
	private $dir;

	/**
	 * Constructor
	 */
	public function __construct() {
		global $CONFIG;

		// Log directory
		$log_dir = array_get($CONFIG, 'log_dir', '');
		if (!is_dir($log_dir)) {
			throw new eConfiguration('Log dir is not a directory');
		}

		$this->dir = $log_dir;

		parent::__construct();
	}

	protected function insert($type, $msg, $file = null, $line = null) {
		if ($file === null) {
			$file = 'unknown';
		}

		if ($line === null) {
			$line = 0;
		}

		$t = date('Y-m-d H:i:s');
		$l = "[$t] ($file:$line) $msg";

		$ftype = $this->types[$type];
		$fdir = $this->dir;
		return error_log($l, /*file*/3, "${fdir}/${ftype}");
	}

}

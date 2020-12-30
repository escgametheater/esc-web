<?php
/**
 * Debug functions
 *
 */

class Debug {

	/**
	 * Debug helper function.  This is used to update the debug mode
	 * during a request.
	 *
	 * @return array
	 */
	public static function setDebug(Request $request, $new_debug) {
		$request->debug = $new_debug;

		if (Modules::is_loaded(Modules::CACHE)) {
			$request->cache->setProfiling($request->debug);
		}

		if (Modules::is_loaded(Modules::DB)) {
			$request->db->setProfiling($request->debug);
		}
	}

	/**
	 * Debug helper function.  This is used as template tag
	 * outputs all the sql queries used for the request
	 *
	 * @return array
	 */
	public static function sql_queries($simple = false) {
		$queries = DB::get_sql_queries();

		foreach ($queries as &$q) {
			// $geshi = new GeSHi(trim($q[1]), 'mysql');
			// $geshi->set_header_type(GESHI_HEADER_NONE);
			// $q[2] = $geshi->parse_code();
            if (!$simple)
			    $q[2] = $q[1];
		}

		return $queries;
	}

	public static function total_sql_query_time() {
		$sql_queries = self::sql_queries();
		$query_time = 0;
		foreach ($sql_queries as $query) {
			$query_time = $query_time + floatval($query[0]);
		}

		return $query_time;
	}

	/**
	 * Debug helper function.  That makes the traceback more consistent.
	 * It does 2 things:
	 * - adds file and line values for each function call if they are missing
	 * - replaces the framework and project path by short strings to improve
	 *   readability
	 *
	 * @return string
	 */
	public static function get_stack($exception = null, $offset = 0) {
		global $CONFIG, $FRAMEWORK_DIR, $PROJECT_DIR;

		if ($exception) {
			$stack = $exception->getTrace();
			// Workround a null (!) stack trace
			if (!$stack) {
				$stack = [];
			}

			array_unshift($stack, ['file' => $exception->getFile(),
				'line' => $exception->getLine(),
				'args' => [],
				'function' => '']);
		} else {
			$stack = debug_backtrace();
		}

		// offset
		$offset = max($offset, 0);
		while ($offset > 0) {
			array_shift($stack);
			$offset--;
		}

		foreach ($stack as $level => &$call) {
			$call['line'] = array_get($call, 'line', '0');
			$call['file'] = array_get($call, 'file', 'unknown');
			$call['file'] = str_replace($FRAMEWORK_DIR, 'FRAMEWORK_DIR', $call['file']);
			$call['file'] = str_replace($PROJECT_DIR, 'PROJECT_DIR', $call['file']);
			$call['file'] = str_replace($CONFIG['templ']['compiled_dir'], 'COMPILED_TEMPLATES_DIR', $call['file']);
			$call['file'] = str_replace($CONFIG['templ']['dir'], 'TEMPLATES_DIR', $call['file']);
		}

		return $stack;
	}

	/**
	 * Debug helper function.  This is a wrapper for debug_backtrace() that
	 * converts the array into a readable string
	 *
	 * @return string
	 */
	public static function get_stack_string($exception = null, $offset = 0) {
		$stack = self::get_stack($exception, $offset);
		$str = '';
		foreach ($stack as $level => $call) {

			// TODO: Figure out why the debugger fails to have a function index sometimes.
			if (isset($call['function'])) {
				$function = $call['function'];
			} else {
				$function = '@get_stack_string---function-NOT-set-currently-WHY?';
			}

			$str .= $call['file'] . '(' . $call['line'] . ') @ ' . $function . "\n";
		}
		return $str;
	}

	/**
	 * Debug helper function.  This is a wrapper for debug_backtrace() that
	 * prints the stack in a readable way
	 *
	 */
	public static function print_stack($exception = null, $show_args = false, $echo = true) {
		$stack = self::get_stack($exception);

		$content = '<div style="text-align: left; padding: 7px; font-size: 15px;">';
		$content .= '<h3 style="color: black;">Debug Stack Trace:</h3>';
		$content .= '<ul>';
		foreach ($stack as $level => $call) {
			$content .= '<li style="color: black;">';
			if (array_key_exists('file', $call)) {
				$content .= $call['file'];
				if (array_key_exists('line', $call)) {
					$content .= '(' . $call['line'] . ') ';
				}

			}

			// TODO: Figure out why indexes 'function' and 'args' are not always set.
			if (isset($call['function'])) {
				$function = $call['function'];
			} else {
				$function = '@print_stack--function-NOT-set-currently-WHY?';
			}

			$content .= '@ ' . $function;

			if (isset($call['args'])) {

				if (count($call['args']) > 0 && $show_args) {
					$content .= '<div><a href="#" onclick="document.getElementById(\'args_' . $level . '\').style.display = \'block\';">Click here to show args</a></div>';
					$content .= '<ul id="args_' . $level . '" style="display: none;">';
					foreach ($call['args'] as $arg) {
						$content .= '<li style="color: black;">';
						$content .= get_class($arg);
						$content .= '</li>';
					}
					$content .= '</ul>';
				}
			}
			$content .= '</li>';
		}
		$content .= '</ul>';
		$content .= '</div>';

		if ($echo)
		    echo $content;
		else
		    return $content;
	}

	public static function get_time_elapsed() {
		$time = microtime(true) - TIME_NOW;
		$time *= 1000;
		$time = round($time, 2);
		return $time;
	}

	/**
	 * Debug helper function.  This is used displays the time
	 * elapsed since the beginning of the request
	 *
	 * @return string
	 */
	public static function show_time_elapsed() {
		$time = microtime(true) - TIME_NOW;
		$time *= 1000;
		$time = round($time, 2);
		echo 'elapsed: ' . $time . ' ms';
	}

	/**
	 * Debug helper function.  This is a wrapper for var_dump() that adds
	 * the <pre /> tags, cleans up newlines and indents, and runs
	 * htmlentities() before output. (smarty tag version)
	 *
	 * @param array $params
	 * @param object $smarty
	 * @return string
	 */
	public static function dump_tag($params = '', $smarty = null) {
		if (array_key_exists('var', $params)) {
			return dump($params['var'], /*label*/null, /*echo*/false);
		} else {
			return 'missing required param var';
		}

	}

	/**
	 * Debug helper function.  This is used to display
	 * the init time used for the current page
	 *
	 * @return integer
	 */
	public static function init_time_tag($params = '', $smarty = null) {
		global $init_time;
		return round($init_time * 1000, 2);
	}

	/**
	 * Debug helper function.  This is used to display
	 * the computing time used for the current page
	 *
	 * @return integer
	 */
	public static function processing_time_tag($params = '', $smarty = null) {
		return round((microtime(true) - TIME_NOW) * 1000, 2);
	}
}

/**
 * Debug helper function.  This is a wrapper for var_dump() that adds
 * the <pre /> tags, cleans up newlines and indents, and runs
 * htmlentities() before output.
 *
 * @param  mixed  $var   The variable to dump.
 * @param  string $label OPTIONAL Label to prepend to output.
 * @param  bool   $echo  OPTIONAL Echo output if true.
 * @return string
 */
function dump($var, $label = null, $echo = true) {
	// format the label
	$label = ($label === null) ? '' : rtrim($label) . ' ';

	// var_dump the variable into a buffer and keep the output
	ob_start();
	var_dump($var);
	//echo sizeof($var);
	$output = ob_get_clean();

	// neaten the newlines and indents
	$output = preg_replace("/\]\=\>\n(\s+)/m", "] => ", $output);
	if (PHP_SAPI == 'cli') {
		$output = PHP_EOL . $label
			. PHP_EOL . $output
			. PHP_EOL;
	} else {
		$output = '<pre style="text-align: left;">'
		. $label
		. htmlspecialchars($output, ENT_QUOTES)
			. '</pre>';
	}

	if ($echo) {
		echo ($output);
	} else {
		return $output;
	}

}

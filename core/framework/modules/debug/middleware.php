<?php
/**
 * Debug Middleware
 * Profiling functions
 * Query displaying
 * etc
 *
 * @package debug
 */

class DebugMiddleware extends Middleware {
	/**
	 * Request processing
	 * @param Request $request
	 * @return optional Response
	 */
	public function process_request(Request $request)
    {
		Debug::setDebug($request, $request->debug);
	}

	/**
	 * Response processing
	 * @param Request $request
	 * @param Response $response
	 * @return optional Response
	 */
	public function process_response(Request $request, HttpResponse $response) {
		if ($request->debug && $response instanceof HtmlResponse) {
			$debugbar_time = isset($response->debug_time) ? $response->debug_time : 0;
			$total_time = microtime(true) - $request->start_time - $debugbar_time;
			$view_time = $total_time - $response->template_time - $response->default_tree_time;
			$view_time = round($view_time * 1000, 2);
			$debug_time = round($debugbar_time * 1000, 2);
			$total_time = round($total_time * 1000, 2);

			if (isset($response->custom_bone) && !$response->custom_bone) {

				$debugContent = "Object.assign(debug_info, { view_time: {$view_time}, debug_time: {$debug_time}, total_time: {$total_time} });";
				$response->content = str_replace('<!-- DEBUGINFO_DEBUG -->', $debugContent, $response->content);
			}
		}
	}
}

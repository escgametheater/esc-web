<?php
/**
 * Entry point: creates a request and handles it
 *
 * @package handlers
 * @subpackage cgi
 */
function create_request()
{
    global $CONFIG;

    return new Request($_SERVER, $_POST, $_COOKIE, $_FILES, $CONFIG,
                       getallheaders());
}

function handler()
{
    // Downtime check
    if (defined('DOWN')) {
        $response = Http::render_error('downtime');
        $response->display();
        exit();
    }

    $request = null;
    $request = create_request();

    Http::set_request($request);

    try {
        $response = Http::handle_request($request);
    } catch(Http404 $e) {
        $response = Http::render_error(404, $e->getMessage(), $request);
    } catch(HttpDenied $e) {
        $response = Http::render_error(401, $e->getMessage(), $request);
    } catch(DBConnectionFailed $e) {
        $response = Http::render_error(500, $e->getMessage(), $request);
    } catch(Exception $e) {
        log_exception_in_sentry($e, $request);
        exhandler($e);
        exit();
    }

    if ($request && $response)
        $response->setRequestId($request->requestId);

    // Display result
    $response->display();
}
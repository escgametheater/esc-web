<?php
/**
 * Entry point: creates a request and handles it
 *
 * @package handlers
 * @subpackage scgi
 */

require "server.php";
require "response.php";

class SCGIHandler extends SCGIServer
{
    protected static function create_request($headers, $body, $config)
    {
        // TODO: fill this
        $files = [];

        // Parse cookies
        $cookies = [];
        if (array_key_exists('HTTP_COOKIE', $headers)) {
            $cookies_str = explode("; ", $headers['HTTP_COOKIE']);
            foreach ($cookies_str as $value) {
                $tuple = explode('=', $value);
                $cookies[$tuple[0]] = $tuple[1];
            }
        }

        // Post
        parse_str($body, $post);

        return new Request($headers, $post, $cookies, $files, $config);
    }

    protected function requestHandler($headers, $body, $config)
    {
        // Text has to be sent through network
        // echo wouldn't work overwise
        ob_start();

        $request = self::create_request($headers, $body, $config);

        try {
            $response = Http::handle_request($request);
        } catch(Http404 $e) {
            $response = Http::render_error(404, $e->getMessage());
        } catch(HttpDenied $e) {
            $response = Http::render_error(401, $e->getMessage());
        } catch(DBConnectionFailed $e) {
            $response = Http::render_error(500, $e->getMessage());
        }

        // Create response
        $scgi_response = new SCGIResponse();
        foreach ($response->headers as $name => $value)
            $scgi_response->add_header($name, $value);
        $scgi_response->set_body($response->content.ob_get_clean());

        return $scgi_response;
    }
}

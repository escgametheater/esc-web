<?php
/**
 * Middleware base class
 *
 * @package http
 */

abstract class Middleware
{

    public $processResponseOnServerError  = false;

    /**
     * Request processing
     * @param string $url
     * @return string
     */
     public function process_request(Request $request) {}

    /**
     * Response processing
     * @param string $url
     * @return string
     */
     public function process_response(Request $request, HttpResponse $response) {}
}

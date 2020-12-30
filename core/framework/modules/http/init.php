<?php
/**
 * Http init
 *
 * @package http
 */

class Http404 extends Exception {

    protected $is_ajax = false;

    public function __construct($message = 'Not Found', $is_ajax = false)
    {
        parent::__construct($message, HttpResponse::HTTP_NOT_FOUND);
        $this->is_ajax = $is_ajax;
    }

    public function is_ajax()
    {
        return $this->is_ajax;
    }
}
class HttpDenied extends Exception {

    public function __construct($message = 'Access Denied', $code = HttpResponse::HTTP_UNAUTHORIZED, $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}

require "request.php";
require "responses.php";
require "http.php";
require "funcs.php";
require "ipv6.php";
require "middleware.php";

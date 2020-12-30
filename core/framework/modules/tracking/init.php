<?php
/**
 * Created by PhpStorm.
 * User: ccarter
 * Date: 5/19/18
 * Time: 11:05 PM
 */
require "middleware.php";

Http::register_middleware(new TrackingMiddleware());
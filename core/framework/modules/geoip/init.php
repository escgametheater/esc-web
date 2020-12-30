<?php
/**
 * Created by PhpStorm.
 * User: ccarter
 * Date: 2/21/18
 * Time: 5:19 PM
 */

Modules::uses(Modules::MANAGERS);

require "middleware.php";
Http::register_middleware(new GeoIpMapperMiddleware());


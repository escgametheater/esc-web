<?php
/**
 * Auth init
 *
 * @package auth
 */

Modules::uses(Modules::CACHE);
Modules::uses(Modules::MANAGERS);
require "geo.php";
require "auth.php";
require "user.php";
require "session.php";
require "guest.php";

if (Modules::is_loaded('http')) {
    require "http-funcs.php";
    require "middleware.php";
    Http::register_middleware(new AuthMiddleware());
}

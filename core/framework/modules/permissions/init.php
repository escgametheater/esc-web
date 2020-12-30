<?php
/**
 * Auth init
 *
 * @package auth
 */

Modules::uses(Modules::AUTH);
require "permissions.php";
require "rights.php";

if (Modules::is_loaded('http')) {
    require "middleware.php";
    Http::register_middleware(new PermissionsMiddleware());
}

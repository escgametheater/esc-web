<?php
/**
 * Debug
 *
 * @package debug
 */

require "funcs.php";

if (Modules::is_loaded('http')) {
    require "middleware.php";
    Http::register_middleware(new DebugMiddleware());
}

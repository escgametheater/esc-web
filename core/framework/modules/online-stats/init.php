<?php
/**
 * Online Stats module init
 *
 * @package onlne-stats
 */

Modules::uses(Modules::CACHE);
require "online-stats.php";

if (Modules::is_loaded('http')) {
    require "middleware.php";
    Http::register_middleware(new OnlineStatsMiddleware());
}

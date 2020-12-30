<?php

define('DEFAULT_CACHE_TIME', 60 * 5);
define('CACHE_REFRESH_TIME', 60);
define('CACHE_LOCK_TIME', 20);

require "memcache.php";
require "disk.php";

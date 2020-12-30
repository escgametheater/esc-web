<?php

$sec = 'Memcache';

/*
 * Various Tests for the memcache caching
 */
require_once "${FRAMEWORK_DIR}/modules/cache/init.php";
require_once "${FRAMEWORK_DIR}/modules/cache/backends/memcache.php";

$servers = ['127.0.0.1:11211'];
$c = new MemCacheBackend($servers);

require "base.php";

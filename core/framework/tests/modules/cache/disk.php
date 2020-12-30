<?php

$sec = 'Disk Cache';

/*
 * Various Tests for the memcache caching
 */
require_once "${FRAMEWORK_DIR}/modules/cache/backends/disk.php";

$test_key = 'test-cache';

$cache_directory = sys_get_temp_dir() . 'test-cache';
$c = new DiskCache($cache_directory);

require "base.php";

/*
 * Lock Test
 * file locked manually, lock should fail
 *
 */

$file = new File("${cache_directory}/${test_key}-lock");
$file->open('w');
$file->lockwrite();

$r = $c->lock($test_key);
simple_test($r == false, "$sec Lock, file locked manually, lock should fail");
$file->close();

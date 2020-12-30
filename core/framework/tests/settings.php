<?php

define('TIME_NOW', microtime(true));

$CONFIG = [];

$FRAMEWORK_DIR = getcwd().'/..';
$WEBSITE_ROOT   = '/Users/osso/Documents/Mangahelpers/Website Root/var';

$CONFIG['cache_directory'] = "${WEBSITE_ROOT}/cache";

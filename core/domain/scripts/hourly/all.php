<?php
/**
 * Daily maintenance script
 *
 * @package maintenance
 */

if (!defined('INIT')) {
    // Change current work directory to project root
    chdir('../..');
    define('WEB_APP', 'scripts');
    define('APP_DIRECTORY', getcwd());
    // Does initialisation
    require "../init.php";
}

global $CONFIG;
$request = make_cli_request($CONFIG);

echo "executing hourly tasks...\n";

require "regenerate-kpis.php";

echo "end of hourly tasks...\n";

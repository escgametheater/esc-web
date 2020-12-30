<?php
/**
 * Maintenance script (to run daily)
 *
 * @package maintenance
 */

if (!defined('INIT')) {
    // Change current work directory to project root
    chdir('../..');

    // Does initialisation
    require "./init.php";
}

echo "Cleaning login attempts...\n";

$timecut = (int)microtime(true) - 86400;  // 1 day
LoginAttemptsManager::objects()
    ->filter(Q::Lt('post_date', date(SQL_DATETIME, $timecut)))
    ->delete();

echo "Cleaned all old login attempts\n";

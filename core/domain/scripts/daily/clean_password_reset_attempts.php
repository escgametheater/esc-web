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

echo "Cleaning password reset attempts...\n";

$timecut = (int)microtime(true) - 86400 * 2; // 2 day
PasswordResetAttemptsManager::objects()
    ->filter(Q::Lt('post_date', date(SQL_DATETIME, $timecut)))
    ->delete();

echo "Cleaned all old password reset attempts\n";

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

require_once "${FRAMEWORK_DIR}/managers/uploads.php";

echo "Cleaning uploads...\n";

$timecut = (int)microtime(true) - 86400 * 2; // 2 days
$uploads = UploadsManager::objects()
    ->filter(Q::Lt('post_date', date(SQL_DATETIME, $timecut)))
    ->get_list(DBField::ID);

foreach ($uploads as $u) {
    echo 'found: '.$u['id']."\n";
    UploadsHelper::delete_upload($u['id']);
}

echo "Cleaned all uploads\n";

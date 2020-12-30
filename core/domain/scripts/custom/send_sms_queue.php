<?php
/**
 * Created by PhpStorm.
 * User: christophercarter
 * Date: 6/15/18
 * Time: 1:12 PM
 */

if (!defined('INIT')) {
    // Change current work directory to project root
    chdir('../..');
    define('WEB_APP', 'scripts');
    define('APP_DIRECTORY', getcwd());
    // Does initialisation
    require "../init.php";
}

echo "* Fetching SMS Queue...\n";

global $CONFIG;
$request = make_cli_request($CONFIG);

$smsManager = $request->managers->sms();

$messages = $smsManager->getUnsentMessagesByScheduleTime($request, $request->getCurrentSqlTime());

$countSent = 0;

foreach ($messages as $sms) {
    $smsManager->triggerSendSmsTask($sms);
    $countSent++;
}

echo "* Triggered send for {$countSent} messages\n";

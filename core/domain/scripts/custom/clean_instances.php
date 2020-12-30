<?php
/**
 * Created by PhpStorm.
 * User: christophercarter
 * Date: 7/12/18
 * Time: 4:39 PM
 */

if (!defined('INIT')) {
    // Change current work directory to project root
    chdir('../..');
    define('WEB_APP', 'scripts');
    define('APP_DIRECTORY', getcwd());
    // Does initialisation
    require "../init.php";
}

echo "* Running Instance Cleanup ...\n";

global $CONFIG;
$request = make_cli_request($CONFIG);

$hostsInstancesManager = $request->managers->hostsInstances();
$gamesInstancesManager = $request->managers->gamesInstances();


$hostInstances = $hostsInstancesManager->getExpiredHostInstances($request);

$count = 0;

$currentRunTime = $request->getCurrentSqlTime();

foreach ($hostInstances as $hostInstance) {
    $count++;
    $hostsInstancesManager->stopHostInstance($request, $hostInstance, HostsInstancesManager::EXIT_STATUS_TIMED_OUT);
    echo "* Ended Host Instance: {$hostInstance->getPk()}\n";
}

$gameInstances = $gamesInstancesManager->getExpiredGameInstancesForClosedHostInstances($request);
foreach ($gameInstances as $gameInstance) {
    echo "* Processing Orphaned Game Instance: {$gameInstance->getPk()}\n";
    $gamesInstancesManager->stopGameInstance($request, $gameInstance, GamesInstancesManager::EXIT_STATUS_TIMED_OUT);
}

echo "* Cleaned up {$count} host instances\n";

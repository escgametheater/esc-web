<?php

declare(ticks = 1);

$currentPath = getcwd();

define('WEB_APP', 'tasks');
define('APP_DIRECTORY', dirname(__FILE__).'/..');

// Change current work directory to project root
chdir(dirname(__FILE__).'/..');

// Does initialisation
require "../init.php";

Modules::uses(Modules::TASKS);

$mgr = new GearmanPearManager();
$mgr->run();

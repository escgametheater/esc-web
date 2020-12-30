<?php
/**
 * Start scgi app server
 *
 * @version 1
 * @package scripts
 */

global $CONFIG;

// Change current work directory to project root
chdir('..');

// Does initialisation
require "./init.php";

require "${FRAMEWORK_DIR}/scgi/handler.php";

// Handle request
$server = new SCGIHandler($CONFIG['scgi_address']);
$server->runLoop();

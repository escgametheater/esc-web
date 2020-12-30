<?php
/**
 * Index Page (website entrance point)
 *
 * @package webroot
 */

// Change current work directory to core root
chdir('..');

define('WEB_APP', 'api');

// Does initialisation
require "./init.php";

require "$FRAMEWORK_DIR/handlers/cgi/handler.php";

// Handle request
handler();

<?php
/**
 * Proxy for framework run tasks
 *
 * @package scripts
 */

if (!defined('INIT')) {
    // Change current work directory to project root
    chdir('../..');

    // Does initialisation
    require "./init.php";
}

require "$FRAMEWORK_DIR/scripts/run_tasks.php";

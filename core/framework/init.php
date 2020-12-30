<?php
/**
 * Initialisation
 */

if (!isset($FRAMEWORK_DIR))
    $FRAMEWORK_DIR = $_ENV['FRAMEWORK_DIR'];

require "${FRAMEWORK_DIR}/core/modules.php";
require "${FRAMEWORK_DIR}/core/settings.php";

// Load functions
require "${FRAMEWORK_DIR}/func/admin.php";
require "${FRAMEWORK_DIR}/func/files.php";
require "${FRAMEWORK_DIR}/func/array.php";
require "${FRAMEWORK_DIR}/func/global.php";
require "${FRAMEWORK_DIR}/func/html.php";
require "${FRAMEWORK_DIR}/func/time.php";
require "${FRAMEWORK_DIR}/func/strings.php";
require "${FRAMEWORK_DIR}/func/validators.php";


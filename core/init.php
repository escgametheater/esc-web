<?php
/**
 * Website Initialisation
 * It loads:
 * 1) locally user defined settings (conf/local-settings.php)
 * 2) globally user defined settings (conf/settings.php)
 * 3) the framework
 *
 */
// Initialize Configuration

define('TIME_NOW', microtime(true));

$CONFIG = [];


// SQL Tables
require "conf/class/data-access.php";


// Configuration Class + Base Array Access Object
require "conf/class/esc-configuration.php";

// General settings
require "conf/default-settings.php";

$localDomainSettings = "conf/dev-settings.php";
if (is_file($localDomainSettings)) {
    require $localDomainSettings;
}



$prodSettings = "conf/prod-settings.php";
if (is_file($prodSettings)) {
    require $prodSettings;
}

// Get global settings for app
// they override the default settings
// and are unique to the app itself
$settings = "{$APP_DIR}/conf/settings.php";
if (is_file($settings))
    require $settings;

// Get local dev settings
// they override the default and global settings
// and are provided by the user
$local_settings = "{$APP_DIR}/conf/dev-settings.php";
if (is_file($local_settings)) {
    require $local_settings;
}

$appProdSettings = "{$APP_DIR}/conf/prod-settings.php";
if (is_file($appProdSettings)) {
    require $appProdSettings;
}

// Post config, compute config variables that depend on the local settings
require "conf/computed-settings.php";

// Initialise framework
require "{$FRAMEWORK_DIR}/init.php";

// Packages installed by composer
$vendorAutoloadFile = "{$PROJECT_DIR}/core/domain/vendor/autoload.php";
if (is_file($vendorAutoloadFile))
    require $vendorAutoloadFile;

// Request handling defined here
Modules::uses(Modules::HTTP);

/**
 * Redirect spammers to anti spam sites.
 */
Modules::uses(Modules::REDIRECTS);
// Caching
Modules::uses(Modules::CACHE);
// DB
Modules::uses(Modules::DB);
// Profiling
Modules::uses(Modules::PROFILING);
// Debug
Modules::uses(Modules::DEBUG);
// Error handling
Modules::uses(Modules::ERROR_HANDLING);
// Logs
Modules::uses(Modules::LOGS);
// DB Entities
Modules::uses(Modules::ENTITIES);
// Query Generator
Modules::uses(Modules::MANAGERS);
// GeoIpMapping
Modules::uses(Modules::GEOIP);
// Auth
Modules::uses(Modules::AUTH);
// Permissions
Modules::uses(Modules::PERMISSIONS);
// i18n
Modules::uses(Modules::I18N);
// Session / Tracking
Modules::uses(Modules::TRACKING);
// CSRF
Modules::uses(Modules::CSRF);
// Anonymous full page caching
//Modules::uses(Modules::ANONYMOUS_CACHE);
// Forms
Modules::uses(Modules::FORMS);

// Tasks
if ($CONFIG[ESCConfiguration::TASKS])
    Modules::uses(Modules::TASKS);

// Template system
Modules::uses(Modules::TWIG);


// Error reporting
Modules::uses(Modules::SENTRY);
// Elastic search
//Modules::uses(Modules::ELASTICSEARCH);
// AWS
Modules::uses(Modules::S3);
// Beta specific features: requires login to all users
//if (isset($CONFIG[ESCConfiguration::BETA]))
//    Modules::load(Modules::BETA);

// Helpers
Modules::load_helper(Helpers::CONTENT);
Modules::load_helper(Helpers::UPLOADS);
Modules::load_helper(Helpers::BITFIELD);
Modules::load_helper(Helpers::FILE);
Modules::load_helper(Helpers::IMAGE);

foreach ($CONFIG[ESCConfiguration::MANAGERS] as $file)
    Modules::load_manager($file);

$init_time = microtime(true) - TIME_NOW;

chdir(APP_DIRECTORY);

define('INIT', true);

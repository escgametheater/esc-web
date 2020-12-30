<?php
/**
 * Website Settings.
 *
 */

// Error level
// Errors slow down the website
// So we want all of them reported
error_reporting(E_ALL);

define('CONTENT_DIR', 'controllers');
define('MAX_INT', 2147483647);
define('MEDIUMTEXT', 16777215);
define('TEXT', 65535);

define('SQL_DATETIME', 'Y-m-d H:i:s');
define('SQL_DATE', 'Y-m-d');
define('RSS_DATETIME', 'r');
define('COOKIE_PATH', '/');


define('DONT_CACHE', 0);
define('ONE_MINUTE', 60);
define('FIVE_MINUTES', ONE_MINUTE * 5);
define('FIFTEEN_MINUTES', FIVE_MINUTES * 3);
define('HALF_AN_HOUR', ONE_MINUTE * 30);
define('ONE_HOUR', ONE_MINUTE * 60);
define('ONE_DAY', ONE_HOUR * 24);
define('ONE_WEEK', ONE_DAY * 7);
define('TWO_WEEKS', ONE_WEEK * 2);
define('ONE_MONTH', ONE_DAY * 30);
define('TWO_MONTH', ONE_MONTH * 2);
define('ONE_YEAR', ONE_MONTH * 12);
define('DAY_FORMAT', 'Y-m-d');

// Should the stuff below be stored in the config array ?
// Does it matter ? ohohohohohohohohohohohohohohohohohohohoh
// ohohohohohohohohohohohohohohohohohohohohohohohohohohohohoh
define('COMMENTS_DEFAULT_ENTRIES', 400);

define('SELECT_DEFAULT_LIMIT', 2000);
define('CHOICES_MAX_LIMIT', 2000);
define('RSS_LIMIT', 30);
define('DEFAULT_PERPAGE', 40);
define('ADMIN_PERPAGE', 40);
define('SALT_LENGTH', 3);

define('DEFAULT_CACHE_TIME', 60 * 5);

// Entries that expire in less than 30s
// will be refreshed
define('CACHE_REFRESH_TIME', 30);
define('CACHE_LOCK_TIME', 20);

define('MAX_DEBUG_QUERIES_LOG', 1000);

/*
 * Paths
 */
$WEBSITE_ROOT = '/home/escweb';
// Todo: Fix trailing slash in PROJECT DIR
$PROJECT_DIR = "${WEBSITE_ROOT}/";
$FRAMEWORK_DIR = "${WEBSITE_ROOT}/core/framework";
$APP_DIR = APP_DIRECTORY;

$CONFIG['var_root'] = '/var/escweb';

// Server name
$CONFIG['server_name'] = gethostname();
$CONFIG['debug'] = false;

// Dev Env?
$CONFIG[ESCConfiguration::IS_DEV] = false;

/*
 * Website Settings
 */
$CONFIG[ESCConfiguration::WEBSITE_NAME] = 'ESC Games';
$CONFIG[ESCConfiguration::WEBSITE_DOMAIN] = 'localhost:4000';
$CONFIG[ESCConfiguration::EMAIL_DOMAIN] = 'playesc.com';
$CONFIG[ESCConfiguration::COMPANY_NAME] = 'Eddie\'s Social Club, LLC';

/*
 * Default Meta Settings
 */

$CONFIG[ESCConfiguration::DEFAULT_PAGE_DESCRIPTION] = 'ESC Games';
$CONFIG[ESCConfiguration::DEFAULT_PAGE_IMAGE] = '/static/images/beta/blog_gfx1.jpg';
$CONFIG[ESCConfiguration::DEFAULT_PAGE_CREATE_DATE] = '2015';
$CONFIG[ESCConfiguration::PAGE_COPYRIGHT] = 'ESC Games 2018';
$CONFIG[ESCConfiguration::PAGE_FBAPP_ID] = '';

/*
 * SQL settings
 */
define('SQLN_SITE', 1);
define('SQLN_SLAVE', 1);

define ('SQLN_BI', 99);

$CONFIG['sql'] = [
    SQLN_SITE => [
        'backend' => 'mysql',
        'host' => 'p:db',
        'db' => 'esc',
        'user' => '<<<REDACTED>>>',
        'pass' => '<<<REDACTED>>>',
        'port' => 3306,
        'socket' => null,
        'pem' => null
        //'socket' => '/var/run/mysqld/mysqld.sock',
    ],
    SQLN_BI => [
        'backend' => 'mysql',
        'host' => 'p:db',
        'db' => 'esc',
        'user' => '<<<REDACTED>>>',
        'pass' => '<<<REDACTED>>>',
        'port' => 3306,
        'socket' => null,
        'pem' => null
        //'socket' => '/var/run/mysqld/mysqld.sock',
    ]
];

/*
 * Logs
 */
define('LOG_DEFAULT', 0);

$CONFIG['log_categories'] = [
    LOG_DEFAULT => 'error.log',
];
$CONFIG['log_class'] = 'FileLog';

/*
 * Template system settings
 */
$CONFIG['templ'] = [
    'debug' => false,
    'caching' => true,
    'compile_check' => true,
];

/*
 * Auth module
 */
$CONFIG['auth'] = 'DefaultAuth';
$CONFIG['secret'] = '<<<REDACTED>>>';
$CONFIG['js_salt'] = '<<<REDACTED>>>';
$CONFIG['cookie_domain'] = 'esc.games';
$CONFIG['cookie_pre'] = 'esc_';
$CONFIG['cookie_secure'] = false;

// These values must match forums settings
// This one not so much, we can have shorter or longer
// sessions on the website
$CONFIG['session_timeout'] = 30 * 60; // 15 minutes
$CONFIG['guest_timeout'] = 60 * 60 * 6; // 6 Hours

/*
 * Caching
 */

define('GNS_ROOT', 'esc');

// Possible values: disk, memcache, dummy
$CONFIG['cache'] = 'redis';
$CONFIG['local_cache'] = 'dummy';

// Comma separated list of servers
// Format: server1:port1,server2:port2
$CONFIG['memcache'] = ['127.0.0.1:11211'];

// Compress data in memcached
$CONFIG['memcache_compress'] = true;


$CONFIG['redis'] = [
    'master' => [
        'host' => 'redis',
        'port' => '6379'
    ]
];

// Full page html caching time
// (for anonymous users)
$CONFIG['html_cache_time'] = 60 * 5;

// Stats server
$CONFIG['stats_enable'] = true;
$CONFIG['stats_online_cut'] = 900;

$CONFIG['apps'] = [
    "play",
    "api",
    "www",
    "develop",
    "go",
    "images"
];

$CONFIG['hosts'] = [];

foreach ($CONFIG['apps'] as $app) {
    $CONFIG['hosts'][$app] = '';
}

$CONFIG['managers'] = [
    // Settings
    'rights',
    'settings',
    'usergroups',
    'locale',
    'vars',
    'flashmessages',
    'email',

    // Platform - Users
    'users',
    'tracking',
    'activities',
    'activations',
    'uploads',
    'hosts-locations',
    'hosts-instances',
    'hosts-assets',
    'hosts-builds',
    'sdk',
    'sdk-assets',
    'sdk-builds',
    'games',
    'games-data',
    'games-mods',
    'games-builds',
    'games-assets',
    'games-instances',
    'games-players',
    'organizations',
    'sms',
    'applications',
    'incentives',
    'orders',
    'payments',
    'payments-services',
    'payouts',
    'accounting',
    'income',
    'urls',
    'images',
    'sso',
    'stats',
    'services-access',
    'coins'
];

// User messages types
define('MSG_INFO', 1);
define('MSG_SUCCESS', 2);
define('MSG_FAILURE', 3);
define('MSG_WARNING', 4);
define('MSG_CONFIRMATION', 5);
define('MSG_ALERT', 6);
define('MSG_MODAL', 7);

// Comic Statuses
define('COMIC_ONGOING', 1);
define('COMIC_CANCELLED', 2);
define('COMIC_FINISHED', 3);
define('COMIC_ON_HOLD', 4);
define('COMIC_ONE_SHOT', 5);
// Comic Types
define('COMIC_TYPE_COMIC', 1);
define('COMIC_TYPE_GRAPHICNOVEL', 2);
define('COMIC_TYPE_MANGA', 3);
define('COMIC_TYPE_STRIP', 4);
// Reading Direction
define('COMIC_READ_LEFT_TO_RIGHT', 'l');
define('COMIC_READ_RIGHT_TO_LEFT', 'r');
define('COMIC_READ_TOP_TO_BOTTOM', 't');
define('COMIC_READ_BOTTOM_TO_TOP', 'b');

define('IS_ACTIVE', 1);
define('TYPE', 'type');
define('LIMIT', 'limit');
define('TEMPLATE_NAME', 'template_name');
// Translators Releases
define('RELEASE_TRANS', 1);
define('RELEASE_TIP', 2);
define('RELEASE_ISSUE', 3);
define('RELEASE_NEWS', 4);
define('RELEASE_TRANSCRIPTION', 5);

// Contact email for feeds
$CONFIG['rss_email'] = 'mail@example.com';
// URL path for feeds
$CONFIG['rss_link'] = '/news/rss';
// Contact email for error mails
$CONFIG['admins_email'] = 'Admins <mail@example.com>';

$CONFIG[ESCConfiguration::EMAIL_CONTENT_VIOLATION] = 'mail@example.com';

/*
 * Website Sections
 */

define('HOMEPAGE', '/');


/*
 * RSS Feeds
 */

// Feeds
$CONFIG['feeds'] = ['news'];

/*
 * Comments Types
 */

define('TABLE', 'table');

// User groups for alerts
// on new group/translator application
$CONFIG['banned_group'] = 999;
$CONFIG['banned_special_group'] = 998;
$CONFIG['awaiting_group'] = 2;
$CONFIG['registered_group'] = 3;

$CONFIG['show_ads'] = true;
$CONFIG['superadmins'] = '6,7';
$CONFIG['staff_groups'] = '4,5';
$CONFIG['slow_page_cut'] = 10;
$CONFIG['default_magazine'] = 501;
$CONFIG['default_publisher'] = 174;
$CONFIG['manga_main_forum'] = 10;
$CONFIG['artists_main_forum'] = 11;
// Date
$CONFIG['date_format'] = '%b %e, %Y';
$CONFIG[ESCConfiguration::DATE_FORMAT_SQL_POST_DATE] = SQL_DATETIME;
$CONFIG['short_date_format'] = '%b %e';
$CONFIG['time_format'] = '%H:%M';

// Default page headers
// xhtml+xml has issues with ads also there's some stuff that work with js
// like innerHtml thuse breaking most of the jquery functions
//$CONFIG['default_content_type'] = 'application/xhtml+xml';
$CONFIG['default_content_type'] = 'text/html';
$CONFIG['default_charset'] = 'utf-8';
$CONFIG['default_lang'] = 'en';

$CONFIG['domains_with_simple_markup'] = [

];

// Media
$CONFIG['media_key'] = '<<<REDACTED>>>';
$CONFIG[ESCConfiguration::DIR_UPLOAD] = "${WEBSITE_ROOT}/uploads";
$CONFIG[ESCConfiguration::DIR_MEDIA] = "${WEBSITE_ROOT}/media";
$CONFIG['media_prefix'] = '';

// SCGI Server
$CONFIG['scgi_address'] = 'tcp://127.0.0.1:8000';
// Gearman
$CONFIG['gearman'] = ['tasksworker:4730'];

// Protection from csrf
$CONFIG[ESCConfiguration::ALLOWED_REFERRERS] = '127.0.0.1:4000 192.168.0.11:4000';

/*
 * Disable html caching
 * didn't change much on the load and makes us use more memory
 * cause we have to parse ads on the cached html
 * + there's less places to bug in if it's desactived
 */
$CONFIG['enable_anonymous_cache'] = false;

/*
 * Send json rpc to a profiling server
 * in order to check the most heavy pages/queries
 */
$CONFIG['enable_profiling'] = true;

/*
 * Backend for Query class (managers module)
 */
$CONFIG['query_backend'] = 'sql';
$CONFIG['run_etls'] = false;

/*
 * Imagemagick path for thumbnails creation
 */
$CONFIG['convert_path'] = '/usr/bin/convert';

/*
 * Switch for compress js/css
 */
$CONFIG[ESCConfiguration::RAW_ASSETS] = false;
$CONFIG[ESCConfiguration::RAW_CSS] = true;
$CONFIG[ESCConfiguration::RAW_JS] = true;

/*
 * Redirects
 */

$CONFIG[ESCConfiguration::REDIRECTS] = [
    '/wp-login.php' => 'http://spam.abuse.net/',
    '/xmlrpc.php' => 'http://spam.abuse.net/'
];


/*
 * Email handling
 */
$CONFIG['send_emails'] = true;

/*
 * Other Settings
 */
$CONFIG['enable_media_serving'] = false;

/*
 * Google Analytics
 */

$CONFIG['ga_id'] = [
    'live' => '',
    'test' => ''
];

/*
 * Facebook
 */
$CONFIG['facebook_appid'] = '';
$CONFIG['facebook_key'] = '';

/*
 * Sentry
 */

// Dev Settings
// $CONFIG['sentry_server'] = '<<<REDACTED>>>';

$CONFIG['sentry_server'] = '';

/*
 * Elastic search
 */
$CONFIG['elastic_search'] = [
    'servers' => [
        'host' => 'localhost',
        'port' => 9200
    ],
];

/*
 * CloudFlare
 */

$CONFIG['cloudflare'] = [
    'live' => [
        'auth-email' => 'mail@example.com',
        'auth-key' => '<<<REDACTED>>>'
    ],
    'test' => [
        'auth-email' => 'mail@example.com',
        'auth-key' => '<<<REDACTED>>>'
    ]
];

/*
 * AWS
 */

$CONFIG['aws'] = [
    'bucket_prefix' => '',
    'key' => '<<<REDACTED>>>',
    'secret_key' => '<<<REDACTED>>>',
    'endpoint' => 'http://awshost:9000',
    'region' => 'us-east-1',
];

$CONFIG[ESCConfiguration::TASKS] = true;

$CONFIG['mailgun'] = [
    'api_key' => '<<<REDACTED>>>',
    'base_url' => '<<<REDACTED>>>',
    'email_domain' => '<<<REDACTED>>>',
    'local_email_domain' => '<<<REDACTED>>>',
];

$CONFIG['tasks_worker_log_file'] = '/dev/stdout';
$CONFIG['extract_worker_log_file'] = '/dev/stdout';


$CONFIG['stripe'] = [
    'test' => [
        'public_key' => '<<<REDACTED>>>',
        'secret_key' => '<<<REDACTED>>>'
    ],
    'live' => [
        'public_key' => '<<<REDACTED>>>',
        'secret_key' => '<<<REDACTED>>>'
    ]
];

$CONFIG['twilio'] = [
    'test' => [
        'sid' => '<<<REDACTED>>>',
        'token' => '<<<REDACTED>>>',
        'from_number' => '+12125551212',
    ],
    'live' => [
        'sid' => '<<<REDACTED>>>',
        'token' => '<<<REDACTED>>>',
        'from_number' => '<<<REDACTED>>>',
    ]
];

// Josh - ESC - qa2 keyset
$CONFIG['pubnub'] = [
    'publish_key' => '<<<REDACTED>>>',
    'subscribe_key' => '<<<REDACTED>>>',
    'secret_key' => '',
];

$CONFIG['sso'] = [
    'google' => [
        'client_id' => '<<<REDACTED>>>',
        'client_secret' => '<<<REDACTED>>>',
    ],
    'facebook' => [
        'client_id' => '',
        'client_secret' => '',
    ]
];

$CONFIG['mixpanel'] = [
    'client_id' => '<<<REDACTED>>>'
];

/**
 * Map browsers lang to our internal prefixes
 * used by i18n middleware to know which language to display
 */
$CONFIG['languages_map'] = [
    'it-ch' => 'it',
    'en-us' => 'en',
    'en-au' => 'en',
    'en-bz' => 'en',
    'en-ca' => 'en',
    'en-ie' => 'en',
    'en-jm' => 'en',
    'en-zn' => 'en',
    'en-ph' => 'en',
    'en-za' => 'en',
    'en-uk' => 'en',
    'en-zw' => 'en',
    'fr-be' => 'fr',
    'fr-ca' => 'fr',
    'fr-fr' => 'fr',
    'fr-lu' => 'fr',
    'fr-mc' => 'fr',
    'fr-ch' => 'fr',
    'pt-br' => 'br'
];


/**
 * Pages that are to be stored in the html cache
 */
$CONFIG['html_cache_pages'] = [
    '/'                               => 1,
];

/**
 * Bootstrap the public pages array
 */
$CONFIG['beta_public_pages'] = [];


/**
 * PROD
 */

$CONFIG['is_prod'] = false;


// DEFAULT RELATIVE PATH URLS FOR SCRIPTS
$CONFIG[ESCConfiguration::STATIC_URL] = '/static/';
$CONFIG[ESCConfiguration::MEDIA_URL] = '/media/';
$CONFIG[ESCConfiguration::IMAGES_URL] = $CONFIG[ESCConfiguration::STATIC_URL].'images/';

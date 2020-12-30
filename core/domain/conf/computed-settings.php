<?php

$https_port = getenv('HTTPS_PORT');
//if ($https_port && $https_port != '84' and $https_port != '85') {
//	$CONFIG[ESCConfiguration::WEBSITE_DOMAIN] .= ':' . $https_port;
//}

global $APP_DIR;
$WEB_APP = WEB_APP;

$CONFIG[ESCConfiguration::ALLOWED_REFERRERS] .= ' ' . $CONFIG[ESCConfiguration::WEBSITE_DOMAIN];
$VAR_ROOT = $CONFIG['var_root'];
$CONFIG[ESCConfiguration::DIR_PROJECT] = $PROJECT_DIR;
$CONFIG[ESCConfiguration::DIR_FRAMEWORK] = $FRAMEWORK_DIR;
$CONFIG[ESCConfiguration::STATIC_URL] = '/static/';
$CONFIG[ESCConfiguration::DIR_LOG] = "${VAR_ROOT}/log";
$CONFIG[ESCConfiguration::DIR_MEDIA] = "${VAR_ROOT}/media";
$CONFIG[ESCConfiguration::DIR_UPLOAD] = "${VAR_ROOT}/uploads";
$CONFIG['cache_directory'] = "${VAR_ROOT}/cache/${WEB_APP}";
$CONFIG['templ']['dir'] = "{$APP_DIR}/templates";

$CONFIG['templ']['compiled_dir'] = "${VAR_ROOT}/templates/${WEB_APP}";

$CONFIG[ESCConfiguration::CDN_URL] = $CONFIG[ESCConfiguration::STATIC_URL];

// Computed Sibling Subdomains on the currently computed domain
if (isset($_SERVER['HTTP_HOST']))
    $host = $_SERVER['HTTP_HOST'];
else
    $host = 'qa1.playesc.com';

if ($WEB_APP == 'scripts') {
    $host = 'www.esc.games';
}

$rawHostParts = explode('.', $host);

foreach ($CONFIG['apps'] as $app) {
    $hostParts = $rawHostParts;
    if (in_array($hostParts[0], $CONFIG['apps']))
        $hostParts[0] = $app;
    else
        array_unshift($hostParts, $app);

    $CONFIG['hosts'][$app] = join('.', $hostParts);
}

$CONFIG[ESCConfiguration::WEBSITE_DOMAIN] = $CONFIG['hosts']['www'];

if (in_array($rawHostParts[0], $CONFIG['apps']))
    unset($rawHostParts[0]);

$rootHost = join('.', $rawHostParts);

$CONFIG[ESCConfiguration::COOKIE_DOMAIN] = $rootHost;

$CONFIG['root_host'] = $rootHost;
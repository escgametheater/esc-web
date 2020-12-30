<?php
/**
 * Created by PhpStorm.
 * User: christophercarter
 * Date: 6/8/18
 * Time: 12:01 PM
 */

$CONFIG['sections'] = [
    // Default content/pages wrapper
    'default' => 'Main',
    'v1' => 'Api-V1-Controller'
];

// URLs
$CONFIG[ESCConfiguration::STATIC_URL] = '/static/';
$CONFIG[ESCConfiguration::MEDIA_URL] = '/media/';
$CONFIG[ESCConfiguration::IMAGES_URL] = $CONFIG[ESCConfiguration::STATIC_URL].'images/';

$VAR_ROOT = $CONFIG['var_root'];
$CONFIG[ESCConfiguration::DIR_UPLOAD] = "${VAR_ROOT}/uploads";
$CONFIG[ESCConfiguration::DIR_MEDIA] = "${VAR_ROOT}/media";

$CONFIG['auth'] = 'ApiAuth';

$CONFIG['beta_public_pages'] = [
    '/v1/auth/request-token/' => [
        'is_public' => true,
        'wildcard' => false
    ],
    '/v1/auth/register-device/' => [
        'is_public' => true,
        'wildcard' => false
    ],
    '/v1/host-auto-update/latest-mac.yml' => [
        'is_public' => true,
        'wildcard' => false
    ],
    '/v1/host-auto-update/latest-win.yml' => [
        'is_public' => true,
        'wildcard' => false
    ],
    '/v1/host-auto-update/latest.yml' => [
        'is_public' => true,
        'wildcard' => false
    ],
    '/v1/host-auto-update/latest-file-' => [
        'is_public' => true,
        'wildcard' => true
    ],
    '/v1/host-auto-update/latest-installer-mac' => [
        'is_public' => true,
        'wildcard' => false
    ],
    '/v1/host-auto-update/latest-installer-win' => [
        'is_public' => true,
        'wildcard' => false
    ],
    '/v1/sms/inbound' => [
        'is_public' => true,
        'wildcard' => false
    ]
];
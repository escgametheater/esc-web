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
];

// URLs
$CONFIG[ESCConfiguration::STATIC_URL] = '/static/';
$CONFIG[ESCConfiguration::MEDIA_URL] = '/media/';
$CONFIG[ESCConfiguration::IMAGES_URL] = $CONFIG[ESCConfiguration::STATIC_URL].'images/';

$VAR_ROOT = $CONFIG['var_root'];
$CONFIG[ESCConfiguration::DIR_UPLOAD] = "${VAR_ROOT}/uploads";
$CONFIG[ESCConfiguration::DIR_MEDIA] = "${VAR_ROOT}/media";

$CONFIG['beta_public_pages'] = [
    '/' => [
        'is_public' => true,
        'wildcard' => true
    ],
    '/nyc' => [
        'is_public' => true,
        'wildcard' => true
    ],
    '/i/' => [
        'is_public' => true,
        'wildcard' => true
    ],
    '/cga/' => [
        'is_public' => true,
        'wildcard' => true
    ],
    '/api/' => [
        'is_public' => true,
        'wildcard' => true
    ],
    '/luxor' => [
        'is_public' => true,
        'wildcard' => true
    ],
    '/__createLeaderBoard' => [
        'is_public' => true,
        'wildcard' => true
    ]
];
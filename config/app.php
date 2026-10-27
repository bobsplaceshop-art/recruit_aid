<?php
declare(strict_types=1);

/**
 * Recruit Aid - application configuration.
 * Per-deployment overrides live in config/local.php.
 */

return [
    'name'    => 'Recruit Aid',
    'version' => '1.0.0',

    'env'   => 'production',
    'debug' => false,
    'url'   => '',

    'key' => '',

    'timezone' => 'Africa/Nairobi',
    'locale'   => 'en',

    'session' => [
        'name'              => 'recruitaid_session',
        'lifetime'          => 7200,
        'remember_lifetime' => 60 * 60 * 24 * 30,
        'cookie_path'       => '/',
        'cookie_domain'     => '',
        'cookie_secure'     => false,
        'cookie_httponly'   => true,
        'cookie_samesite'   => 'Lax',
    ],

    'pagination' => [
        'default' => 25,
        'max'     => 100,
    ],

    'uploads' => [
        'max_size_mb'      => 8,
        'allowed_docs'     => ['pdf','doc','docx','odt','rtf','txt'],
        'allowed_images'   => ['png','jpg','jpeg','webp','svg'],
        'allowed_archives' => ['zip'],
        'branding_max_kb'  => 500,
    ],

    'demo' => [
        'auto_seed'     => true,
        'nightly_reset' => true,
        'banner_text'   => 'DEMO MODE - changes are reset nightly.',
    ],
];

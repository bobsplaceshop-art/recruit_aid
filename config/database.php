<?php
declare(strict_types=1);

/**
 * Recruit Aid - database configuration.
 * Per-deployment values live in config/local.php.
 */

return [
    'driver'    => 'mysql',
    'host'      => 'localhost',
    'port'      => 3306,
    'database'  => '',
    'username'  => '',
    'password'  => '',
    'charset'   => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'options'   => [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_STRINGIFY_FETCHES  => false,
        PDO::ATTR_PERSISTENT         => false,
    ],
];

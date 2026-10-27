<?php
declare(strict_types=1);

/**
 * Recruit Aid - mail configuration.
 * Real SMTP credentials go in config/local.php.
 * During local development, Laragon's Mailpit catches everything.
 */

return [
    'driver'     => 'smtp',
    'host'       => '127.0.0.1',
    'port'       => 1025,
    'username'   => '',
    'password'   => '',
    'encryption' => '',
    'auth'       => false,
    'timeout'    => 20,
    'from_email' => 'no-reply@recruitaid.test',
    'from_name'  => 'Recruit Aid',
    'reply_to'   => '',
    'debug'      => 0,
];

<?php
declare(strict_types=1);

/**
 * Recruit Aid - front controller.
 * Every HTTP request enters here. Only /public is web-accessible.
 */

define('RA_APP_START', microtime(true));
define('RA_BASE_PATH', dirname(__DIR__));
define('RA_PUBLIC_PATH', __DIR__);

// ------------------------------------------------------------------
// PHP environment checks
// ------------------------------------------------------------------
if (PHP_SAPI === 'cli') {
    fwrite(STDERR, "Recruit Aid front controller is web-only.\n");
    exit(1);
}

if (PHP_VERSION_ID < 80200) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Recruit Aid requires PHP 8.2 or newer. Current: ' . PHP_VERSION);
}

$requiredExtensions = ['pdo', 'pdo_mysql', 'mbstring', 'openssl', 'json', 'fileinfo', 'ctype', 'tokenizer', 'dom', 'gd'];
foreach ($requiredExtensions as $ext) {
    if (!extension_loaded($ext)) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        exit("Recruit Aid cannot start: missing PHP extension '{$ext}'.");
    }
}

// ------------------------------------------------------------------
// Composer autoloader + local config
// ------------------------------------------------------------------
$autoload = RA_BASE_PATH . '/vendor/autoload.php';
if (!is_file($autoload)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Recruit Aid cannot start: vendor/autoload.php is missing. Run `composer install`.');
}
require $autoload;

$localConfig = RA_BASE_PATH . '/config/local.php';
if (!is_file($localConfig)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Recruit Aid cannot start: config/local.php is missing.');
}

// ------------------------------------------------------------------
// Boot and run
// ------------------------------------------------------------------
\CorporateAid\Core\App::boot(RA_BASE_PATH)->run();

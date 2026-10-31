<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from the command line.\n");
    exit(1);
}

$basePath = dirname(__DIR__);
$autoload = $basePath . '/vendor/autoload.php';
if (!is_file($autoload)) {
    fwrite(STDERR, "Missing vendor/autoload.php.\n");
    exit(1);
}
require $autoload;

$cliConfig = require $basePath . '/config/cli.php';
$database = new CorporateAid\Core\Database(CorporateAid\Core\DatabaseConfig::fromArray($cliConfig['database']));
$bus = new CorporateAid\Platform\Modules\EventBus($database);

$processed = $bus->processPending(100);
echo "Processed {$processed} events.\n";

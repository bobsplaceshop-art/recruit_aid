<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use CorporateAid\Core\App;
use CorporateAid\Platform\LiveSearch\LiveSearchService;

try {
    $app = App::boot(dirname(__DIR__));

    $service = $app->container()->get(LiveSearchService::class);

    if (!$service instanceof LiveSearchService) {
        throw new RuntimeException('Unexpected service instance.');
    }

    echo "[PASS] Application booted successfully.\n";
    echo "[PASS] LiveSearchService resolved successfully.\n";
    echo "[PASS] TenantContext dependency was accepted.\n";
    echo "\nAll bootstrap checks passed.\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "\n[FAIL] " . $e->getMessage() . "\n");
    fwrite(STDERR, $e->getFile() . ':' . $e->getLine() . "\n");
    exit(1);
}

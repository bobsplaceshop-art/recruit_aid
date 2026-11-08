<?php
declare(strict_types=1);

/**
 * Process queued module events using the application's registered listeners.
 *
 * Usage: php bin/process-events.php
 */

require __DIR__ . '/../vendor/autoload.php';

$app = CorporateAid\Core\App::boot(dirname(__DIR__));
$container = $app->container();

/** @var CorporateAid\Platform\Modules\EventBus $bus */
$bus = $container->get(CorporateAid\Platform\Modules\EventBus::class);

$processed = $bus->processPending(100);

echo "Processed {$processed} events.\n";

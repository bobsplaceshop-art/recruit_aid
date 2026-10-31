<?php
declare(strict_types=1);

// Use Windows paths for Laragon Terminal
require __DIR__ . '/vendor/autoload.php';

// Load config
$config = require __DIR__ . '/config/local.php';
$dbConfig = $config['database'];

$db = new CorporateAid\Core\Database(CorporateAid\Core\DatabaseConfig::fromArray($dbConfig));

// We need TenantContext. We'll mock a Session so TenantContext works.
$sessionConfig = ['name' => 'test', 'secure' => false, 'httponly' => true, 'samesite' => 'Lax'];
$session = new CorporateAid\Core\Session($sessionConfig);

$tenant = new CorporateAid\Core\TenantContext($session);
$repo = new CorporateAid\Recruit\Repositories\OrganisationRepository($db, $tenant);

echo "Calling countsByStatus()...\n";
$counts = $repo->countsByStatus();
print_r($counts);

echo "\nCalling paginate()...\n";
$result = $repo->paginate([], 1, 20);
echo "Total returned by paginate: " . $result['total'] . "\n";

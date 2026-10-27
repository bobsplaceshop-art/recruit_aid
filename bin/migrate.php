<?php
declare(strict_types=1);

/**
 * Recruit Aid — migration runner.
 * Usage (from Laragon Terminal or WSL):
 *   php bin/migrate.php
 *   php bin/migrate.php status
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from the command line.\n");
    exit(1);
}

$basePath = dirname(__DIR__);

$autoload = $basePath . '/vendor/autoload.php';
if (!is_file($autoload)) {
    fwrite(STDERR, "Missing vendor/autoload.php. Run: composer install\n");
    exit(1);
}
require $autoload;

// CLI scripts use the elevated user from config/cli.php,
// NOT the limited user that the web app uses.
$cliConfig = require $basePath . '/config/cli.php';
$dbConfig = $cliConfig['database'];

$dsn = sprintf(
    'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
    $dbConfig['host'],
    $dbConfig['port'],
    $dbConfig['database']
);

try {
    $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    fwrite(STDERR, "DB connection failed: " . $e->getMessage() . "\n");
    exit(1);
}

$migrator = new CorporateAid\Core\Migrator($pdo, $basePath . '/database/migrations');

$command = $argv[1] ?? 'run';

if ($command === 'status') {
    $migrator->ensureMigrationsTable();
    $applied = $migrator->applied();
    $available = $migrator->available();

    echo "Migration status:\n";
    foreach ($available as $file) {
        $mark = isset($applied[$file]) ? '[x]' : '[ ]';
        echo "  {$mark} {$file}\n";
    }
    echo "\nTotal: " . count($available) . " available, " . count($applied) . " applied.\n";
    exit(0);
}

if ($command !== 'run') {
    fwrite(STDERR, "Unknown command: {$command}\n");
    fwrite(STDERR, "Usage: php bin/migrate.php [run|status]\n");
    exit(1);
}

echo "Running migrations...\n";
try {
    $ran = $migrator->run();
    if (empty($ran)) {
        echo "Nothing to apply. All migrations are up to date.\n";
    } else {
        foreach ($ran as $file) {
            echo "  [applied] {$file}\n";
        }
        echo "\nDone. " . count($ran) . " migration(s) applied.\n";
    }
} catch (Throwable $e) {
    fwrite(STDERR, "ERROR: " . $e->getMessage() . "\n");
    exit(1);
}

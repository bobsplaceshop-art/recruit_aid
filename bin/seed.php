<?php
declare(strict_types=1);

/**
 * Recruit Aid — seeder runner.
 * Usage: php bin/seed.php
 *
 * Seeders are .sql files in database/seeders/. They are meant to be
 * idempotent (use INSERT ... ON DUPLICATE KEY UPDATE / INSERT IGNORE),
 * so running them multiple times is safe.
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

$seedersDir = $basePath . '/database/seeders';
$files = glob($seedersDir . '/*.sql') ?: [];
sort($files, SORT_NATURAL | SORT_FLAG_CASE);

if (empty($files)) {
    echo "No seeders found in database/seeders/.\n";
    exit(0);
}

echo "Running seeders...\n";

foreach ($files as $path) {
    $filename = basename($path);
    $sql = file_get_contents($path);
    if ($sql === false) {
        fwrite(STDERR, "Unable to read seeder: {$filename}\n");
        exit(1);
    }

    echo "  [running] {$filename}\n";

    try {
        // Split on semicolons outside of strings/comments
        $statements = [];
        $buffer = '';
        $inString = false;
        $stringChar = '';
        $len = strlen($sql);
        for ($i = 0; $i < $len; $i++) {
            $c = $sql[$i];
            if ($inString) {
                if ($c === '\\') {
                    $buffer .= $c;
                    if ($i + 1 < $len) { $buffer .= $sql[++$i]; }
                    continue;
                }
                if ($c === $stringChar) { $inString = false; }
                $buffer .= $c;
                continue;
            }
            if ($c === "'" || $c === '"') {
                $inString = true;
                $stringChar = $c;
                $buffer .= $c;
                continue;
            }
            if ($c === '-' && ($i + 1 < $len) && $sql[$i + 1] === '-') {
                while ($i < $len && $sql[$i] !== "\n") { $i++; }
                $buffer .= "\n";
                continue;
            }
            if ($c === ';') {
                $statements[] = $buffer;
                $buffer = '';
                continue;
            }
            $buffer .= $c;
        }
        if (trim($buffer) !== '') { $statements[] = $buffer; }

        foreach ($statements as $stmt) {
            $trimmed = trim($stmt);
            if ($trimmed === '' || str_starts_with($trimmed, '--')) {
                continue;
            }
            $pdo->exec($trimmed);
        }

        echo "  [done]    {$filename}\n";
    } catch (Throwable $e) {
        fwrite(STDERR, "  [FAILED]  {$filename}: " . $e->getMessage() . "\n");
        exit(1);
    }
}

echo "\nSeeders complete.\n";

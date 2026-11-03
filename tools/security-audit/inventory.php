<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';
require __DIR__ . '/analyzers/AstAnalyzer.php';

use CAS\SecurityAudit\Analyzers\AstAnalyzer;

$root = dirname(__DIR__, 2);
$directories = ['app', 'routes', 'bin', 'config', 'cron', 'database', 'tests'];
$excluded = ['vendor', '.git', 'storage', 'node_modules'];

$analyzer = new AstAnalyzer();
$totals = [
    'files' => 0,
    'failed' => 0,
    'classes' => 0,
    'methods' => 0,
    'functions' => 0,
    'calls' => [],
    'sql_fragments' => 0,
];
$failures = [];

foreach ($directories as $directory) {
    $path = $root . '/' . $directory;

    if (!is_dir($path)) {
        continue;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            static function (SplFileInfo $item) use ($excluded): bool {
                return !$item->isDir()
                    || !in_array($item->getFilename(), $excluded, true);
            }
        )
    );

    foreach ($iterator as $file) {
        if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
            continue;
        }

        $source = file_get_contents($file->getPathname());
        $relative = substr($file->getPathname(), strlen($root) + 1);

        try {
            if ($source === false) {
                throw new RuntimeException('Unable to read file.');
            }

            $result = $analyzer->analyze($source);
            $totals['files']++;
            $totals['classes'] += count($result['classes']);
            $totals['methods'] += count($result['methods']);
            $totals['functions'] += count($result['functions']);
            $totals['sql_fragments'] += count($result['sql_literals']);

            foreach ($result['calls'] as $call) {
                $category = $call['category'];
                $totals['calls'][$category] = ($totals['calls'][$category] ?? 0) + 1;
            }
        } catch (Throwable $exception) {
            $totals['failed']++;
            $failures[] = [
                'file' => $relative,
                'error' => $exception->getMessage(),
            ];
        }
    }
}

echo "CAS SUIT PROJECT AST INVENTORY\n";
echo "==============================\n";
echo "Files parsed: {$totals['files']}\n";
echo "Files failed: {$totals['failed']}\n";
echo "Classes: {$totals['classes']}\n";
echo "Methods: {$totals['methods']}\n";
echo "Functions: {$totals['functions']}\n";
echo "SQL fragments: {$totals['sql_fragments']}\n\n";

echo "CALL CATEGORIES\n";
foreach ($totals['calls'] as $category => $count) {
    echo str_pad($category, 22) . $count . "\n";
}

if ($failures !== []) {
    echo "\nPARSING FAILURES\n";
    foreach ($failures as $failure) {
        echo "{$failure['file']}: {$failure['error']}\n";
    }
}

exit($totals['failed'] > 0 ? 1 : 0);

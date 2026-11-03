<?php

declare(strict_types=1);

/**
 * CAS Suit Security Auditor
 *
 * Initial phase: source discovery and PHP tokenisation.
 * Read-only. No database connections or source modifications.
 */

$root = dirname(__DIR__, 2);

$scanDirectories = [
    'app',
    'routes',
    'bin',
    'config',
    'cron',
    'database',
    'tests',
];

$excludedDirectories = [
    'vendor',
    '.git',
    'storage',
    'node_modules',
];

$excludedSuffixes = [
    '.bak',
    '.debug-backup',
];

$excludedFiles = [
    'local.php',
    'cli.php',
    'secrets.php',
    'credentials.php',
];

$excludedFilenameFragments = [
    '.bak.',
    '.pre_',
    '.debug',
    '.old.',
];

$filesScanned = 0;
$filesFailed = 0;
$totalLines = 0;
$totalClasses = 0;
$totalFunctions = 0;
$failures = [];
$findings = [];

$tableClassification = require __DIR__ . '/rules/table-classification.php';
$tenantSqlRule = require __DIR__ . '/rules/tenant-sql.php';


echo "CAS Suit Security Auditor\n";
echo "=========================\n";
echo "Project: {$root}\n";
echo "Mode: READ ONLY\n\n";

foreach ($scanDirectories as $directory) {
    $path = $root . DIRECTORY_SEPARATOR . $directory;

    if (!is_dir($path)) {
        continue;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator(
                $path,
                FilesystemIterator::SKIP_DOTS
            ),
            static function (SplFileInfo $item) use (
                $excludedDirectories,
                $excludedSuffixes,
                $excludedFiles,
                $excludedFilenameFragments
            ): bool {
                if ($item->isDir()) {
                    return !in_array($item->getFilename(), $excludedDirectories, true);
                }

                $filename = $item->getFilename();

                if (in_array(strtolower($filename), $excludedFiles, true)) {
                    return false;
                }

                foreach ($excludedSuffixes as $suffix) {
                    if (str_ends_with(strtolower($filename), $suffix)) {
                        return false;
                    }
                }

                foreach ($excludedFilenameFragments as $fragment) {
                    if (str_contains(strtolower($filename), $fragment)) {
                        return false;
                    }
                }

                return $item->isFile() && strtolower($item->getExtension()) === 'php';
            }
        )
    );

    foreach ($iterator as $file) {
        $filePath = $file->getPathname();
        $relativePath = substr($filePath, strlen($root) + 1);

        try {
            $source = file_get_contents($filePath);

            if ($source === false) {
                throw new RuntimeException('Unable to read file.');
            }

            $tokens = token_get_all($source, TOKEN_PARSE);

            // TENANT-002: Inspect SQL literals for potentially unscoped tenant tables.
            $findings = array_merge(
                $findings,
                $tenantSqlRule($tokens, $relativePath, $tableClassification)
            );


            // TENANT-001: Hardcoded tenant IDs in application code.
            // The documented TenantContext default is an intentional fallback.
            if (
                preg_match('#^(app|routes|bin|cron)/#', $relativePath) === 1
                && $relativePath !== 'app/Core/TenantContext.php'
                && preg_match_all(
                    '/`?tenant_id`?\s*=\s*[0-9]+/i',
                    $source,
                    $matches,
                    PREG_OFFSET_CAPTURE
                )
            ) {
                foreach ($matches[0] as [$matchedText, $offset]) {
                    $line = substr_count(substr($source, 0, $offset), "\n") + 1;
                    $findings[] = [
                        'rule' => 'TENANT-001',
                        'severity' => 'REVIEW',
                        'file' => $relativePath,
                        'line' => $line,
                        'evidence' => trim($matchedText),
                    ];
                }
            }


            $classes = 0;
            $functions = 0;

            foreach ($tokens as $token) {
                if (!is_array($token)) {
                    continue;
                }

                if (in_array($token[0], [
                    T_CLASS,
                    T_INTERFACE,
                    T_TRAIT,
                    T_ENUM,
                ], true)) {
                    $classes++;
                }

                if ($token[0] === T_FUNCTION) {
                    $functions++;
                }
            }

            $lines = substr_count($source, "\n") + 1;

            $filesScanned++;
            $totalLines += $lines;
            $totalClasses += $classes;
            $totalFunctions += $functions;

            echo sprintf(
                "[SCANNED] %-65s %5d lines | %3d classes | %3d functions\n",
                $relativePath,
                $lines,
                $classes,
                $functions
            );
        } catch (Throwable $exception) {
            $filesFailed++;
            $failures[] = [
                'file' => $relativePath,
                'error' => $exception->getMessage(),
            ];

            echo "[FAILED]  {$relativePath}: {$exception->getMessage()}\n";
        }
    }
}

echo "\n";
echo "SCAN SUMMARY\n";
echo "------------\n";
echo "Files scanned: {$filesScanned}\n";
echo "Files failed:  {$filesFailed}\n";
echo "Total lines:   {$totalLines}\n";
echo "Classes:       {$totalClasses}\n";
echo "Functions:     {$totalFunctions}\n";

echo "\nSECURITY FINDINGS\n";
echo "-----------------\n";

foreach ($findings as $finding) {
    echo sprintf(
        "[%s] %s:%d | %s | %s\n",
        $finding['rule'],
        $finding['file'],
        $finding['line'],
        $finding['severity'],
        $finding['evidence']
    );
}

echo "Total findings: " . count($findings) . "\n\n";

$outputDirectory = __DIR__ . '/reports';

if (!is_dir($outputDirectory)) {
    mkdir($outputDirectory, 0775, true);
}

$report = [
    'generated_at' => date(DATE_ATOM),
    'project_root' => $root,
    'mode' => 'READ ONLY',
    'inventory' => [
        'files_scanned' => $filesScanned,
        'files_failed' => $filesFailed,
        'total_lines' => $totalLines,
        'classes' => $totalClasses,
        'functions' => $totalFunctions,
    ],
    'findings_count' => count($findings),
    'findings' => $findings,
    'failures' => $failures,
];

$outputFile = $outputDirectory . '/security-audit.json';

file_put_contents(
    $outputFile,
    json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
);

echo "JSON report: {$outputFile}\n";

if ($failures !== []) {
    echo "\nFILES REQUIRING ATTENTION\n";

    foreach ($failures as $failure) {
        echo "- {$failure['file']}: {$failure['error']}\n";
    }
}

exit($filesFailed > 0 ? 1 : 0);

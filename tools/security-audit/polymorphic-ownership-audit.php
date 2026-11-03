<?php

declare(strict_types=1);

use CAS\SecurityAudit\Analyzers\PolymorphicOwnershipAnalyzer;

require dirname(__DIR__, 2) . '/vendor/autoload.php';
require __DIR__ . '/analyzers/PolymorphicOwnershipAnalyzer.php';

$root = dirname(__DIR__, 2);
$analyzer = new PolymorphicOwnershipAnalyzer();

$results = $analyzer->analyzeDirectory($root . '/app');

$report = [
    'generated_at' => date(DATE_ATOM),
    'project_root' => $root,
    'mode' => 'READ ONLY',
    'analyzer' => 'polymorphic-ownership',
    'files_scanned' => count(array_unique(array_column($results, 'file'))),
    'results_count' => count($results),
    'by_rule' => [],
    'results' => $results,
];

foreach ($results as $result) {
    $rule = $result['rule'];
    $report['by_rule'][$rule] = ($report['by_rule'][$rule] ?? 0) + 1;
}

ksort($report['by_rule']);

$outputDirectory = __DIR__ . '/reports';

if (!is_dir($outputDirectory)
    && !mkdir($outputDirectory, 0775, true)
    && !is_dir($outputDirectory)) {
    fwrite(STDERR, "Unable to create report directory.\n");
    exit(1);
}

$output = $outputDirectory . '/polymorphic-ownership-audit.json';

file_put_contents(
    $output,
    json_encode(
        $report,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    )
);

echo "POLYMORPHIC OWNERSHIP AUDIT\n";
echo "===========================\n";
echo "Results:       " . count($results) . "\n";

foreach ($report['by_rule'] as $rule => $count) {
    printf("%-24s %d\n", $rule, $count);
}

echo "\nJSON report: {$output}\n";

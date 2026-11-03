<?php

declare(strict_types=1);

/**
 * Recruit Aid Security Auditor runner.
 * Runs regression tests, analyzers and consolidation in sequence.
 * Application source files are read only.
 */

$root = dirname(__DIR__, 2);
$auditDir = __DIR__;
$reportsDir = $auditDir . '/reports';
$startedAt = time();

$steps = [
    ['AST, routes and controller authorization regressions', 'tests/analyzer-regression.php', null],
    ['Tenant SQL regressions', 'tests/tenant-sql-regression.php', null],
    ['Polymorphic ownership regressions', 'tests/polymorphic-ownership-regression.php', null],
    ['Tenant and source audit', 'audit.php', 'security-audit.json'],
    ['Route audit', 'route-audit.php', 'route-audit.json'],
    ['Controller authorization audit', 'controller-auth-audit.php', 'controller-auth-audit.json'],
    ['Polymorphic ownership audit', 'polymorphic-ownership-audit.php', 'polymorphic-ownership-audit.json'],
    ['Consolidation', 'consolidate.php', 'consolidated-security-audit.json'],
];

$fail = static function (string $message): never {
    fwrite(STDERR, "\nAUDIT FAILED: {$message}\n");
    exit(1);
};

if (!is_file($root . '/vendor/autoload.php')) {
    $fail('Composer autoload is missing. Run composer install first.');
}

if (!is_dir($reportsDir) && !mkdir($reportsDir, 0775, true) && !is_dir($reportsDir)) {
    $fail('Unable to create reports directory.');
}

if (!function_exists('proc_open')) {
    $fail('PHP proc_open is disabled.');
}

echo "RECRUIT AID SECURITY AUDITOR\n";
echo "============================\n";
echo "Mode: READ ONLY (application source)\n";
echo "Project: {$root}\n\n";

foreach ($steps as $index => [$label, $relativeScript, $expectedReport]) {
    $script = $auditDir . '/' . $relativeScript;

    if (!is_file($script)) {
        $fail("Required script is missing: {$relativeScript}");
    }

    printf("[%d/%d] %s\n", $index + 1, count($steps), $label);

    $process = proc_open(
        [PHP_BINARY, $script],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        $root
    );

    if (!is_resource($process)) {
        $fail("Could not start {$relativeScript}");
    }

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);

    if ($stdout !== '') {
        echo $stdout;
    }
    if ($stderr !== '') {
        fwrite(STDERR, $stderr);
    }
    if ($exitCode !== 0) {
        $fail("{$relativeScript} exited with code {$exitCode}");
    }

    if ($expectedReport !== null) {
        $reportPath = $reportsDir . '/' . $expectedReport;

        if (!is_file($reportPath) || filemtime($reportPath) < $startedAt) {
            $fail("Expected report was not freshly generated: {$expectedReport}");
        }

        try {
            $report = json_decode(
                (string) file_get_contents($reportPath),
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (Throwable $exception) {
            $fail("Invalid JSON in {$expectedReport}: {$exception->getMessage()}");
        }

        if (!is_array($report)) {
            $fail("Unexpected report structure in {$expectedReport}");
        }

        if ($expectedReport === 'security-audit.json'
            && (int) ($report['inventory']['files_failed'] ?? 0) > 0) {
            $fail('The source audit reported files that could not be scanned.');
        }
    }

    echo "\n";
}

$finalPath = $reportsDir . '/consolidated-security-audit.json';
$final = json_decode((string) file_get_contents($finalPath), true, 512, JSON_THROW_ON_ERROR);
$summary = $final['summary'] ?? [];

echo "AUDIT COMPLETE\n";
echo "==============\n";
echo 'Findings: ' . ($summary['findings_count'] ?? count($final['findings'] ?? [])) . "\n";
echo "Report: {$finalPath}\n";
echo "\nNo application source files were modified.\n";

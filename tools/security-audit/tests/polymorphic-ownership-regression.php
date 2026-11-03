<?php

declare(strict_types=1);

require_once __DIR__ . '/../analyzers/PolymorphicOwnershipAnalyzer.php';

use CAS\SecurityAudit\Analyzers\PolymorphicOwnershipAnalyzer;

$analyzer = new PolymorphicOwnershipAnalyzer();
$base = sys_get_temp_dir() . '/recruitaid-poly-audit-' . getmypid();

if (!mkdir($base, 0777, true) && !is_dir($base)) {
    throw new RuntimeException('Could not create test directory.');
}

$tests = [];
$failures = [];

$assert = static function (string $name, bool $condition) use (&$tests, &$failures): void {
    $tests[] = $name;
    if (!$condition) {
        $failures[] = $name;
        echo "[FAIL] {$name}\n";
    } else {
        echo "[PASS] {$name}\n";
    }
};

try {
    $saver = $base . '/CustomFieldValueSaver.php';
    file_put_contents($saver, <<<'SOURCE'
<?php
$file = $this->files->findByUuid($uuid);
$this->files->link($file->id, $entityType, $entityId, $field->fieldKey, $userId);
$this->files->link($result->id, $entityType, $entityId, $field->fieldKey, $userId);
$files->link($result->id, 'branding', 0, 'branding_logo', $userId);
SOURCE
    );

    $results = $analyzer->analyzeFile($saver, $base);
    $rules = array_column($results, 'rule');

    $assert('Detects file UUID lookup', count(array_filter($rules, static fn($r) => $r === 'POLY-OWNERSHIP-001')) === 1);
    $assert('Detects both custom-field file links', count(array_filter($rules, static fn($r) => $r === 'POLY-OWNERSHIP-002')) === 2);
    $assert('Excludes intentional branding link', count($results) === 3);

    $repository = $base . '/FileRepository.php';
    file_put_contents($repository, <<<'SOURCE'
<?php
public function findByUuid(string $uuid): ?File
{
    return null;
}
SOURCE
    );
    $assert('Ignores findByUuid method declaration', $analyzer->analyzeFile($repository, $base) === []);

    $service = $base . '/FileService.php';
    file_put_contents($service, <<<'SOURCE'
<?php
return $this->files->findByUuid($uuid);
$this->links->link($fileId, $entityType, $entityId, $category, $linkedBy);
SOURCE
    );
    $serviceResults = $analyzer->analyzeFile($service, $base);
    $assert('Excludes generic FileService wrapper', count($serviceResults) === 1 && $serviceResults[0]['rule'] === 'POLY-OWNERSHIP-001');

    $assert('Missing file returns no findings', $analyzer->analyzeFile($base . '/missing.php', $base) === []);
} finally {
    foreach (glob($base . '/*') ?: [] as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
    rmdir($base);
}

echo "\nTests: " . count($tests) . " | Passed: " . (count($tests) - count($failures)) . " | Failed: " . count($failures) . "\n";

exit($failures === [] ? 0 : 1);

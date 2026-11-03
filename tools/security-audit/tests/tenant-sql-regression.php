<?php

declare(strict_types=1);

$rule = require dirname(__DIR__) . '/rules/tenant-sql.php';

$classification = [
    'tenant_scoped' => [
        'recruit_offers',
        'recruit_applications',
        'recruit_candidates',
        'recruit_client_contacts',
    ],
    'user_scoped' => [
        'core_users',
    ],
    'global' => [],
    'review' => [],
];

$passed = 0;
$failed = 0;

$assert = static function (bool $condition, string $message) use (&$passed, &$failed): void {
    echo ($condition ? '[PASS] ' : '[FAIL] ') . $message . "\n";
    $condition ? $passed++ : $failed++;
};

$analyze = static function (string $sql, string $file = 'app/Test.php') use ($rule, $classification): array {
    $source = "<?php\n\$db->select(" . var_export($sql, true) . ");";
    return $rule(token_get_all($source), $file, $classification);
};

$findings = $analyze(
    'SELECT * FROM recruit_offers o WHERE o.deleted_at IS NULL'
);
$assert(count($findings) === 1, 'Flags an unscoped tenant table');

$findings = $analyze(
    'SELECT * FROM recruit_offers o WHERE o.tenant_id = ?'
);
$assert(count($findings) === 0, 'Accepts a direct tenant predicate');

$findings = $analyze(
    'SELECT * FROM recruit_offers o JOIN recruit_applications a ON a.id = o.application_id WHERE o.tenant_id = ?'
);
$assert(
    count($findings) === 1,
    'Checks joined tenant tables independently when only one alias is scoped'
);

$findings = $analyze(
    'SELECT * FROM recruit_offers o JOIN recruit_applications a ON a.id = o.application_id WHERE o.tenant_id = ? AND a.tenant_id = ?'
);
$assert(count($findings) === 0, 'Accepts independently scoped joined tables');

$findings = $analyze(
    'SELECT * FROM core_users WHERE id = ?'
);
$assert(count($findings) === 0, 'Does not treat user-scoped tables as tenant-scoped');

$findings = $analyze(
    'UPDATE recruit_client_contacts SET deleted_at = NOW() WHERE id = ?'
);
$assert(count($findings) === 1, 'Flags an ID-only update for manual ownership review');

$findings = $analyze(
    'INSERT INTO recruit_offers (tenant_id, application_id, status) VALUES (?, ?, ?)'
);
$assert(count($findings) === 0, 'Accepts inserts that explicitly provide tenant_id');

$findings = $analyze(
    'SELECT * FROM recruit_offers o WHERE o.tenant_id = ?',
    'tests/tenant_fixture_test.php'
);
$assert(count($findings) === 0, 'Does not flag a tenant-scoped test query');

echo "\nResults: {$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);

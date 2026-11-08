<?php

declare(strict_types=1);

$rule = require dirname(__DIR__) . '/rules/tenant-sql.php';

$classification = [
    'tenant_scoped' => [
        'recruit_offers',
        'recruit_applications',
        'recruit_candidates',
        'recruit_client_contacts',
        'recruit_application_stage_history',
        'recruit_vacancy_stages',
        'recruit_vacancies',
        'recruit_interviews',
        'core_users',
    ],
    'user_scoped' => [],
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
$assert(count($findings) === 1, 'Treats core_users according to the production tenant-scoped classification');

$findings = $analyze(
    'SELECT * FROM recruit_applications a WHERE a.id IN (SELECT application_id FROM recruit_application_stage_history WHERE tenant_id = ?)'
);
$assert(count($findings) === 1, 'Does not let a nested tenant predicate scope its outer query');

$findings = $analyze(
    'SELECT * FROM recruit_applications a WHERE a.id IN (SELECT application_id FROM recruit_application_stage_history)'
);
$assert(count($findings) === 2, 'Flags unscoped outer and nested query references independently');

$findings = $analyze(
    'SELECT * FROM recruit_candidates WHERE tenant_id = ? UNION ALL SELECT * FROM recruit_applications'
);
$assert(count($findings) === 1, 'Checks UNION branches independently');

$findings = $analyze(
    'SELECT * FROM recruit_application_stage_history sh WHERE EXISTS (SELECT 1 FROM recruit_application_stage_history sh2 WHERE sh2.application_id = sh.application_id)'
);
$assert(count($findings) === 2, 'Checks repeated table references in separate query scopes independently');

$findings = $analyze(
    'SELECT * FROM recruit_applications a WHERE EXISTS (SELECT 1 FROM recruit_candidates c WHERE c.tenant_id = ?)'
);
$assert(count($findings) === 1, 'Does not let an unrelated nested predicate scope the outer query');

$findings = $analyze(
    'SELECT * FROM core_users WHERE id = ?'
);
$assert(count($findings) === 1, 'Treats core_users according to the production tenant-scoped classification');

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


$findings = $analyze(
    "SELECT `id` FROM `recruit_vacancy_stages` WHERE `vacancy_id` = ?"
);
$assert(count($findings) === 1, "Detects unscoped backtick-quoted tenant tables");

echo "\nResults: {$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);

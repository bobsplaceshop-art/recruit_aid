<?php
declare(strict_types=1);

ob_start();

register_shutdown_function(static function (): void {
    if (ob_get_level() > 0) {
        ob_end_flush();
    }
});

require dirname(__DIR__) . '/vendor/autoload.php';

use CorporateAid\Core\App;
use CorporateAid\Core\Database;
use CorporateAid\Platform\Support\CodeGenerator;

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }

    echo "[PASS] {$message}\n";
}

function insert(PDO $pdo, string $sql, array $values): int
{
    $statement = $pdo->prepare($sql);
    $statement->execute($values);

    return (int) $pdo->lastInsertId();
}

function scalar(PDO $pdo, string $sql, array $values = []): mixed
{
    $statement = $pdo->prepare($sql);
    $statement->execute($values);

    return $statement->fetchColumn();
}

function expectException(
    callable $callback,
    string $exceptionClass,
    string $message
): void {
    try {
        $callback();
    } catch (Throwable $exception) {
        check(
            $exception instanceof $exceptionClass,
            $message . ' (correct exception type)'
        );
        return;
    }

    throw new RuntimeException($message . ' (no exception thrown)');
}

$app = null;
$pdo = null;
$transactionStarted = false;
$exitCode = 0;

try {
    $app = App::boot(dirname(__DIR__));

    $container = $app->container();
    $db = $container->get(Database::class);
    $pdo = $db->pdo();

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $generator = new CodeGenerator($db);

    check(true, 'Application and CodeGenerator booted successfully.');

    $pdo->beginTransaction();
    $transactionStarted = true;

    $suffix = (string) random_int(100000, 999999);

    $tenantOne = insert(
        $pdo,
        "INSERT INTO core_tenants (code, name, status)
         VALUES (?, ?, 'active')",
        [
            'CGT-' . $suffix . '-1',
            'Code Generator Test One ' . $suffix,
        ]
    );

    $tenantTwo = insert(
        $pdo,
        "INSERT INTO core_tenants (code, name, status)
         VALUES (?, ?, 'active')",
        [
            'CGT-' . $suffix . '-2',
            'Code Generator Test Two ' . $suffix,
        ]
    );

    check(true, 'Two temporary tenants created.');

    $counterOne = 'test_org_code_t' . $tenantOne;
    $counterTwo = 'test_org_code_t' . $tenantTwo;

    $pdo->prepare(
        "INSERT INTO core_sequence_counters (name, `last_value`)
         VALUES (?, 0), (?, 0)"
    )->execute([$counterOne, $counterTwo]);

    check(true, 'Independent zero-value sequence counters created.');

    // ---------------------------------------------------------
    // TENANT ONE AND TENANT TWO: independent first allocations
    // ---------------------------------------------------------

    $tenantOneFirst = $generator->nextForTenant(
        'test_org_code',
        'ORG',
        $tenantOne
    );

    $tenantTwoFirst = $generator->nextForTenant(
        'test_org_code',
        'ORG',
        $tenantTwo
    );

    check(
        $tenantOneFirst === 'ORG-000001',
        'Tenant one receives its first organisation code.'
    );

    check(
        $tenantTwoFirst === 'ORG-000001',
        'Tenant two independently receives the same first organisation code.'
    );

    // ---------------------------------------------------------
    // TENANT ONE AND TENANT TWO: independent increments
    // ---------------------------------------------------------

    $tenantOneSecond = $generator->nextForTenant(
        'test_org_code',
        'ORG',
        $tenantOne
    );

    check(
        $tenantOneSecond === 'ORG-000002',
        'Tenant one sequence increments to its second code.'
    );

    check(
        scalar(
            $pdo,
            "SELECT `last_value` FROM core_sequence_counters WHERE name = ?",
            [$counterOne]
        ) === 2,
        'Tenant one counter stores its own second value.'
    );

    check(
        scalar(
            $pdo,
            "SELECT `last_value` FROM core_sequence_counters WHERE name = ?",
            [$counterTwo]
        ) === 1,
        'Tenant one allocation does not increment tenant two counter.'
    );

    $tenantTwoSecond = $generator->nextForTenant(
        'test_org_code',
        'ORG',
        $tenantTwo
    );

    check(
        $tenantTwoSecond === 'ORG-000002',
        'Tenant two sequence increments independently to its second code.'
    );

    check(
        scalar(
            $pdo,
            "SELECT `last_value` FROM core_sequence_counters WHERE name = ?",
            [$counterOne]
        ) === 2,
        'Tenant two allocation does not change tenant one counter.'
    );

    check(
        scalar(
            $pdo,
            "SELECT `last_value` FROM core_sequence_counters WHERE name = ?",
            [$counterTwo]
        ) === 2,
        'Tenant two counter stores its own second value.'
    );

    // ---------------------------------------------------------
    // CUSTOM PADDING
    // ---------------------------------------------------------

    $customPaddingCode = $generator->nextForTenant(
        'test_org_code',
        'ORG',
        $tenantOne,
        4
    );

    check(
        $customPaddingCode === 'ORG-0003',
        'Custom code padding is respected.'
    );

    // ---------------------------------------------------------
    // INVALID INPUTS
    // ---------------------------------------------------------

    expectException(
        static fn() => $generator->nextForTenant(
            'Invalid-Counter',
            'ORG',
            $tenantOne
        ),
        InvalidArgumentException::class,
        'Invalid counter name is rejected.'
    );

    expectException(
        static fn() => $generator->nextForTenant(
            'test_org_code',
            'org',
            $tenantOne
        ),
        InvalidArgumentException::class,
        'Invalid code prefix is rejected.'
    );

    expectException(
        static fn() => $generator->nextForTenant(
            'test_org_code',
            'ORG',
            0
        ),
        InvalidArgumentException::class,
        'Non-positive tenant ID is rejected.'
    );

    expectException(
        static fn() => $generator->nextForTenant(
            'test_org_code',
            'ORG',
            $tenantOne,
            0
        ),
        InvalidArgumentException::class,
        'Zero code padding is rejected.'
    );

    expectException(
        static fn() => $generator->nextForTenant(
            'test_org_code',
            'ORG',
            $tenantOne,
            19
        ),
        InvalidArgumentException::class,
        'Padding above the supported limit is rejected.'
    );

    expectException(
        static fn() => $generator->nextForTenant(
            'missing_test_counter',
            'ORG',
            $tenantOne
        ),
        RuntimeException::class,
        'Missing sequence counter is rejected.'
    );

    echo "\nAll CodeGenerator tenant-sequence checks passed.\n";
} catch (Throwable $exception) {
    fwrite(STDERR, "\n[FAIL] " . $exception->getMessage() . "\n");
    fwrite(STDERR, $exception->getTraceAsString() . "\n");
    $exitCode = 1;
} finally {
    if ($transactionStarted && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
        echo "[CLEANUP] Test transaction rolled back.\n";
    }
}

exit($exitCode);

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
use CorporateAid\Core\TenantContext;
use CorporateAid\Recruit\Repositories\OrganisationRepository;

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
$tenantContext = null;
$transactionStarted = false;
$exitCode = 0;

try {
    $app = App::boot(dirname(__DIR__));

    $container = $app->container();
    $db = $container->get(Database::class);
    $pdo = $db->pdo();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $tenantContext = $container->get(TenantContext::class);
    $repository = $container->get(OrganisationRepository::class);

    check(true, 'Application and OrganisationRepository booted successfully.');

    $pdo->beginTransaction();
    $transactionStarted = true;

    $suffix = (string) random_int(100000, 999999);
    $marker = 'ORT' . $suffix;

    $tenantOne = 1;

    $tenantOneCountBefore = (int) scalar(
        $pdo,
        "SELECT COUNT(*) FROM recruit_client_organisations
         WHERE tenant_id = ? AND deleted_at IS NULL",
        [$tenantOne]
    );

    $tenantOneActiveBefore = (int) scalar(
        $pdo,
        "SELECT COUNT(*) FROM recruit_client_organisations
         WHERE tenant_id = ? AND status = 'active' AND deleted_at IS NULL",
        [$tenantOne]
    );

    $tenantTwo = insert(
        $pdo,
        "INSERT INTO core_tenants (code, name, status)
         VALUES (?, ?, 'active')",
        [
            'ORT-' . $suffix,
            'Organisation Repository Test ' . $suffix,
        ]
    );

    check($tenantTwo > 1, 'Temporary second tenant created.');

    $userOneId = insert(
        $pdo,
        "INSERT INTO core_users
            (tenant_id, name, email, password_hash, status)
         VALUES (?, ?, ?, ?, 'active')",
        [
            $tenantOne,
            'Organisation Test User One',
            'org-test-one-' . $suffix . '@example.test',
            password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
        ]
    );

    $userTwoId = insert(
        $pdo,
        "INSERT INTO core_users
            (tenant_id, name, email, password_hash, status)
         VALUES (?, ?, ?, ?, 'active')",
        [
            $tenantTwo,
            'Organisation Test User Two',
            'org-test-two-' . $suffix . '@example.test',
            password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
        ]
    );

    check(true, 'Temporary users created in both tenants.');

    $sharedCode = 'ORT-SHARED-' . $suffix;
    $sharedEmail = 'shared-org-' . $suffix . '@example.test';

    $orgOneId = insert(
        $pdo,
        "INSERT INTO recruit_client_organisations
            (tenant_id, code, name, email, status, created_by)
         VALUES (?, ?, ?, ?, 'active', ?)",
        [
            $tenantOne,
            $sharedCode,
            $marker . ' Tenant One Organisation',
            $sharedEmail,
            $userOneId,
        ]
    );

    $orgTwoId = insert(
        $pdo,
        "INSERT INTO recruit_client_organisations
            (tenant_id, code, name, email, status, created_by)
         VALUES (?, ?, ?, ?, 'active', ?)",
        [
            $tenantTwo,
            $sharedCode,
            $marker . ' Tenant Two Organisation',
            $sharedEmail,
            $userTwoId,
        ]
    );

    check(
        $orgOneId !== $orgTwoId,
        'Both tenants can use the same organisation code and email.'
    );

    // ---------------------------------------------------------
    // TENANT ONE: lookup isolation
    // ---------------------------------------------------------

    $tenantContext->set($tenantOne);

    check(
        $repository->findById($orgOneId) !== null,
        'Tenant one can find its own organisation by ID.'
    );

    check(
        $repository->findById($orgTwoId) === null,
        'Tenant one cannot find tenant two organisation by ID.'
    );

    check(
        $repository->findByCode($sharedCode)?->id === $orgOneId,
        'Tenant one code lookup returns its own organisation.'
    );

    check(
        $repository->findByEmail($sharedEmail)?->id === $orgOneId,
        'Tenant one email lookup returns its own organisation.'
    );

    check(
        $repository->codeExists($sharedCode),
        'Tenant one recognises its own organisation code.'
    );

    $page = $repository->paginate(['search' => $marker]);

    check(
        count($page['rows']) === 1
        && $page['rows'][0]->id === $orgOneId,
        'Tenant one pagination excludes tenant two organisations.'
    );

    $filterRows = $repository->allForFilter();

    check(
        count(array_filter(
            $filterRows,
            static fn(array $row): bool => $row['id'] === $orgOneId
        )) === 1
        && count(array_filter(
            $filterRows,
            static fn(array $row): bool => $row['id'] === $orgTwoId
        )) === 0,
        'Tenant one organisation filter dropdown excludes tenant two.'
    );

    check(
        $repository->count() === $tenantOneCountBefore + 1,
        'Tenant one organisation count includes only its own active records.'
    );

    check(
        $repository->countsByStatus()['active'] === $tenantOneActiveBefore + 1,
        'Tenant one status counts exclude tenant two.'
    );

    // ---------------------------------------------------------
    // TENANT ONE: mutation isolation
    // ---------------------------------------------------------

    expectException(
        static fn() => $repository->update(
            $orgTwoId,
            ['name' => 'Unauthorised Organisation Change']
        ),
        RuntimeException::class,
        'Cross-tenant organisation update is rejected.'
    );

    check(
        scalar(
            $pdo,
            "SELECT name FROM recruit_client_organisations WHERE id = ?",
            [$orgTwoId]
        ) === $marker . ' Tenant Two Organisation',
        'Rejected update leaves tenant two organisation unchanged.'
    );

    $repository->softDelete($orgTwoId);

    check(
        scalar(
            $pdo,
            "SELECT deleted_at FROM recruit_client_organisations WHERE id = ?",
            [$orgTwoId]
        ) === null,
        'Cross-tenant soft-delete does not affect tenant two organisation.'
    );

    $pdo->prepare(
        "UPDATE recruit_client_organisations
         SET deleted_at = '2026-01-01 00:00:00'
         WHERE id = ? AND tenant_id = ?"
    )->execute([$orgTwoId, $tenantTwo]);

    $repository->restore($orgTwoId);

    check(
        scalar(
            $pdo,
            "SELECT deleted_at FROM recruit_client_organisations WHERE id = ?",
            [$orgTwoId]
        ) === '2026-01-01 00:00:00',
        'Cross-tenant restore does not affect tenant two organisation.'
    );

    // ---------------------------------------------------------
    // TENANT ONE: creator ownership validation
    // ---------------------------------------------------------

    expectException(
        static fn() => $repository->create([
            'code' => 'ORT-INVALID-' . $suffix,
            'name' => 'Invalid Creator Organisation',
            'created_by' => $userTwoId,
        ]),
        InvalidArgumentException::class,
        'Organisation creation rejects a creator from another tenant.'
    );

    $createdOrgId = $repository->create([
        'code' => 'ORT-VALID-' . $suffix,
        'name' => 'Valid Creator Organisation',
        'email' => 'valid-org-' . $suffix . '@example.test',
        'created_by' => $userOneId,
    ]);

    check(
        (int) scalar(
            $pdo,
            "SELECT tenant_id FROM recruit_client_organisations WHERE id = ?",
            [$createdOrgId]
        ) === $tenantOne,
        'Organisation creation assigns the active tenant ID.'
    );

    check(
        (int) scalar(
            $pdo,
            "SELECT created_by FROM recruit_client_organisations WHERE id = ?",
            [$createdOrgId]
        ) === $userOneId,
        'Organisation creation accepts a creator from the same tenant.'
    );

    // ---------------------------------------------------------
    // TENANT TWO: own records remain accessible
    // ---------------------------------------------------------

    $tenantContext->set($tenantTwo);

    $repository->restore($orgTwoId);

    check(
        scalar(
            $pdo,
            "SELECT deleted_at FROM recruit_client_organisations WHERE id = ?",
            [$orgTwoId]
        ) === null,
        'Tenant two can restore its own organisation.'
    );

    check(
        $repository->findById($orgTwoId) !== null,
        'Tenant two can find its own organisation.'
    );

    check(
        $repository->findById($orgOneId) === null,
        'Tenant two cannot find tenant one organisation.'
    );

    check(
        $repository->findByCode($sharedCode)?->id === $orgTwoId,
        'Tenant two code lookup returns its own organisation.'
    );

    check(
        $repository->findByEmail($sharedEmail)?->id === $orgTwoId,
        'Tenant two email lookup returns its own organisation.'
    );

    check(
        $repository->count() === 1,
        'Tenant two organisation count includes only its own active records.'
    );

    check(
        $repository->findById($createdOrgId) === null,
        'Tenant two cannot find the organisation created by tenant one.'
    );

    echo "\nAll OrganisationRepository tenant-isolation checks passed.\n";

} catch (Throwable $exception) {
    echo "\n[FAIL] " . $exception->getMessage() . "\n";
    echo $exception->getTraceAsString() . "\n";
    $exitCode = 1;

} finally {
    if ($tenantContext !== null) {
        $tenantContext->clear();
    }

    if ($transactionStarted && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
        echo "[CLEANUP] Test transaction rolled back.\n";
    }
}

exit($exitCode);

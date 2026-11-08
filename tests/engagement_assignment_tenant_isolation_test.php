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
use CorporateAid\Recruit\Repositories\EngagementAssignmentRepository;

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

function createTestUser(
    PDO $pdo,
    int $tenantId,
    string $name,
    string $email
): int {
    return insert(
        $pdo,
        "INSERT INTO core_users
            (tenant_id, name, email, password_hash, status)
         VALUES (?, ?, ?, ?, 'active')",
        [
            $tenantId,
            $name,
            $email,
            password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
        ]
    );
}

function createTestEngagement(
    PDO $pdo,
    int $tenantId,
    int $organisationId,
    int $number,
    ?int $createdBy
): int {
    return insert(
        $pdo,
        "INSERT INTO recruit_engagements
            (tenant_id, code, client_organisation_id, name,
             service_type, status, created_by)
         VALUES (?, ?, ?, ?, 'focused_search', 'active', ?)",
        [
            $tenantId,
            sprintf('ENG-%06d', $number),
            $organisationId,
            'Assignment Test Engagement ' . $number,
            $createdBy,
        ]
    );
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
    $assignments = $container->get(EngagementAssignmentRepository::class);

    check(true, 'Application and EngagementAssignmentRepository booted successfully.');

    $pdo->beginTransaction();
    $transactionStarted = true;

    $suffix = (string) random_int(100000, 999999);
    $tenantOne = 1;

    $tenantTwo = insert(
        $pdo,
        "INSERT INTO core_tenants (code, name, status)
         VALUES (?, ?, 'active')",
        [
            'EAT-' . $suffix,
            'Engagement Assignment Test Tenant ' . $suffix,
        ]
    );

    check($tenantTwo > 1, 'Temporary second tenant created.');

    $userOne = createTestUser(
        $pdo,
        $tenantOne,
        'Assignment User One',
        'ea-user-one-' . $suffix . '@example.test'
    );

    $userTwo = createTestUser(
        $pdo,
        $tenantTwo,
        'Assignment User Two',
        'ea-user-two-' . $suffix . '@example.test'
    );

    $expiredUser = createTestUser(
        $pdo,
        $tenantOne,
        'Assignment Expired User',
        'ea-expired-user-' . $suffix . '@example.test'
    );

    check(true, 'Active users created in both tenants, including an expiry test user.');

    $organisationOne = insert(
        $pdo,
        "INSERT INTO recruit_client_organisations
            (tenant_id, code, name, email, status, created_by)
         VALUES (?, ?, ?, ?, 'active', ?)",
        [
            $tenantOne,
            'EA-ORG-' . $suffix,
            'Assignment Test Organisation One ' . $suffix,
            'ea-org-one-' . $suffix . '@example.test',
            $userOne,
        ]
    );

    $organisationTwo = insert(
        $pdo,
        "INSERT INTO recruit_client_organisations
            (tenant_id, code, name, email, status, created_by)
         VALUES (?, ?, ?, ?, 'active', ?)",
        [
            $tenantTwo,
            'EB-ORG-' . $suffix,
            'Assignment Test Organisation Two ' . $suffix,
            'ea-org-two-' . $suffix . '@example.test',
            $userTwo,
        ]
    );

    $engagementOne = createTestEngagement(
        $pdo,
        $tenantOne,
        $organisationOne,
        (int) $suffix,
        $userOne
    );

    $engagementTwo = createTestEngagement(
        $pdo,
        $tenantTwo,
        $organisationTwo,
        ((int) $suffix + 1) % 1000000,
        $userTwo
    );

    check(true, 'Valid tenant-owned organisations and engagements created.');

    // ---------------------------------------------------------
    // GLOBAL ROLE AND PERMISSION FIXTURES
    // ---------------------------------------------------------

    $permissionSlug = 'ea.test.permission.' . $suffix;
    // Alias for downstream assign() calls that reference the tenant-1 role
    // by slug (the engagement being tested belongs to tenant one).
    $roleSlug = 'ea_test_role_1_' . $suffix;

    // Post-migration (0061): core_roles is tenant-scoped. A single role cannot
    // be assigned across tenants. Create one role per tenant; assign each user
    // to their own tenant's role. The permission is shared (core_permissions is
    // global), but the role-permission grants are per-role.
    $roleId = insert(
        $pdo,
        "INSERT INTO core_roles (tenant_id, name, slug, scope, module_key)
         VALUES (?, ?, ?, 'app', 'recruit')",
        [$tenantOne, 'Engagement Assignment Test Role 1 ' . $suffix, 'ea_test_role_1_' . $suffix]
    );

    $roleTwoId = insert(
        $pdo,
        "INSERT INTO core_roles (tenant_id, name, slug, scope, module_key)
         VALUES (?, ?, ?, 'app', 'recruit')",
        [$tenantTwo, 'Engagement Assignment Test Role 2 ' . $suffix, 'ea_test_role_2_' . $suffix]
    );

    $permissionId = insert(
        $pdo,
        "INSERT INTO core_permissions (slug, module, scope)
         VALUES (?, 'recruit', 'app')",
        [$permissionSlug]
    );

    insert(
        $pdo,
        "INSERT INTO core_role_permissions (role_id, permission_id)
         VALUES (?, ?)",
        [$roleId, $permissionId]
    );

    insert(
        $pdo,
        "INSERT INTO core_role_permissions (role_id, permission_id)
         VALUES (?, ?)",
        [$roleTwoId, $permissionId]
    );

    insert(
        $pdo,
        "INSERT INTO core_user_roles (tenant_id, user_id, role_id, assigned_by)
         VALUES (?, ?, ?, ?)",
        [$tenantOne, $userOne, $roleId, $userOne]
    );

    insert(
        $pdo,
        "INSERT INTO core_user_roles (tenant_id, user_id, role_id, assigned_by)
         VALUES (?, ?, ?, ?)",
        [$tenantTwo, $userTwo, $roleTwoId, $userTwo]
    );

    insert(
        $pdo,
        "INSERT INTO core_user_roles
            (tenant_id, user_id, role_id, assigned_by, expires_at)
         VALUES (?, ?, ?, ?, DATE_SUB(NOW(), INTERVAL 1 DAY))",
        [$tenantOne, $expiredUser, $roleId, $userOne]
    );

    check(true, 'Per-tenant role fixtures created; each user assigned to their own tenant role.');
    check(true, 'An expired role membership was created for tenant one.');

    // ---------------------------------------------------------
    // TENANT ONE: CREATE ASSIGNMENTS
    // ---------------------------------------------------------

    $tenantContext->set($tenantOne);

    $assignments->assign(
        $engagementOne,
        'user',
        (string) $userOne,
        true,
        true,
        $userOne
    );

    $assignments->assign(
        $engagementOne,
        'role',
        $roleSlug,
        true,
        false,
        $userOne
    );

    $assignments->assign(
        $engagementOne,
        'permission',
        $permissionSlug,
        true,
        false,
        $userOne
    );

    check(
        (int) scalar(
            $pdo,
            "SELECT COUNT(*) FROM recruit_engagement_assignments
             WHERE tenant_id = ? AND engagement_id = ?",
            [$tenantOne, $engagementOne]
        ) === 3,
        'Tenant one assignments are stored with the active tenant ID.'
    );

    $tenantOneAssignments = $assignments->forEngagement($engagementOne);

    check(
        count($tenantOneAssignments) === 3,
        'Tenant one can retrieve all three of its assignments.'
    );

    $directAssignment = null;

    foreach ($tenantOneAssignments as $assignment) {
        if (
            $assignment->assigneeType === 'user'
            && $assignment->assigneeValue === (string) $userOne
        ) {
            $directAssignment = $assignment;
            break;
        }
    }

    check(
        $directAssignment !== null
        && $directAssignment->assigneeLabel === 'Assignment User One',
        'Direct user assignment resolves its tenant-owned display label.'
    );

    // ---------------------------------------------------------
    // TENANT ONE: RESOLVE ROLE AND PERMISSION MEMBERS
    // ---------------------------------------------------------

    $roleUsers = $assignments->resolveUserIds($engagementOne);

    check(
        in_array($userOne, $roleUsers, true),
        'Role assignment resolves the active tenant one user.'
    );

    check(
        !in_array($userTwo, $roleUsers, true),
        'Role assignment does not resolve a user from tenant two.'
    );

    check(
        !in_array($expiredUser, $roleUsers, true),
        'Role assignment excludes expired role memberships.'
    );

    $permissionUsers = $assignments->resolveUserIds($engagementOne);

    check(
        in_array($userOne, $permissionUsers, true),
        'Permission assignment resolves the tenant one user through global role permissions.'
    );

    check(
        !in_array($userTwo, $permissionUsers, true),
        'Permission assignment does not resolve a user from tenant two.'
    );

    // ---------------------------------------------------------
    // REJECT INVALID AND CROSS-TENANT ASSIGNMENTS
    // ---------------------------------------------------------

    expectException(
        static fn() => $assignments->assign(
            $engagementOne,
            'user',
            (string) $userTwo,
            true,
            false,
            $userOne
        ),
        InvalidArgumentException::class,
        'Direct assignment rejects a user from another tenant.'
    );

    expectException(
        static fn() => $assignments->assign(
            $engagementOne,
            'user',
            (string) $userOne,
            true,
            false,
            $userTwo
        ),
        InvalidArgumentException::class,
        'Assignment rejects an assigning user from another tenant.'
    );

    expectException(
        static fn() => $assignments->assign(
            $engagementTwo,
            'user',
            (string) $userOne,
            true,
            false,
            $userOne
        ),
        InvalidArgumentException::class,
        'Assignment rejects an engagement owned by another tenant.'
    );

    expectException(
        static fn() => $assignments->forEngagement($engagementTwo),
        InvalidArgumentException::class,
        'Tenant one cannot list assignments for tenant two engagement.'
    );

    expectException(
        static fn() => $assignments->assign(
            $engagementOne,
            'invalid',
            'anything',
            true,
            false,
            $userOne
        ),
        InvalidArgumentException::class,
        'Invalid assignment types are rejected.'
    );

    check(
        (int) scalar(
            $pdo,
            "SELECT COUNT(*) FROM recruit_engagement_assignments
             WHERE tenant_id = ? AND engagement_id = ?",
            [$tenantOne, $engagementOne]
        ) === 3,
        'Rejected assignments do not modify existing assignments.'
    );

    // ---------------------------------------------------------
    // TENANT TWO: ISOLATED ASSIGNMENTS AND REMOVAL
    // ---------------------------------------------------------

    $tenantContext->set($tenantTwo);

    check(
        $assignments->forEngagement($engagementTwo) === [],
        'Tenant two starts with no assignments on its own engagement.'
    );

    expectException(
        static fn() => $assignments->assign(
            $engagementTwo,
            'user',
            (string) $userOne,
            true,
            false,
            $userTwo
        ),
        InvalidArgumentException::class,
        'Tenant two also rejects a direct user assignment from tenant one.'
    );

    $assignments->assign(
        $engagementTwo,
        'user',
        (string) $userTwo,
        true,
        true,
        $userTwo
    );

    check(
        count($assignments->forEngagement($engagementTwo)) === 1,
        'Tenant two can create and retrieve its own assignment.'
    );

    $assignments->remove(
        (int) scalar(
            $pdo,
            "SELECT id FROM recruit_engagement_assignments
             WHERE tenant_id = ? AND engagement_id = ?
               AND assignee_type = 'user' AND assignee_value = ?",
            [$tenantOne, $engagementOne, (string) $userOne]
        ),
        $engagementTwo
    );

    check(
        (int) scalar(
            $pdo,
            "SELECT COUNT(*) FROM recruit_engagement_assignments
             WHERE tenant_id = ? AND engagement_id = ?",
            [$tenantOne, $engagementOne]
        ) === 3,
        'Tenant two cannot remove an assignment belonging to tenant one.'
    );

    check(
        count($assignments->forEngagement($engagementTwo)) === 1,
        'Tenant two assignment remains intact after cross-tenant removal attempt.'
    );

    // ---------------------------------------------------------
    // TENANT ONE: VERIFY ITS RECORDS REMAIN INTACT
    // ---------------------------------------------------------

    $tenantContext->set($tenantOne);

    check(
        count($assignments->forEngagement($engagementOne)) === 3,
        'Tenant one retains all its assignments after tenant two operations.'
    );

    $assignments->remove(
        (int) scalar(
            $pdo,
            "SELECT id FROM recruit_engagement_assignments
             WHERE tenant_id = ? AND engagement_id = ?
               AND assignee_type = 'user' AND assignee_value = ?",
            [$tenantOne, $engagementOne, (string) $userOne]
        ),
        $engagementOne
    );

    check(
        (int) scalar(
            $pdo,
            "SELECT COUNT(*) FROM recruit_engagement_assignments
             WHERE tenant_id = ? AND engagement_id = ?
               AND assignee_type = 'user'",
            [$tenantOne, $engagementOne]
        ) === 0,
        'Tenant one can remove its own assignment.'
    );

    check(
        (int) scalar(
            $pdo,
            "SELECT COUNT(*) FROM recruit_engagement_assignments
             WHERE tenant_id = ? AND engagement_id = ?",
            [$tenantOne, $engagementOne]
        ) === 2,
        'Removing one assignment preserves the other assignment types.'
    );

    echo "\nAll EngagementAssignmentRepository tenant-isolation checks passed.\n";

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

<?php

declare(strict_types=1);

use CorporateAid\Core\App;
use CorporateAid\Core\Database;
use CorporateAid\Core\PDOParams;
use CorporateAid\Core\Row;
use CorporateAid\Core\TenantContext;
use CorporateAid\Platform\Authorization\AuthorizationService;
use CorporateAid\Platform\Models\User;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance criteria:
 *   TA-01: A user with a role grant in the active tenant can access that
 *          tenant's permission.
 *   TA-02: A tenant permission requires matching tenant context. Same user,
 *          same permission, different active context → denied.
 *   TA-03: Explicit denial overrides role grant.
 *   TA-04: Policy denial overrides explicit grant.
 */
final class TenantAuthorityTest extends TestCase
{
    private static Database $db;
    private static AuthorizationService $authz;
    private static TenantContext $tenant;
    private static int $originalTenantId;

    public static function setUpBeforeClass(): void
    {
        App::boot(dirname(__DIR__, 2));
        $c = App::instance()->container();
        self::$db     = $c->get(Database::class);
        self::$authz  = AuthorizationService::instance();
        self::$tenant = $c->get(TenantContext::class);
        self::$originalTenantId = self::$tenant->id();
        self::$authz->clearCache();
    }

    public static function tearDownAfterClass(): void
    {
        self::$tenant->set(self::$originalTenantId);
        self::$authz->clearCache();
    }

    /** TA-01: Tenant role grant allows tenant permission. */
    public function testTenantRoleGrantsTenantPermission(): void
    {
        $this->withTenantFixture(function (int $userId, int $tenantId) {
            self::$tenant->set($tenantId);
            self::$authz->clearCache();

            $user = $this->loadUser($userId);
            self::assertTrue(
                self::$authz->allows($user, 'recruit.candidates.view'),
                'User with tenant role grant must be allowed the tenant permission.'
            );
        });
    }

    /** TA-02: Same user, different active context → denied. */
    public function testTenantPermissionRequiresMatchingTenantContext(): void
    {
        $this->withTenantFixture(function (int $userId, int $tenantId, int $otherTenantId) {
            self::$tenant->set($tenantId);
            self::$authz->clearCache();
            $user = $this->loadUser($userId);
            self::assertTrue(
                self::$authz->allows($user, 'recruit.candidates.view'),
                'Sanity: permission allowed in home tenant context.'
            );

            self::$tenant->set($otherTenantId);
            self::$authz->clearCache();
            self::assertFalse(
                self::$authz->allows($user, 'recruit.candidates.view'),
                'Tenant permission must be denied when active context does not match home tenant.'
            );
        });
    }

    /** TA-03: Explicit denial overrides role grant. */
    public function testExplicitDenialOverridesTenantRoleGrant(): void
    {
        $this->withTenantFixture(function (int $userId, int $tenantId) {
            self::$tenant->set($tenantId);
            self::$authz->clearCache();

            $permId = $this->permissionId('recruit.candidates.view');
            self::$db->execute(
                "INSERT INTO `core_user_permissions`
                    (`tenant_id`, `user_id`, `permission_id`, `granted_by`, `denied`)
                 VALUES (?, ?, ?, NULL, 1)",
                PDOParams::positional([$tenantId, $userId, $permId])
            );
            self::$authz->clearCache();

            $user = $this->loadUser($userId);
            self::assertFalse(
                self::$authz->allows($user, 'recruit.candidates.view'),
                'Explicit denial must override tenant role grant (Rule 7).'
            );
        });
    }

    /** TA-04: Policy denial overrides explicit grant. */
    public function testPolicyDenialOverridesExplicitGrant(): void
    {
        $this->withTenantFixture(function (int $userId, int $tenantId) {
            self::$tenant->set($tenantId);

            $permId = $this->permissionId('recruit.candidates.view');

            // Grant explicitly, then add a tenant-wide policy deny.
            self::$db->execute(
                "INSERT INTO `core_user_permissions`
                    (`tenant_id`, `user_id`, `permission_id`, `granted_by`, `denied`)
                 VALUES (?, ?, ?, NULL, 0)",
                PDOParams::positional([$tenantId, $userId, $permId])
            );
            self::$db->execute(
                "INSERT INTO `core_permission_policies`
                    (`tenant_id`, `permission_slug`, `effect`, `reason`)
                 VALUES (?, 'recruit.candidates.view', 'deny', 'ta-04 fixture')",
                PDOParams::positional([$tenantId])
            );
            self::$authz->clearCache();

            $user = $this->loadUser($userId);
            self::assertFalse(
                self::$authz->allows($user, 'recruit.candidates.view'),
                'Policy deny must override explicit grant (Rule 5).'
            );
        });
    }

    /**
     * Creates a tenant fixture with a user, a tenant role granting
     * recruit.candidates.view, and a second tenant for boundary tests.
     * Rolls back after $body runs.
     */
    private function withTenantFixture(callable $body): void
    {
        $original = self::$tenant->id();
        self::$db->beginTransaction();
        try {
            $suffix = bin2hex(random_bytes(6));

            $tenant2 = self::$db->insert(
                "INSERT INTO `core_tenants` (`code`, `name`, `status`)
                 VALUES (?, ?, 'active')",
                PDOParams::positional(["TA-{$suffix}", "TenantAuthorityTest {$suffix}"])
            );

            $roleId = self::$db->insert(
                "INSERT INTO `core_roles`
                    (`tenant_id`, `name`, `slug`, `scope`, `module_key`, `is_system`)
                 VALUES (1, ?, ?, 'app', 'recruit', 0)",
                PDOParams::positional(["TA Role {$suffix}", "ta_role_{$suffix}"])
            );

            $permId = $this->permissionId('recruit.candidates.view');
            self::$db->execute(
                "INSERT INTO `core_role_permissions` (`role_id`, `permission_id`)
                 VALUES (?, ?)",
                PDOParams::positional([$roleId, $permId])
            );

            $userId = self::$db->insert(
                "INSERT INTO `core_users`
                    (`tenant_id`, `name`, `email`, `password_hash`, `status`)
                 VALUES (1, ?, ?, 'test-hash', 'active')",
                PDOParams::positional([
                    "TA-User {$suffix}",
                    "ta-user-{$suffix}@example.test",
                ])
            );

            self::$db->execute(
                "INSERT INTO `core_user_roles`
                    (`tenant_id`, `user_id`, `role_id`, `assigned_by`)
                 VALUES (1, ?, ?, NULL)",
                PDOParams::positional([$userId, $roleId])
            );

            // App assignment is required by Rule 6.
            self::$db->execute(
                "INSERT INTO `core_user_applications`
                    (`user_id`, `module_key`, `is_default`)
                 VALUES (?, 'recruit', 1)",
                PDOParams::positional([$userId])
            );

            $body($userId, 1, $tenant2);
        } finally {
            self::$tenant->set($original);
            self::$authz->clearCache();
            self::$db->rollBack();
        }
    }

    private function permissionId(string $slug): int
    {
        $row = self::$db->selectOne(
            "SELECT `id` FROM `core_permissions` WHERE `slug` = ? AND `is_active` = 1 LIMIT 1",
            PDOParams::positional([$slug])
        );
        if ($row === null) {
            self::fail("Permission '{$slug}' not found or inactive.");
        }
        return Row::int($row, 'id');
    }

    private function loadUser(int $id): User
    {
        $row = self::$db->selectOne(
            "SELECT * FROM `core_users` WHERE `id` = ?",
            PDOParams::positional([$id])
        );
        if ($row === null) {
            self::fail("User {$id} not found.");
        }
        $u = User::fromRow($row);

        // Hydrate roles and permissions mirroring Auth::user() behaviour,
        // scoped to tenant 1 (the fixture's tenant).
        $roles = self::$db->select(
            "SELECT r.`slug` FROM `core_user_roles` ur
             JOIN `core_roles` r ON r.`id` = ur.`role_id`
                               AND r.`tenant_id` = ur.`tenant_id`
             WHERE ur.`user_id` = ? AND ur.`tenant_id` = 1
               AND r.`module_key` = 'recruit'
               AND (ur.`expires_at` IS NULL OR ur.`expires_at` > NOW())",
            PDOParams::positional([$id])
        );
        $u->roleSlugs = array_map(static fn ($r) => Row::str($r, 'slug'), $roles);

        $perms = self::$db->select(
            "SELECT DISTINCT p.`slug` FROM `core_user_roles` ur
             JOIN `core_roles` r ON r.`id` = ur.`role_id` AND r.`tenant_id` = ur.`tenant_id`
             JOIN `core_role_permissions` rp ON rp.`role_id` = ur.`role_id`
             JOIN `core_permissions` p ON p.`id` = rp.`permission_id`
             WHERE ur.`user_id` = ? AND ur.`tenant_id` = 1
               AND r.`module_key` = 'recruit'
               AND (SUBSTRING_INDEX(p.`module`, '.', 1) IN ('recruit','core'))
               AND p.`is_active` = 1
               AND (ur.`expires_at` IS NULL OR ur.`expires_at` > NOW())",
            PDOParams::positional([$id])
        );
        $u->permissionSlugs = array_map(static fn ($p) => Row::str($p, 'slug'), $perms);

        return $u;
    }
}

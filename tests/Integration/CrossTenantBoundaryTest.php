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
 *   CT-01: A user with a tenant-2 role is denied tenant-1 permissions when
 *          the active tenant context is 1.
 *   CT-02: Delegations are isolated by tenant AND user-tenant ownership
 *          (migrated from the earlier AuthorizationTenantBoundaryTest).
 *   CT-03: Permission cache does not pollute across tenant contexts.
 */
final class CrossTenantBoundaryTest extends TestCase
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

    /** CT-01: Foreign-tenant user cannot access tenant-1 permissions. */
    public function testForeignTenantUserDeniedTenantPermission(): void
    {
        $original = self::$tenant->id();
        self::$db->beginTransaction();
        try {
            $suffix = bin2hex(random_bytes(6));

            $tenant2 = self::$db->insert(
                "INSERT INTO `core_tenants` (`code`, `name`, `status`)
                 VALUES (?, ?, 'active')",
                PDOParams::positional(["CT-{$suffix}", "CrossTenant {$suffix}"])
            );

            // Create role in tenant 2 granting recruit.candidates.view.
            $roleId = self::$db->insert(
                "INSERT INTO `core_roles`
                    (`tenant_id`, `name`, `slug`, `scope`, `module_key`, `is_system`)
                 VALUES (?, ?, ?, 'app', 'recruit', 0)",
                PDOParams::positional([$tenant2, "CT Role {$suffix}", "ct_role_{$suffix}"])
            );

            $permRow = self::$db->selectOne(
                "SELECT `id` FROM `core_permissions`
                 WHERE `slug` = 'recruit.candidates.view' AND `is_active` = 1 LIMIT 1"
            );
            if ($permRow === null) {
                self::fail('recruit.candidates.view not seeded.');
            }
            self::$db->execute(
                "INSERT INTO `core_role_permissions` (`role_id`, `permission_id`)
                 VALUES (?, ?)",
                PDOParams::positional([$roleId, Row::int($permRow, 'id')])
            );

            // Tenant-2 user with that role.
            $userId = self::$db->insert(
                "INSERT INTO `core_users`
                    (`tenant_id`, `name`, `email`, `password_hash`, `status`)
                 VALUES (?, ?, ?, 'test-hash', 'active')",
                PDOParams::positional([
                    $tenant2,
                    "CT-User {$suffix}",
                    "ct-user-{$suffix}@example.test",
                ])
            );
            self::$db->execute(
                "INSERT INTO `core_user_roles`
                    (`tenant_id`, `user_id`, `role_id`, `assigned_by`)
                 VALUES (?, ?, ?, NULL)",
                PDOParams::positional([$tenant2, $userId, $roleId])
            );
            self::$db->execute(
                "INSERT INTO `core_user_applications`
                    (`user_id`, `module_key`, `is_default`)
                 VALUES (?, 'recruit', 1)",
                PDOParams::positional([$userId])
            );

            $user = $this->loadUser($userId, $tenant2);

            // Sanity: user has the permission in their own tenant context.
            self::$tenant->set($tenant2);
            self::$authz->clearCache();
            self::assertTrue(
                self::$authz->allows($user, 'recruit.candidates.view'),
                'Sanity: tenant-2 user allowed in tenant-2 context.'
            );

            // Boundary: same user, tenant-1 active context → denied.
            self::$tenant->set(1);
            self::$authz->clearCache();
            self::assertFalse(
                self::$authz->allows($user, 'recruit.candidates.view'),
                'Foreign-tenant user must be denied when active context is tenant 1.'
            );
        } finally {
            self::$tenant->set($original);
            self::$authz->clearCache();
            self::$db->rollBack();
        }
    }

    /**
     * CT-02: Delegations are isolated by tenant AND user-tenant ownership.
     * Migrated from AuthorizationTenantBoundaryTest::testDelegationsAreIsolated...
     */
    public function testDelegationsAreIsolatedByTenantAndUserOwnership(): void
    {
        $original = self::$tenant->id();
        self::$db->beginTransaction();
        try {
            $module = self::$db->selectOne(
                "SELECT `module_key` FROM `core_modules` ORDER BY `module_key` LIMIT 1"
            );
            self::assertNotNull($module, 'At least one registered module is required.');

            $suffix = bin2hex(random_bytes(6));

            $tenant2 = self::$db->insert(
                "INSERT INTO `core_tenants` (`code`, `name`, `status`)
                 VALUES (?, ?, 'active')",
                PDOParams::positional(["CT-{$suffix}", "CT Delegation {$suffix}"])
            );

            $user1 = self::$db->insert(
                "INSERT INTO `core_users`
                    (`tenant_id`, `name`, `email`, `password_hash`, `status`)
                 VALUES (1, ?, ?, 'test-hash', 'active')",
                PDOParams::positional(["CT User One {$suffix}", "ct-one-{$suffix}@example.test"])
            );
            $user2 = self::$db->insert(
                "INSERT INTO `core_users`
                    (`tenant_id`, `name`, `email`, `password_hash`, `status`)
                 VALUES (?, ?, ?, 'test-hash', 'active')",
                PDOParams::positional([
                    $tenant2,
                    "CT User Two {$suffix}",
                    "ct-two-{$suffix}@example.test",
                ])
            );

            $moduleKey = (string) $module['module_key'];
            $insertDelegation = static function (int $uid, int $tid) use ($moduleKey): void {
                self::$db->insert(
                    "INSERT INTO `core_admin_delegations`
                        (`user_id`, `tenant_id`, `module_key`, `granted_by`,
                         `allowed_permissions_json`, `denied_permissions_json`)
                     VALUES (?, ?, ?, ?, '[]', NULL)",
                    PDOParams::positional([$uid, $tid, $moduleKey, $uid])
                );
            };

            $insertDelegation($user1, 1);
            $insertDelegation($user2, $tenant2);
            // Structurally permitted by FKs, business-invalid: user1 is tenant 1, not tenant 2.
            $insertDelegation($user1, $tenant2);

            self::$authz->clearCache();
            self::$tenant->set(1);
            self::assertCount(1, self::$authz->getActiveDelegations($user1));

            self::$tenant->set($tenant2);
            self::assertSame([], self::$authz->getActiveDelegations($user1));

            self::assertCount(1, self::$authz->getActiveDelegations($user2));
        } finally {
            self::$tenant->set($original);
            self::$authz->clearCache();
            self::$db->rollBack();
        }
    }

    /** CT-03: Permission cache does not pollute across tenant contexts. */
    public function testCacheDoesNotPolluteAcrossTenantContexts(): void
    {
        $original = self::$tenant->id();
        self::$db->beginTransaction();
        try {
            $suffix = bin2hex(random_bytes(6));

            $tenant2 = self::$db->insert(
                "INSERT INTO `core_tenants` (`code`, `name`, `status`)
                 VALUES (?, ?, 'active')",
                PDOParams::positional(["CT-{$suffix}", "CT Cache {$suffix}"])
            );

            $roleId = self::$db->insert(
                "INSERT INTO `core_roles`
                    (`tenant_id`, `name`, `slug`, `scope`, `module_key`, `is_system`)
                 VALUES (1, ?, ?, 'app', 'recruit', 0)",
                PDOParams::positional(["CT Cache Role {$suffix}", "ct_cache_{$suffix}"])
            );

            $permRow = self::$db->selectOne(
                "SELECT `id` FROM `core_permissions`
                 WHERE `slug` = 'recruit.candidates.view' AND `is_active` = 1 LIMIT 1"
            );
            self::$db->execute(
                "INSERT INTO `core_role_permissions` (`role_id`, `permission_id`)
                 VALUES (?, ?)",
                PDOParams::positional([$roleId, Row::int($permRow, 'id')])
            );

            $userId = self::$db->insert(
                "INSERT INTO `core_users`
                    (`tenant_id`, `name`, `email`, `password_hash`, `status`)
                 VALUES (1, ?, ?, 'test-hash', 'active')",
                PDOParams::positional(["CT Cache User {$suffix}", "ct-cache-{$suffix}@example.test"])
            );
            self::$db->execute(
                "INSERT INTO `core_user_roles`
                    (`tenant_id`, `user_id`, `role_id`, `assigned_by`)
                 VALUES (1, ?, ?, NULL)",
                PDOParams::positional([$userId, $roleId])
            );
            self::$db->execute(
                "INSERT INTO `core_user_applications`
                    (`user_id`, `module_key`, `is_default`)
                 VALUES (?, 'recruit', 1)",
                PDOParams::positional([$userId])
            );

            $user = $this->loadUser($userId, 1);

            self::$tenant->set(1);
            self::$authz->clearCache();
            self::assertTrue(
                self::$authz->allows($user, 'recruit.candidates.view'),
                'Tenant 1 context: allowed.'
            );

            // Switch to tenant 2 WITHOUT clearing cache — the result must not
            // leak tenant-1 grants into tenant-2 context.
            self::$tenant->set($tenant2);
            self::assertFalse(
                self::$authz->allows($user, 'recruit.candidates.view'),
                'Cache must not return a tenant-1 grant when active context is tenant 2.'
            );
        } finally {
            self::$tenant->set($original);
            self::$authz->clearCache();
            self::$db->rollBack();
        }
    }

    private function loadUser(int $id, int $homeTenant): User
    {
        $row = self::$db->selectOne(
            "SELECT * FROM `core_users` WHERE `id` = ?",
            PDOParams::positional([$id])
        );
        if ($row === null) {
            self::fail("User {$id} not found.");
        }
        $u = User::fromRow($row);

        $roles = self::$db->select(
            "SELECT r.`slug` FROM `core_user_roles` ur
             JOIN `core_roles` r ON r.`id` = ur.`role_id`
                               AND r.`tenant_id` = ur.`tenant_id`
             WHERE ur.`user_id` = ? AND ur.`tenant_id` = ?
               AND r.`module_key` = 'recruit'
               AND (ur.`expires_at` IS NULL OR ur.`expires_at` > NOW())",
            PDOParams::positional([$id, $homeTenant])
        );
        $u->roleSlugs = array_map(static fn ($r) => Row::str($r, 'slug'), $roles);

        $perms = self::$db->select(
            "SELECT DISTINCT p.`slug` FROM `core_user_roles` ur
             JOIN `core_roles` r ON r.`id` = ur.`role_id` AND r.`tenant_id` = ur.`tenant_id`
             JOIN `core_role_permissions` rp ON rp.`role_id` = ur.`role_id`
             JOIN `core_permissions` p ON p.`id` = rp.`permission_id`
             WHERE ur.`user_id` = ? AND ur.`tenant_id` = ?
               AND r.`module_key` = 'recruit'
               AND (SUBSTRING_INDEX(p.`module`, '.', 1) IN ('recruit','core'))
               AND p.`is_active` = 1
               AND (ur.`expires_at` IS NULL OR ur.`expires_at` > NOW())",
            PDOParams::positional([$id, $homeTenant])
        );
        $u->permissionSlugs = array_map(static fn ($p) => Row::str($p, 'slug'), $perms);

        return $u;
    }
}

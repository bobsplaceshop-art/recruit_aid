<?php

declare(strict_types=1);

use CorporateAid\Core\App;
use CorporateAid\Core\Database;
use CorporateAid\Core\PDOParams;
use CorporateAid\Core\Row;
use CorporateAid\Core\TenantContext;
use CorporateAid\Platform\Authorization\AuthorizationService;
use CorporateAid\Platform\Repositories\UserRepository;
use CorporateAid\Platform\Models\User;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance criteria:
 *   PA-01: A user with cas_admin platform role can access cas.dashboard.access
 *          without any tenant role.
 *   PA-02: Platform authority is tenant-context-independent. Switching active
 *          tenant context does not change cas.* evaluation for a platform holder.
 *   PA-03: A platform role does not grant tenant permissions. cas_admin with
 *          no tenant role is denied recruit.candidates.view.
 */
final class PlatformAuthorityTest extends TestCase
{
    private static Database $db;
    private static AuthorizationService $authz;
    private static UserRepository $userRepo;
    private static TenantContext $tenant;
    private static int $originalTenantId;

    public static function setUpBeforeClass(): void
    {
        App::boot(dirname(__DIR__, 2));
        $c = App::instance()->container();
        self::$db     = $c->get(Database::class);
        self::$authz  = AuthorizationService::instance();
        self::$userRepo = $c->get(UserRepository::class);
        self::$tenant = $c->get(TenantContext::class);
        self::$originalTenantId = self::$tenant->id();
        self::$authz->clearCache();
    }

    public static function tearDownAfterClass(): void
    {
        self::$tenant->set(self::$originalTenantId);
        self::$authz->clearCache();
    }

    /**
     * PA-01: cas_admin platform role alone grants cas.dashboard.access.
     */
    public function testPlatformRoleGrantsCasDashboardAccess(): void
    {
        $this->withFixtures(function (int $userId, int $tenantId) {
            self::$tenant->set($tenantId);
            self::$authz->clearCache();

            $user = $this->loadUser($userId);
            self::assertTrue(
                self::$authz->allows($user, 'cas.dashboard.access'),
                'cas_admin platform role must grant cas.dashboard.access.'
            );
        });
    }

    /**
     * PA-02: Platform authority is tenant-context-independent. A cas_admin
     * in tenant 1 retains cas.dashboard.access when the active context is
     * switched to another tenant — cas.* bypasses the tenant identity check.
     */
    public function testPlatformAuthorityIsTenantContextIndependent(): void
    {
        $this->withFixtures(function (int $userId, int $tenantId, int $otherTenantId) {
            self::$tenant->set($tenantId);
            self::$authz->clearCache();
            $user = $this->loadUser($userId);
            self::assertTrue(
                self::$authz->allows($user, 'cas.dashboard.access'),
                'cas.dashboard.access allowed in home tenant context.'
            );

            self::$tenant->set($otherTenantId);
            self::$authz->clearCache();
            self::assertTrue(
                self::$authz->allows($user, 'cas.dashboard.access'),
                'cas.dashboard.access must remain allowed across tenant contexts — '
                . 'platform authority is not tenant-scoped.'
            );
        });
    }

    /**
     * PA-03: Platform role alone does not grant tenant permissions.
     */
    public function testPlatformRoleDoesNotGrantTenantPermissions(): void
    {
        $this->withFixtures(function (int $userId, int $tenantId) {
            self::$tenant->set($tenantId);
            self::$authz->clearCache();

            $user = $this->loadUser($userId);
            self::assertFalse(
                self::$authz->allows($user, 'recruit.candidates.view'),
                'cas_admin without a tenant role must be denied tenant permissions.'
            );
        });
    }

    /**
     * Executes $body inside a transaction with two users (one cas_admin in
     * tenant 1, one plain user in another tenant) and rolls back after.
     */
    private function withFixtures(callable $body): void
    {
        $original = self::$tenant->id();
        self::$db->beginTransaction();
        try {
            $suffix = bin2hex(random_bytes(6));

            $tenant2 = self::$db->insert(
                "INSERT INTO `core_tenants` (`code`, `name`, `status`)
                 VALUES (?, ?, 'active')",
                PDOParams::positional(["PA-{$suffix}", "PlatformAuthorityTest {$suffix}"])
            );

            $casRoleId = $this->platformRoleId('cas_admin');

            $userId = self::$db->insert(
                "INSERT INTO `core_users`
                    (`tenant_id`, `name`, `email`, `password_hash`, `status`)
                 VALUES (1, ?, ?, 'test-hash', 'active')",
                PDOParams::positional([
                    "PA-User {$suffix}",
                    "pa-user-{$suffix}@example.test",
                ])
            );

            self::$db->execute(
                "INSERT INTO `core_user_platform_roles` (`user_id`, `role_id`)
                 VALUES (?, ?)",
                PDOParams::positional([$userId, $casRoleId])
            );

            $body($userId, 1, $tenant2);
        } finally {
            self::$tenant->set($original);
            self::$authz->clearCache();
            self::$db->rollBack();
        }
    }

    private function platformRoleId(string $slug): int
    {
        $row = self::$db->selectOne(
            "SELECT `id` FROM `core_platform_roles` WHERE `slug` = ? LIMIT 1",
            PDOParams::positional([$slug])
        );
        if ($row === null) {
            self::fail("Platform role '{$slug}' not found — run migration 0061.");
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
        // Populate permissionSlugs via the production path so Rule 9
        // (role grant) sees the same data as runtime.
        return self::$userRepo->loadPermissions(User::fromRow($row));
    }

    /**
     * Fixture: a user in platform_owners, optionally also cas_admin.
     * Runs inside a transaction; rolls back after.
     */
    private function withDeveloperFixtures(callable $body, bool $withCasAdminRole = true): void
    {
        $original = self::$tenant->id();
        self::$db->beginTransaction();
        try {
            $suffix = bin2hex(random_bytes(6));

            $tenantB = self::$db->insert(
                "INSERT INTO `core_tenants` (`code`, `name`, `status`)
                 VALUES (?, ?, 'active')",
                PDOParams::positional(["PA-Dev-{$suffix}", "DevFixture {$suffix}"])
            );

            $userId = self::$db->insert(
                "INSERT INTO `core_users`
                    (`tenant_id`, `name`, `email`, `password_hash`, `status`)
                 VALUES (1, ?, ?, 'test-hash', 'active')",
                PDOParams::positional([
                    "PA-Dev {$suffix}",
                    "pa-dev-{$suffix}@example.test",
                ])
            );

            if ($withCasAdminRole) {
                $casRoleId = $this->platformRoleId('cas_admin');
                self::$db->execute(
                    "INSERT INTO `core_user_platform_roles` (`user_id`, `role_id`)
                     VALUES (?, ?)",
                    PDOParams::positional([$userId, $casRoleId])
                );
            }

            self::$db->execute(
                "INSERT INTO `platform_owners` (`user_id`, `designation`, `contract_reference`)
                 VALUES (?, 'Test Owner', 'TEST-REF')",
                PDOParams::positional([$userId])
            );

            $body($userId, $tenantB);
        } finally {
            self::$tenant->set($original);
            self::$authz->clearCache();
            self::$db->rollBack();
        }
    }

    /** PA-04: Developer bypasses tenant-role requirement for tenant permission. */
    public function testDeveloperBypassesTenantRoleForTenantPermission(): void
    {
        $this->withDeveloperFixtures(function (int $userId, int $tenantB) {
            self::$tenant->set(1);
            self::$authz->clearCache();
            $user = $this->loadUser($userId);
            self::assertTrue(
                self::$authz->allows($user, 'recruit.candidates.view'),
                'Developer with no tenant role must be allowed tenant permission via Rule 3b.'
            );
        });
    }

    /** PA-05: Developer access is independent of effective tenant context. */
    public function testDeveloperBypassesTenantScopingAcrossTenants(): void
    {
        $this->withDeveloperFixtures(function (int $userId, int $tenantB) {
            self::$tenant->set($tenantB);
            self::$authz->clearCache();
            $user = $this->loadUser($userId);
            self::assertTrue(
                self::$authz->allows($user, 'recruit.candidates.view'),
                'Developer must be allowed in cross-tenant context.'
            );
        });
    }

    /** PA-06: Rule 3b does not bypass Rule 2 (unknown permission). */
    public function testDeveloperDeniedUnknownPermission(): void
    {
        $this->withDeveloperFixtures(function (int $userId, int $tenantB) {
            self::$tenant->set(1);
            self::$authz->clearCache();
            $user = $this->loadUser($userId);
            self::assertFalse(
                self::$authz->allows($user, 'not.a.real.permission'),
                'Developer must be denied for unknown permission (Rule 2).'
            );
        });
    }

    /** PA-07: Rule 3b does not bypass Rule 2 (inactive permission). */
    public function testDeveloperDeniedInactivePermission(): void
    {
        $this->withDeveloperFixtures(function (int $userId, int $tenantB) {
            $slug = 'recruit.inactive.' . bin2hex(random_bytes(4));
            self::$db->execute(
                "INSERT INTO `core_permissions` (`slug`, `module`, `description`, `scope`, `module_key`, `sensitivity`, `is_active`)
                 VALUES (?, 'recruit', 'Test inactive', 'app', 'recruit', 'public', 0)",
                PDOParams::positional([$slug])
            );
            self::$tenant->set(1);
            self::$authz->clearCache();
            $user = $this->loadUser($userId);
            self::assertFalse(
                self::$authz->allows($user, $slug),
                'Developer must be denied for inactive permission (Rule 2).'
            );
        });
    }

    /** PA-08: Rule 3b does NOT grant platform-domain (scope='global') permissions. */
    public function testDeveloperDeniedPlatformDomainPermission(): void
    {
        $this->withDeveloperFixtures(function (int $userId, int $tenantB) {
            self::$tenant->set(1);
            self::$authz->clearCache();
            $user = $this->loadUser($userId);
            self::assertFalse(
                self::$authz->allows($user, 'core.settings.manage'),
                'Developer must not gain platform-domain permission via Rule 3b.'
            );
        }, false);
    }

    /** PA-09: Developer without cas_admin role is denied cas.* (Rule 3 governs). */
    public function testDeveloperDeniedCasPermissionWithoutAssignment(): void
    {
        $this->withDeveloperFixtures(function (int $userId, int $tenantB) {
            self::$tenant->set(1);
            self::$authz->clearCache();
            $user = $this->loadUser($userId);
            self::assertFalse(
                self::$authz->allows($user, 'cas.rbac.manage_global'),
                'Developer must be denied cas.* without explicit platform permission.'
            );
        }, false);
    }

    /** PA-10: Ordinary tenant user without permission is denied. */
    public function testTenantUserWithoutPermissionDenied(): void
    {
        $this->withFixtures(function (int $userId, int $tenantId) {
            self::$tenant->set($tenantId);
            self::$authz->clearCache();
            $user = $this->loadUser($userId);
            self::assertFalse(
                self::$authz->allows($user, 'recruit.candidates.view'),
                'Non-Developer without tenant role must be denied.'
            );
        });
    }

    /** PA-11: Tenant role does not grant Developer authority (Rule 3b is membership-only). */
    public function testTenantRoleDoesNotGrantDeveloperAuthority(): void
    {
        $this->withFixtures(function (int $userId, int $tenantId) {
            $suffix = bin2hex(random_bytes(4));
            $roleId = self::$db->insert(
                "INSERT INTO `core_roles` (`tenant_id`, `name`, `slug`, `scope`, `module_key`, `is_system`)
                 VALUES (?, ?, ?, 'app', 'recruit', 0)",
                PDOParams::positional([$tenantId, "T-{$suffix}", "t_{$suffix}"])
            );
            $permRow = self::$db->selectOne(
                "SELECT id FROM `core_permissions` WHERE slug = 'recruit.candidates.view' LIMIT 1"
            );
            self::$db->execute(
                "INSERT INTO `core_role_permissions` (`role_id`, `permission_id`) VALUES (?, ?)",
                PDOParams::positional([$roleId, Row::int($permRow, 'id')])
            );
            self::$db->execute(
                "INSERT INTO `core_user_roles` (`tenant_id`, `user_id`, `role_id`, `assigned_by`)
                 VALUES (?, ?, ?, NULL)",
                PDOParams::positional([$tenantId, $userId, $roleId])
            );
            self::$db->execute(
                "INSERT INTO `core_user_applications` (`user_id`, `module_key`, `is_default`)
                 VALUES (?, 'recruit', 1)",
                PDOParams::positional([$userId])
            );
            self::$tenant->set($tenantId);
            self::$authz->clearCache();
            $user = $this->loadUser($userId);

            self::assertTrue(self::$authz->allows($user, 'recruit.candidates.view'));
            self::assertFalse(
                self::$authz->allows($user, 'hr.employees.view'),
                'Tenant role must not become Developer authority.'
            );
        });
    }
}

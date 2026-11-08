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
        return User::fromRow($row);
    }
}

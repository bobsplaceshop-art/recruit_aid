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
 * Historical: pre-0061 this file tested super_admin-based boundary rules.
 * Post-0061 (super_admin deleted), those rules are gone. What remains here
 * is the boundary-enforcement tests that survived the migration, plus one
 * re-framed assertion that matches the new model:
 *
 *   - Unauthenticated users are denied (Rule 1).
 *   - cas.* is evaluated via core_user_platform_roles, not tenant identity.
 *   - Delegations are tenant- and user-scoped.
 *
 * The full three-level RBAC coverage lives in:
 *   - PlatformAuthorityTest
 *   - TenantAuthorityTest
 *   - CrossTenantBoundaryTest
 *
 * This file is the compatibility surface for older test references.
 */
final class AuthorizationTenantBoundaryTest extends TestCase
{
    private static AuthorizationService $authz;
    private static Database $db;
    private static TenantContext $tenant;

    public static function setUpBeforeClass(): void
    {
        App::boot(dirname(__DIR__, 2));
        self::$authz = AuthorizationService::instance();
        self::$db    = App::instance()->container()->get(Database::class);
        self::$tenant = App::instance()->container()->get(TenantContext::class);
        self::$authz->clearCache();
    }

    public function testUnauthenticatedUserIsDenied(): void
    {
        self::assertFalse(
            self::$authz->allows(null, 'cas.dashboard.access')
        );
    }

    /**
     * Re-framed: post-0061, cas.* does not require tenant identity.
     * A user without a platform role is denied — but for the correct reason
     * (no platform role assignment), not because tenant identity is missing.
     */
    public function testPlatformPermissionRequiresPlatformRoleNotTenantIdentity(): void
    {
        $user = User::fromRow([
            'id' => 999000,
            'tenant_id' => 1,
            'name' => 'Stub User Without Platform Role',
            'email' => 'stub@example.test',
            'password_hash' => 'test',
            'status' => 'active',
        ]);

        self::assertFalse(
            self::$authz->allows($user, 'cas.dashboard.access'),
            'A user with no cas_admin platform role is denied cas.* — '
            . 'not because tenant identity is missing, but because the '
            . 'platform permission check fails.'
        );
    }

    public function testDelegationsAreIsolatedByTenantAndUserOwnership(): void
    {
        $originalTenantId = self::$tenant->id();
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
                PDOParams::positional(["ATB-{$suffix}", "ATB {$suffix}"])
            );

            $user1 = self::$db->insert(
                "INSERT INTO `core_users`
                    (`tenant_id`, `name`, `email`, `password_hash`, `status`)
                 VALUES (1, ?, ?, 'test-hash', 'active')",
                PDOParams::positional(["ATB One {$suffix}", "atb-one-{$suffix}@example.test"])
            );
            $user2 = self::$db->insert(
                "INSERT INTO `core_users`
                    (`tenant_id`, `name`, `email`, `password_hash`, `status`)
                 VALUES (?, ?, ?, 'test-hash', 'active')",
                PDOParams::positional([
                    $tenant2,
                    "ATB Two {$suffix}",
                    "atb-two-{$suffix}@example.test",
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
            $insertDelegation($user1, $tenant2);

            self::$authz->clearCache();
            self::$tenant->set(1);
            self::assertCount(1, self::$authz->getActiveDelegations($user1));

            self::$tenant->set($tenant2);
            self::assertSame([], self::$authz->getActiveDelegations($user1));

            self::assertCount(1, self::$authz->getActiveDelegations($user2));
        } finally {
            self::$tenant->set($originalTenantId);
            self::$authz->clearCache();
            self::$db->rollBack();
        }
    }
}

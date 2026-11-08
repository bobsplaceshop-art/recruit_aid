<?php
declare(strict_types=1);

use CorporateAid\Core\App;
use CorporateAid\Core\Auth;
use CorporateAid\Core\Database;
use CorporateAid\Core\PDOParams;
use CorporateAid\Core\Row;
use CorporateAid\Core\TenantContext;
use CorporateAid\Platform\Authorization\AuthorizationService;
use CorporateAid\Platform\Authorization\SupportDelegationService;
use CorporateAid\Platform\Models\User;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance criteria (see docs/RBAC-TESTING.md):
 *   SD-01: read_only grant materializes only view/list/read/export/search
 *   SD-02: permits() honors scope ceiling on read_only
 *   SD-03: permits() honors explicit denial (denied_permissions_json)
 *   SD-04: full scope allows edit but not delete without explicit opt-in
 *   SD-05: cas.* slugs rejected at grant time
 *   SD-06: AuthorizationService honors an active support delegation
 *   SD-06b: Auth::user() survives effective-tenant override (home tenant identity preserved)
 *   SD-07: Same user denied in a tenant with no delegation
 *   SD-08: tenant_admin grant → revoke enters revocation_pending
 *   SD-09: cas_developer grant → revoke is immediate
 *   SD-10: cancelPendingRevocation reverts to active
 */
final class SupportDelegationTest extends TestCase
{
    private static Database $db;
    private static AuthorizationService $authz;
    private static SupportDelegationService $delegations;
    private static TenantContext $tenant;
    private static Auth $auth;
    private static int $originalTenantId;

    // Shared fixture IDs, created once in a wrapping transaction that
    // covers the whole class. Every test runs inside this fixture.
    private static int $tenantA = 1;
    private static int $tenantB = 0;
    private static int $casAdminId = 0;
    private static int $devUserId = 0;
    private static int $tenantAdminId = 0;

    public static function setUpBeforeClass(): void
    {
        App::boot(dirname(__DIR__, 2));
        $c = App::instance()->container();
        self::$db          = $c->get(Database::class);
        self::$authz       = AuthorizationService::instance();
        self::$delegations = $c->get(SupportDelegationService::class);
        self::$tenant      = $c->get(TenantContext::class);
        self::$auth        = $c->get(Auth::class);
        self::$originalTenantId = self::$tenant->id();
        self::$authz->clearCache();

        self::$db->beginTransaction();

        $suffix = bin2hex(random_bytes(4));

        // Second tenant
        self::$tenantB = self::$db->insert(
            "INSERT INTO `core_tenants` (`code`, `name`, `status`)
             VALUES (?, ?, 'active')",
            PDOParams::positional(["SD-{$suffix}", "SupportDelegationTest {$suffix}"])
        );

        // cas_admin user in tenant A (home tenant 1)
        self::$casAdminId = self::$db->insert(
            "INSERT INTO `core_users`
                (`tenant_id`, `name`, `email`, `password_hash`, `status`)
             VALUES (1, ?, ?, 'test-hash', 'active')",
            PDOParams::positional(["SD Cas Admin {$suffix}", "sd-cas-{$suffix}@example.test"])
        );
        $casAdminRoleRow = self::$db->selectOne(
            "SELECT `id` FROM `core_platform_roles` WHERE `slug` = 'cas_admin'"
        );
        if ($casAdminRoleRow === null) {
            self::fail('cas_admin platform role not found — run migration 0061.');
        }
        self::$db->execute(
            "INSERT INTO `core_user_platform_roles` (`user_id`, `role_id`) VALUES (?, ?)",
            PDOParams::positional([self::$casAdminId, Row::int($casAdminRoleRow, 'id')])
        );

        // cas_developer identity: a second cas_admin user is used as the grantor.
        // (In production, the developer is recorded in platform_owners; for tests
        // we exercise the grantor-type enum, which is what the code checks.)
        self::$devUserId = self::$db->insert(
            "INSERT INTO `core_users`
                (`tenant_id`, `name`, `email`, `password_hash`, `status`)
             VALUES (1, ?, ?, 'test-hash', 'active')",
            PDOParams::positional(["SD Developer {$suffix}", "sd-dev-{$suffix}@example.test"])
        );
        self::$db->execute(
            "INSERT INTO `core_user_platform_roles` (`user_id`, `role_id`) VALUES (?, ?)",
            PDOParams::positional([self::$devUserId, Row::int($casAdminRoleRow, 'id')])
        );

        // tenant_admin user in tenant B with an active role there
        self::$tenantAdminId = self::$db->insert(
            "INSERT INTO `core_users`
                (`tenant_id`, `name`, `email`, `password_hash`, `status`)
             VALUES (?, ?, ?, 'test-hash', 'active')",
            PDOParams::positional([
                self::$tenantB,
                "SD Tenant Admin {$suffix}",
                "sd-tadmin-{$suffix}@example.test",
            ])
        );
        $adminRoleRow = self::$db->selectOne(
            "SELECT `id` FROM `core_roles`
             WHERE `slug` = 'admin' AND `tenant_id` = ? LIMIT 1",
            PDOParams::positional([self::$tenantB])
        );
        if ($adminRoleRow === null) {
            // Tenant B has no admin role; provision a lightweight role.
            $roleId = self::$db->insert(
                "INSERT INTO `core_roles`
                    (`tenant_id`, `name`, `slug`, `scope`, `module_key`, `is_system`)
                 VALUES (?, 'SD Admin', ?, 'app', 'recruit', 0)",
                PDOParams::positional([self::$tenantB, "sd_admin_{$suffix}"])
            );
        } else {
            $roleId = Row::int($adminRoleRow, 'id');
        }
        self::$db->execute(
            "INSERT INTO `core_user_roles`
                (`tenant_id`, `user_id`, `role_id`, `assigned_by`)
             VALUES (?, ?, ?, NULL)",
            PDOParams::positional([self::$tenantB, self::$tenantAdminId, $roleId])
        );

        // Ensure Recruit app is enabled and assigned to both tenants.
        self::$db->execute(
            "INSERT IGNORE INTO `core_module_state` (`module_key`, `is_enabled`)
             VALUES ('recruit', 1)"
        );
        foreach ([self::$tenantA, self::$tenantB] as $tid) {
            self::$db->execute(
                "INSERT IGNORE INTO `core_user_applications`
                    (`user_id`, `module_key`, `is_default`)
                 VALUES (?, 'recruit', 1)",
                PDOParams::positional([self::$casAdminId])
            );
        }
    }

    public static function tearDownAfterClass(): void
    {
        self::$db->rollBack();
        self::$tenant->set(self::$originalTenantId);
        self::$tenant->setEffective(1);
        self::$authz->clearCache();
    }

    // -----------------------------------------------------------------
    // Service-level tests (SupportDelegationService)
    // -----------------------------------------------------------------

    /** SD-01: read_only materializes only non-destructive read suffixes. */
    public function testReadOnlyMaterializesOnlyReadSuffixes(): void
    {
        $id = null;

        try {
            $id = self::$delegations->grant([
                'user_id'              => self::$casAdminId,
                'tenant_id'            => self::$tenantB,
                'module_key'           => 'recruit',
                'granted_by_user_id'   => self::$devUserId,
                'granted_by_type'      => 'cas_developer',
                'scope'                => 'read_only',
                'reason'               => 'SD-01 test fixture',
                'expires_at'           => date('Y-m-d H:i:s', time() + 3600),
            ]);

            $row = self::$db->selectOne(
                "SELECT `allowed_permissions_json` FROM `core_support_delegations`
                 WHERE `id` = ?",
                PDOParams::positional([$id])
            );

            $allowed = json_decode(Row::str($row, 'allowed_permissions_json'), true);

            self::assertIsArray($allowed);
            self::assertNotEmpty($allowed);

            foreach ($allowed as $slug) {
                $suffix = substr($slug, strrpos($slug, '.') + 1);

                self::assertContains(
                    $suffix,
                    ['view', 'list', 'read', 'export', 'search'],
                    "read_only must not grant suffix '{$suffix}'"
                );
            }
        } finally {
            if ($id !== null) {
                self::$delegations->revoke($id, self::$devUserId);
                self::$authz->clearCache();
            }
        }
    }

    /** SD-02: permits() honors scope ceiling under read_only. */
    public function testReadOnlyScopeCeilingInPermits(): void
    {
        $delegation = [
            'scope'  => 'read_only',
            'allowed' => ['recruit.candidates.view'],
            'denied'  => [],
        ];
        self::assertTrue(
            self::$delegations->permits($delegation, 'recruit.candidates.view')
        );
        self::assertFalse(
            self::$delegations->permits($delegation, 'recruit.candidates.edit')
        );
    }

    /** SD-03: permits() honors explicit denial. */
    public function testExplicitDenialOverridesAllowlist(): void
    {
        $delegation = [
            'scope'  => 'read_only',
            'allowed' => ['recruit.candidates.view', 'recruit.candidates.export'],
            'denied'  => ['recruit.candidates.export'],
        ];
        self::assertTrue(
            self::$delegations->permits($delegation, 'recruit.candidates.view')
        );
        self::assertFalse(
            self::$delegations->permits($delegation, 'recruit.candidates.export'),
            'Explicit denial must override the allowlist.'
        );
    }

    /** SD-04: full scope allows edit but blocks delete without explicit opt-in. */
    public function testFullScopeBlocksDestructiveWithoutExplicitOptIn(): void
    {
        $id = null;

        try {
            $id = self::$delegations->grant([
                'user_id'              => self::$casAdminId,
                'tenant_id'            => self::$tenantB,
                'module_key'           => 'recruit',
                'granted_by_user_id'   => self::$devUserId,
                'granted_by_type'      => 'cas_developer',
                'scope'                => 'full',
                'reason'               => 'SD-04 test fixture',
                'expires_at'           => date('Y-m-d H:i:s', time() + 3600),
            ]);

            $row = self::$db->selectOne(
                "SELECT `allowed_permissions_json` FROM `core_support_delegations`
                 WHERE `id` = ?",
                PDOParams::positional([$id])
            );

            $allowed = json_decode(Row::str($row, 'allowed_permissions_json'), true);

            foreach ($allowed as $slug) {
                $suffix = substr($slug, strrpos($slug, '.') + 1);

                self::assertNotContains(
                    $suffix,
                    ['delete', 'manage', 'purge', 'restore'],
                    "full must not auto-grant destructive suffix '{$suffix}'"
                );
            }

            $delegation = [
                'scope'   => 'full',
                'allowed' => $allowed,
                'denied'  => [],
            ];

            self::assertFalse(
                self::$delegations->permits($delegation, 'recruit.candidates.delete'),
                'Delete is destructive and requires explicit opt-in even under full scope.'
            );
        } finally {
            if ($id !== null) {
                self::$delegations->revoke($id, self::$devUserId);
                self::$authz->clearCache();
            }
        }
    }

    /** SD-05: cas.* slugs rejected at grant time. */
    public function testCasSlugsRejectedAtGrantTime(): void
    {
        $this->expectException(InvalidArgumentException::class);
        self::$delegations->grant([
            'user_id'              => self::$casAdminId,
            'tenant_id'            => self::$tenantB,
            'module_key'           => 'recruit',
            'granted_by_user_id'   => self::$devUserId,
            'granted_by_type'      => 'cas_developer',
            'scope'                => 'full',
            'reason'               => 'SD-05 negative test',
            'expires_at'           => date('Y-m-d H:i:s', time() + 3600),
            'denied_permissions'   => ['cas.dashboard.access'],
        ]);
    }

    // -----------------------------------------------------------------
    // Integration tests (AuthorizationService + Auth)
    // -----------------------------------------------------------------

    /** SD-06: AuthorizationService honors an active delegation. */
    public function testAuthorizationServiceHonorsActiveDelegation(): void
    {
        // Fixture: fresh delegation, read_only, tenant B.
        $id = self::$delegations->grant([
            'user_id'              => self::$casAdminId,
            'tenant_id'            => self::$tenantB,
            'module_key'           => 'recruit',
            'granted_by_user_id'   => self::$devUserId,
            'granted_by_type'      => 'cas_developer',
            'scope'                => 'read_only',
            'reason'               => 'SD-06 fixture',
            'expires_at'           => date('Y-m-d H:i:s', time() + 3600),
        ]);

        // Effective tenant is B. cas_admin's home tenant is 1.
        self::$tenant->setEffective(self::$tenantB);
        self::$authz->clearCache();

        $user = $this->loadUser(self::$casAdminId);
        self::assertTrue(
            self::$authz->allows($user, 'recruit.candidates.view'),
            'Active read_only delegation to tenant B must allow candidates.view in tenant B context.'
        );

        // Clean up the fixture delegation so subsequent tests start clean.
        self::$delegations->revoke($id, self::$devUserId);
        self::$authz->clearCache();
    }

    /** SD-06b: Auth::user() survives effective-tenant override. */
    public function testAuthUserSurvivesEffectiveTenantOverride(): void
    {
        $id = self::$delegations->grant([
            'user_id'              => self::$casAdminId,
            'tenant_id'            => self::$tenantB,
            'module_key'           => 'recruit',
            'granted_by_user_id'   => self::$devUserId,
            'granted_by_type'      => 'cas_developer',
            'scope'                => 'read_only',
            'reason'               => 'SD-06b fixture',
            'expires_at'           => date('Y-m-d H:i:s', time() + 3600),
        ]);

        // Simulate login: cas_admin in home tenant 1, then effective = tenant B.
        $user = $this->loadUser(self::$casAdminId);
        self::$auth->login($user);
        self::$tenant->setEffective(self::$tenantB);
        self::$authz->clearCache();

        $reloaded = self::$auth->user();
        self::assertNotNull(
            $reloaded,
            'Auth::user() must load the user via home tenant, not effective.'
        );
        self::assertSame(self::$casAdminId, $reloaded->id);
        self::assertSame(1, $reloaded->tenantId, 'Home tenant must remain 1.');

        self::assertTrue(
            self::$auth->can('recruit.candidates.view'),
            'Auth::can() must honor the delegation in the effective tenant.'
        );

        self::$delegations->revoke($id, self::$devUserId);
        self::$auth->logout();
        self::$tenant->setEffective(1);
        self::$authz->clearCache();
    }

    /** SD-07: Same user denied in a tenant with no delegation. */
    public function testUserDeniedInTenantWithNoDelegation(): void
    {
        self::$tenant->setEffective(self::$tenantB);
        self::$authz->clearCache();

        $user = $this->loadUser(self::$casAdminId);
        self::assertFalse(
            self::$authz->allows($user, 'recruit.candidates.view'),
            'No delegation exists now; access must be denied.'
        );
        self::$tenant->setEffective(1);
        self::$authz->clearCache();
    }

    // -----------------------------------------------------------------
    // Revocation asymmetry
    // -----------------------------------------------------------------

    /** SD-08: tenant_admin grant → revoke enters revocation_pending. */
    public function testTenantAdminRevocationIsPending(): void
    {
        $id = self::$delegations->grant([
            'user_id'              => self::$casAdminId,
            'tenant_id'            => self::$tenantB,
            'module_key'           => 'recruit',
            'granted_by_user_id'   => self::$tenantAdminId,
            'granted_by_type'      => 'tenant_admin',
            'scope'                => 'read_only',
            'reason'               => 'SD-08 fixture',
            'expires_at'           => date('Y-m-d H:i:s', time() + 3600),
        ]);

        self::$delegations->revoke($id, self::$tenantAdminId);

        $row = self::$db->selectOne(
            "SELECT `revocation_state`, `revocation_effective_at`
             FROM `core_support_delegations` WHERE `id` = ?",
            PDOParams::positional([$id])
        );
        self::assertSame('revocation_pending', Row::str($row, 'revocation_state'));
        self::assertNotNull(Row::nullableStr($row, 'revocation_effective_at'));
    }

    /** SD-09: cas_developer grant → revoke is immediate. */
    public function testDeveloperRevocationIsImmediate(): void
    {
        $id = self::$delegations->grant([
            'user_id'              => self::$casAdminId,
            'tenant_id'            => self::$tenantB,
            'module_key'           => 'recruit',
            'granted_by_user_id'   => self::$devUserId,
            'granted_by_type'      => 'cas_developer',
            'scope'                => 'read_only',
            'reason'               => 'SD-09 fixture',
            'expires_at'           => date('Y-m-d H:i:s', time() + 3600),
        ]);

        self::$delegations->revoke($id, self::$devUserId);

        $row = self::$db->selectOne(
            "SELECT `revocation_state`, `revoked_at`
             FROM `core_support_delegations` WHERE `id` = ?",
            PDOParams::positional([$id])
        );
        self::assertSame('revoked', Row::str($row, 'revocation_state'));
        self::assertNotNull(Row::nullableStr($row, 'revoked_at'));
    }

    /** SD-10: cancelPendingRevocation reverts to active. */
    public function testDeveloperCanCancelPendingRevocation(): void
    {
        $id = null;

        try {
            $id = self::$delegations->grant([
                'user_id'              => self::$casAdminId,
                'tenant_id'            => self::$tenantB,
                'module_key'           => 'recruit',
                'granted_by_user_id'   => self::$tenantAdminId,
                'granted_by_type'      => 'tenant_admin',
                'scope'                => 'read_only',
                'reason'               => 'SD-10 fixture',
                'expires_at'           => date('Y-m-d H:i:s', time() + 3600),
            ]);

            self::$delegations->revoke($id, self::$tenantAdminId);
            self::$delegations->cancelPendingRevocation($id, self::$devUserId);

            $row = self::$db->selectOne(
                "SELECT `revocation_state`
                 FROM `core_support_delegations` WHERE `id` = ?",
                PDOParams::positional([$id])
            );

            self::assertSame('active', Row::str($row, 'revocation_state'));
        } finally {
            if ($id !== null) {
                self::$delegations->revoke($id, self::$devUserId);
                self::$authz->clearCache();
            }
        }
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

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
             JOIN `core_roles` r ON r.`id` = ur.`role_id`
                               AND r.`tenant_id` = ur.`tenant_id`
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

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
 * Resource-gating acceptance criteria for the platform surface.
 *
 * RG-01 (runtime): cas.dashboard.access grants the dashboard only. A cas_admin
 *   with no tenant role and no delegation is denied every tenant-scoped
 *   resource permission on the same tenant.
 *
 * RG-02 (static, route declaration): every /cas/* route in routes/web.php
 *   carries a "permission:" middleware entry. Adding a /cas/* route without
 *   a permission gate fails this test loudly.
 *
 * RG-03 (static, controller source): the dashboard controller selects
 *   aggregate data only. Adding a per-record PII read fails this test loudly.
 */
final class PlatformSurfaceTest extends TestCase
{
    private static Database $db;
    private static AuthorizationService $authz;
    private static TenantContext $tenant;
    private static int $originalTenantId;
    private static int $casAdminId = 0;

    public static function setUpBeforeClass(): void
    {
        App::boot(dirname(__DIR__, 2));
        $c = App::instance()->container();
        self::$db     = $c->get(Database::class);
        self::$authz  = AuthorizationService::instance();
        self::$tenant = $c->get(TenantContext::class);
        self::$originalTenantId = self::$tenant->id();
        self::$authz->clearCache();

        self::$db->beginTransaction();

        $suffix = bin2hex(random_bytes(4));
        self::$casAdminId = self::$db->insert(
            "INSERT INTO `core_users`
                (`tenant_id`, `name`, `email`, `password_hash`, `status`)
             VALUES (1, ?, ?, 'test-hash', 'active')",
            PDOParams::positional(["PS Cas Admin {$suffix}", "ps-cas-{$suffix}@example.test"])
        );
        $casAdminRoleRow = self::$db->selectOne(
            "SELECT `id` FROM `core_platform_roles` WHERE `slug` = 'cas_admin'"
        );
        if ($casAdminRoleRow === null) {
            self::fail('cas_admin platform role not found.');
        }
        self::$db->execute(
            "INSERT INTO `core_user_platform_roles` (`user_id`, `role_id`) VALUES (?, ?)",
            PDOParams::positional([self::$casAdminId, Row::int($casAdminRoleRow, 'id')])
        );
    }

    public static function tearDownAfterClass(): void
    {
        self::$db->rollBack();
        self::$tenant->set(self::$originalTenantId);
        self::$tenant->setEffective(self::$originalTenantId);
        self::$authz->clearCache();
    }

    /**
     * RG-01: The dashboard permission does not grant any resource permission.
     *
     * A cas_admin holds cas.dashboard.access via their platform role. In their
     * home tenant context, they are denied every tenant-scoped resource
     * permission — because they have no tenant role and no support delegation.
     */
    public function testDashboardPermissionDoesNotGrantResourcePermission(): void
    {
        self::$tenant->set(1);
        self::$tenant->setEffective(1);
        self::$authz->clearCache();

        $user = $this->loadUser(self::$casAdminId);

        // Sanity: the platform role grants the dashboard.
        self::assertTrue(
            self::$authz->allows($user, 'cas.dashboard.access'),
            'A cas_admin must be allowed cas.dashboard.access.'
        );

        // Doctrine: dashboard permission does not grant tenant resources.
        foreach ([
            'recruit.candidates.view',
            'recruit.candidates.edit',
            'recruit.offers.send',
            'core.settings.manage',
        ] as $permission) {
            self::assertFalse(
                self::$authz->allows($user, $permission),
                "cas.dashboard.access must NOT grant tenant resource permission '{$permission}'."
            );
        }
    }

    /**
     * RG-02: Every /cas/* route declares a permission: middleware.
     *
     * Static test against routes/web.php. Adding a /cas/* route without a
     * permission gate is caught here — no DB, no runtime.
     */
    public function testEveryDashboardTileTargetHasPermissionMiddleware(): void
    {
        $routesPath = dirname(__DIR__, 2) . '/routes/web.php';
        self::assertFileExists($routesPath, 'routes/web.php must exist.');

        $src = file_get_contents($routesPath);
        self::assertIsString($src);

        // Match each /cas/* route declaration and capture its middleware list.
        // Format: $router->get('/cas/path', [Controller::class, 'method'], ['mw', 'permission:slug'])
        $pattern = '/\$router->(?:get|post|put|patch|delete)\(\s*[\'"](\/cas\/[^\'"]*)[\'"]\s*,\s*\[[^\]]+\]\s*,\s*\[([^\]]*)\]\s*\)/s';
        preg_match_all($pattern, $src, $matches, PREG_SET_ORDER);

        self::assertGreaterThan(
            0,
            count($matches),
            'At least one /cas/* route must be declared in routes/web.php.'
        );

        foreach ($matches as $m) {
            $path = $m[1];
            $middleware = $m[2];

            self::assertStringContainsString(
                'permission:',
                $middleware,
                "Route {$path} must declare a 'permission:' middleware. Middleware list: [{$middleware}]"
            );
        }
    }

    /**
     * RG-03: Dashboard controller queries are aggregates only.
     *
     * Static test against CasDashboardController.php. Adding a per-record PII
     * read (email, phone, DOB, password hash, or SELECT *) is caught here.
     */
    public function testAnalyticsQueriesReturnAggregatesOnly(): void
    {
        $controllerPath = dirname(__DIR__, 2) . '/app/Platform/Controllers/Cas/CasDashboardController.php';
        self::assertFileExists($controllerPath);

        $src = file_get_contents($controllerPath);
        self::assertIsString($src);

        // Forbidden patterns — the dashboard must not select per-record PII.
        $forbidden = [
            '/\bSELECT\s+\*\s+FROM\b/i'                  => 'SELECT * is forbidden in dashboard queries.',
            '/\bSELECT\s+[^;"\']*\b`email`/i'            => 'Selecting the email column is forbidden.',
            '/\bSELECT\s+[^;"\']*\b`password_hash`/i'    => 'Selecting password_hash is forbidden.',
            '/\bSELECT\s+[^;"\']*\b`date_of_birth`/i'    => 'Selecting date_of_birth is forbidden.',
            '/\bSELECT\s+[^;"\']*\b`phone`/i'            => 'Selecting phone is forbidden.',
        ];

        foreach ($forbidden as $pattern => $message) {
            self::assertDoesNotMatchRegularExpression($pattern, $src, $message);
        }

        // Sanity: the dashboard uses at least one aggregate query.
        self::assertMatchesRegularExpression(
            '/\bCOUNT\s*\(\s*\*/i',
            $src,
            'Dashboard controller should issue at least one aggregate (COUNT(*)) query.'
        );
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

        $roles = self::$db->select(
            "SELECT r.`slug` FROM `core_user_roles` ur
             JOIN `core_roles` r ON r.`id` = ur.`role_id` AND r.`tenant_id` = ur.`tenant_id`
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

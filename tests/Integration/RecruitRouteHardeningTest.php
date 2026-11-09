<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Structural test: every /recruit/* route is either:
 *   (a) gated by `permission:` middleware at the route layer, or
 *   (b) explicitly listed in the allowlist below with a documented
 *       justification (self-service or polymorphic).
 *
 * A new route that is neither will fail this test loudly, preventing
 * the RBAC gap from re-opening as new features land.
 *
 * Mirrors the pattern of PlatformSurfaceTest (RG-02) for /cas/* routes.
 */
final class RecruitRouteHardeningTest extends TestCase
{
    /**
     * Routes that legitimately have no `permission:` middleware.
     * Every entry must have a justification comment.
     */
    private const ALLOWLIST = [
        // --- Self-service (operates on the current user's own data) ---
        '/recruit/dashboard',
        '/recruit/account',
        '/recruit/account/password',
        '/recruit/account/theme',
        '/recruit/account/confirm-password',
        '/recruit/notifications',
        '/recruit/notifications/{id}/read',
        '/recruit/notifications/read-all',
        '/recruit/api/notifications',

        // --- Polymorphic (permission derived from request path or entity links) ---
        '/recruit/files/{uuid}',
        '/recruit/{entity}/export.csv',
        '/recruit/search',
        '/recruit/api/search',
        '/recruit/api/live-search',
        '/recruit/activity',
        '/recruit/activity/{type}/{id}',

        // --- Coming-soon placeholders (render static pages) ---
        '/recruit/tasks',
        '/recruit/calendar',
        '/recruit/documents',
        '/recruit/admin/audit',
        '/recruit/admin/branding',
        '/recruit/admin/email-templates',
    ];

    public function testEveryRecruitRouteIsGatedOrAllowlisted(): void
    {
        $routesPath = dirname(__DIR__, 2) . '/routes/web.php';
        self::assertFileExists($routesPath);

        $src = file_get_contents($routesPath);
        self::assertIsString($src);

        // Match each /recruit/* route declaration and capture its middleware.
        // Format: $router->method('/recruit/path', [Handler], [middleware])
        // Also matches closure routes: $router->method('/recruit/path', function ...
        $pattern = '/\$router->(?:get|post|put|patch|delete)\(\s*[\'"](\/recruit\/[^\'"]*)[\'"]\s*,(?:[^\[]|\[[^\]]*\])+?\[([^\]]*)\]\s*\)/s';
        preg_match_all($pattern, $src, $matches, PREG_SET_ORDER);

        self::assertGreaterThan(
            50,
            count($matches),
            'Expected at least 50 /recruit/* routes; regex may have missed some.'
        );

        $ungated = [];
        foreach ($matches as $m) {
            $path = $m[1];
            $middleware = $m[2];

            // If it has permission: middleware, it's gated. Pass.
            if (str_contains($middleware, 'permission:')) {
                continue;
            }

            // Otherwise, must be in the allowlist.
            if (!in_array($path, self::ALLOWLIST, true)) {
                $ungated[] = $path . ' (middleware: [' . trim($middleware) . '])';
            }
        }

        self::assertSame(
            [],
            $ungated,
            "Routes without permission: middleware and not in the allowlist:\n  - "
                . implode("\n  - ", $ungated)
                . "\n\nEither add a 'permission:...' middleware or add the route to "
                . "RecruitRouteHardeningTest::ALLOWLIST with a justification comment."
        );
    }

    public function testAllowlistEntriesAllExistInRoutes(): void
    {
        $routesPath = dirname(__DIR__, 2) . '/routes/web.php';
        $src = file_get_contents($routesPath);
        self::assertIsString($src);

        foreach (self::ALLOWLIST as $path) {
            self::assertStringContainsString(
                "'" . $path . "'",
                $src,
                "Allowlisted path '{$path}' is not declared in routes/web.php. "
                    . "Remove it from the allowlist or restore the route."
            );
        }
    }
}

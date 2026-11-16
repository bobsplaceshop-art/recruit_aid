<?php
declare(strict_types=1);

use CorporateAid\Core\App;
use CorporateAid\Core\Router;
use PHPUnit\Framework\TestCase;

/**
 * Controller URL audit — verifies Response::redirect() calls and
 * formAction values in controllers match registered routes.
 *
 * RouteAuditTest only scans view files (href/action literals). This
 * catches the class of bug where the route migration forgot to update
 * controller-generated URLs, leaving redirects that 404 after any
 * form submit.
 */
final class ControllerUrlAuditTest extends TestCase
{
    private static array $routePatterns = [];

    public static function setUpBeforeClass(): void
    {
        App::boot(dirname(__DIR__, 2));
        $c = App::instance()->container();
        $router = $c->get(Router::class);

        $allRoutes = $router->allRoutes();
        foreach (['GET', 'POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
            foreach ($allRoutes[$method] ?? [] as $route) {
                self::$routePatterns[] = $route['pattern'];
            }
        }
    }

    public function testEveryControllerRedirectMatchesARoute(): void
    {
        self::assertNotEmpty(self::$routePatterns, 'Router has no routes.');

        $controllersDir = dirname(__DIR__, 2) . '/app/Recruit/Controllers';
        $broken = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($controllersDir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') continue;
            $contents = (string) file_get_contents($file->getPathname());

            // Find Response::redirect('/...') calls
            if (!preg_match_all("/Response::redirect\\('([^']+)'/", $contents, $matches)) {
                continue;
            }

            foreach ($matches[1] as $url) {
                // Skip dynamic (PHP variable concatenation) and external URLs.
                if (str_contains($url, '$') || str_contains($url, '{')
                    || str_starts_with($url, 'http') || str_starts_with($url, '//')) {
                    continue;
                }

                if (!$this->pathMatchesAnyRoute($url)) {
                    $broken[] = sprintf(
                        '%s → %s',
                        str_replace(dirname(__DIR__, 2) . '/', '', $file->getPathname()),
                        $url
                    );
                }
            }

            // Find formAction => '/...' in controller render arrays
            if (!preg_match_all("/'formAction'\\s*=>\\s*'([^']+)'/", $contents, $matches2)) {
                continue;
            }

            foreach ($matches2[1] as $url) {
                if (str_contains($url, '$') || str_contains($url, '{')) continue;
                if (!$this->pathMatchesAnyRoute($url)) {
                    $broken[] = sprintf(
                        '%s → formAction: %s',
                        str_replace(dirname(__DIR__, 2) . '/', '', $file->getPathname()),
                        $url
                    );
                }
            }
        }

        self::assertEmpty($broken, "Broken controller URLs:\n" . implode("\n", $broken));
    }

    private function pathMatchesAnyRoute(string $url): bool
    {
        // Strip query string and trailing slash.
        $path = parse_url($url, PHP_URL_PATH);
        if ($path === false || $path === null) {
            $path = $url;
        }
        $path = rtrim($path, '/') ?: '/';
        foreach (self::$routePatterns as $pattern) {
            if (@preg_match($pattern, $path) === 1) return true;
        }
        return false;
    }
}

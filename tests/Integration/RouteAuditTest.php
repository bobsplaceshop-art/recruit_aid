<?php
declare(strict_types=1);

use CorporateAid\Core\App;
use CorporateAid\Core\Router;
use PHPUnit\Framework\TestCase;

/**
 * Route audit — verifies every link in a view points at a registered route.
 *
 * Catches:
 *   - Static view links that don't match any route (404 at click time).
 *   - Dynamic-prefix bugs: href="/engagements/<?= $code ?>" when the
 *     route is /recruit/engagements/{code}. The static prefix before the
 *     first <? is tested against route patterns' static prefixes.
 *
 * Known limitation: pure-ternary dynamic links
 * (href="<?= $c ? '/a' : '/b' ?>") cannot be reliably validated by
 * static analysis — literal extraction false-positives on concatenation
 * fragments like '/contacts/'. These require manual review.
 */
final class RouteAuditTest extends TestCase
{
    /** @var array<int, string> */
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

    public function testEveryViewLinkMatchesARoute(): void
    {
        self::assertNotEmpty(self::$routePatterns, 'Router has no routes — boot failed?');

        $viewsDir = dirname(__DIR__, 2) . '/resources/views';
        $broken = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($viewsDir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') continue;

            $contents = (string) file_get_contents($file->getPathname());
            if (!preg_match_all('/(?:href|action)="([^"]*)"/', $contents, $matches)) {
                continue;
            }

            foreach ($matches[1] as $href) {
                if (
                    $href === ''
                    || str_starts_with($href, 'http')
                    || str_starts_with($href, '//')
                    || str_starts_with($href, '#')
                    || str_starts_with($href, '?')
                    || str_starts_with($href, 'javascript:')
                    || str_starts_with($href, 'mailto:')
                    || str_starts_with($href, 'tel:')
                    || str_starts_with($href, '/assets/')
                ) {
                    continue;
                }

                if (str_contains($href, '<?')) {
                    $prefix = substr($href, 0, (int) strpos($href, '<?'));
                    if ($prefix !== '' && !$this->prefixMatchesAnyRoute($prefix)) {
                        $broken[] = sprintf(
                            '%s → %s (dynamic link, bad prefix)',
                            str_replace(dirname(__DIR__, 2) . '/', '', $file->getPathname()),
                            $href
                        );
                    }
                    continue;
                }

                $path = (string) (parse_url($href, PHP_URL_PATH) ?: $href);
                $path = rtrim($path, '/') ?: '/';

                if (!$this->pathMatchesAnyRoute($path)) {
                    $broken[] = sprintf(
                        '%s → %s',
                        str_replace(dirname(__DIR__, 2) . '/', '', $file->getPathname()),
                        $href
                    );
                }
            }
        }

        self::assertEmpty(
            $broken,
            "Broken view links (no matching route):\n" . implode("\n", $broken)
        );
    }

    private function pathMatchesAnyRoute(string $path): bool
    {
        foreach (self::$routePatterns as $pattern) {
            if (@preg_match($pattern, $path) === 1) {
                return true;
            }
        }
        return false;
    }

    private function prefixMatchesAnyRoute(string $prefix): bool
    {
        if ($prefix === '' || $prefix === '/') {
            return true;
        }

        foreach (self::$routePatterns as $pattern) {
            $path = preg_replace('/^#\^/', '', $pattern) ?? $pattern;
            $path = preg_replace('/\$#$/', '', $path) ?? $path;

            $staticEnd = strcspn($path, '([');
            $routeStatic = substr($path, 0, $staticEnd);

            if ($routeStatic !== ''
                && (str_starts_with($routeStatic, $prefix)
                    || str_starts_with($prefix, $routeStatic))) {
                return true;
            }
        }
        return false;
    }
}

<?php
declare(strict_types=1);

use CorporateAid\Core\App;
use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Core\Router;
use PHPUnit\Framework\TestCase;

/**
 * Route smoke test — dispatches a real request through the router for
 * every GET route and asserts the response is not a 404.
 *
 * This does NOT test the view links (RouteAuditTest does that). It tests
 * that the route itself resolves to a controller/closure and doesn't
 * blow up during dispatch.
 *
 * Skip routes with dynamic parameters (e.g. {id}) because we can't supply
 * valid data for every model type. Skip POST/PUT/DELETE because they have
 * side effects and CSRF checks.
 */
final class RouteSmokeTest extends TestCase
{
    private static array $routes = [];
    private static Router $router;

    public static function setUpBeforeClass(): void
    {
        App::boot(dirname(__DIR__, 2));
        $c = App::instance()->container();
        self::$router = $c->get(Router::class);

        $allRoutes = self::$router->allRoutes();
        // Only test GET routes that don't have dynamic parameters
        foreach ($allRoutes['GET'] ?? [] as $route) {
            $pattern = $route['pattern'];
            // Skip patterns with regex groups (dynamic params)
            if (str_contains($pattern, '(') || str_contains($pattern, '[')) {
                continue;
            }
            // Extract the static path from the regex pattern
            $path = preg_replace('/^#\^|\$#$/', '', $pattern);
            self::$routes[] = $path;
        }
    }

    public function testEveryStaticGetRouteReturnsNot404(): void
    {
        self::assertNotEmpty(self::$routes, 'No static GET routes found to test.');

        $failures = [];

        foreach (self::$routes as $path) {
            $request = new Request('GET', $path, '', [], [], [], [], [], [], null);
            
            try {
                $response = self::$router->dispatch($request);
                $status = $response->statusCode();
                
                // We expect 200 (OK), 302 (Redirect), 403 (Forbidden), etc.
                // We ONLY fail if it's 404 (route not found) or 500 (server error).
                if ($status === 404 || $status >= 500) {
                    $failures[] = "GET {$path} returned status {$status}";
                }
            } catch (\Throwable $e) {
                $failures[] = "GET {$path} threw exception: " . $e->getMessage();
            }
        }

        self::assertEmpty($failures, "Route smoke test failures:\n" . implode("\n", $failures));
    }
}

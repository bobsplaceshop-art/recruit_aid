<?php
declare(strict_types=1);

namespace CorporateAid\Core\Http;

use CorporateAid\Core\Auth;
use CorporateAid\Core\Container;
use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Platform\Middleware\AuditLoggerMiddleware;
use CorporateAid\Platform\Middleware\AuthMiddleware;
use CorporateAid\Platform\Middleware\AppAccessMiddleware;
use CorporateAid\Platform\Middleware\CsrfMiddleware;
use CorporateAid\Platform\Middleware\CustomFieldFileMiddleware;
use CorporateAid\Platform\Middleware\GuestMiddleware;
use CorporateAid\Platform\Middleware\PermissionMiddleware;
use CorporateAid\Platform\Middleware\RateLimitMiddleware;
use RuntimeException;

/**
 * Middleware registry + pipeline runner.
 *
 * ------------------------------------------------------------------
 * Middleware model: ONION (as of 2026-09-24)
 * ------------------------------------------------------------------
 *
 * Every middleware wraps the controller call. It receives the request
 * and a `$next` callable. It may:
 *
 *   - return its own Response (short-circuit; $next not called)
 *   - call $next($request) and return its Response (pass through)
 *   - call $next($request), inspect/modify the Response, then return
 *     (wrap the response — e.g. audit logging, compression, timing)
 *
 * Signature:
 *     handle(Request $request, callable $next): Response
 *
 * All middleware in app/Middleware/ uses this signature. The earlier
 * gate-style bridge (handle(Request): ?Response) was removed once the
 * migration completed. See docs/CHANGELOG-middleware.md.
 *
 * ------------------------------------------------------------------
 * Route spec grammar
 * ------------------------------------------------------------------
 *   ['auth']                        → AuthMiddleware
 *   ['guest']                       → GuestMiddleware
 *   ['csrf']                        → CsrfMiddleware
 *   ['permission:vacancies.create'] → PermissionMiddleware('vacancies.create')
 *   ['ratelimit:action,max,window'] → RateLimitMiddleware(action, max, window)
 *   ['audit']                       → AuditLoggerMiddleware
 */
final class Kernel
{
    private Container $container;

    /** @var array<string, class-string> */
    private array $map = [
        'auth'       => AuthMiddleware::class,
        'guest'      => GuestMiddleware::class,
        'csrf'       => CsrfMiddleware::class,
        'cffile'     => CustomFieldFileMiddleware::class,
        'permission' => PermissionMiddleware::class,
        'ratelimit'  => RateLimitMiddleware::class,
        'audit'      => AuditLoggerMiddleware::class,
        'app_access' => AppAccessMiddleware::class,
    ];

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Run the middleware pipeline around the controller callable.
     *
     * @param string[]                    $names   Middleware specs in outer-to-inner order
     * @param Request                     $request
     * @param callable(Request):Response  $handler Invokes the controller
     * @return Response                            Response from the innermost callable,
     *                                             possibly wrapped by one or more middlewares
     */
    public function run(array $names, Request $request, callable $handler): Response
    {
        // Build the chain from the inside out. Each wrapper captures the
        // previous chain ($next) in a closure. The outer-most middleware
        // receives the request first and the response last.
        $chain = $handler;

        foreach (array_reverse($names) as $spec) {
            $instance = $this->resolve($spec);
            $next = $chain;

            $chain = function (Request $r) use ($instance, $next): Response {
                return $this->invokeMiddleware($instance, $r, $next);
            };
        }

        return $chain($request);
    }

    /**
     * Invoke a single middleware.
     *
     * Kept as a named method (rather than inlining in run()) so future
     * instrumentation — timing, error wrapping, debug logging — has a
     * single, obvious home.
     */
    private function invokeMiddleware(object $instance, Request $request, callable $next): Response
    {
        if (!method_exists($instance, 'handle')) {
            throw new RuntimeException('Middleware instance is missing a handle() method: ' . get_class($instance));
        }
        /** @var Response $response */
        $response = $instance->handle($request, $next);
        return $response;
    }

    /**
     * Build a middleware instance from a route spec string.
     */
    private function resolve(string $spec): object
    {
        $parts = explode(':', $spec, 2);
        $name = $parts[0];
        $arg = $parts[1] ?? null;

        if (!isset($this->map[$name])) {
            throw new RuntimeException("Unknown middleware: {$spec}");
        }

        $class = $this->map[$name];

        if ($name === 'permission') {
            return new PermissionMiddleware($this->container->get(Auth::class), (string) $arg);
        }

        if ($name === 'app_access') {
            return new AppAccessMiddleware($this->container, (string) $arg);
        }

        if ($name === 'ratelimit') {
            $bits = explode(',', (string) $arg);
            $bits[1] = $bits[1] ?? '5';
            $bits[2] = $bits[2] ?? '15';
            return new RateLimitMiddleware(
                $this->container->get(\CorporateAid\Platform\Support\RateLimiter::class),
                trim((string) $bits[0]),
                (int) $bits[1],
                (int) $bits[2]
            );
        }

        if (!$this->container->has($class)) {
            throw new RuntimeException(
                "Middleware not registered in container: {$class}. " .
                "Add it to App::registerServices()."
            );
        }

        return $this->container->get($class);
    }
}

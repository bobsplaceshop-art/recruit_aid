<?php
declare(strict_types=1);

namespace CorporateAid\Core;

use CorporateAid\Core\Exceptions\HttpException;
use CorporateAid\Core\Http\Kernel;

/**
 * Matches incoming requests to routes and dispatches to controllers.
 *
 * Middleware model: onion. Route middleware wraps the controller call,
 * so middleware can act both before and after the controller runs
 * (audit logging, response headers, compression, timing, etc.).
 *
 * See App\Http\Kernel for the pipeline implementation.
 */
final class Router
{
    /** @var array<string, array<int, array{pattern:string, handler:mixed, params:array, middleware:array}>> */
    private array $routes = [
        'GET' => [], 'POST' => [], 'PUT' => [], 'PATCH' => [], 'DELETE' => [],
    ];

    private Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function get(string $path, mixed $handler, array $middleware = []): void    { $this->add('GET', $path, $handler, $middleware); }
    public function post(string $path, mixed $handler, array $middleware = []): void   { $this->add('POST', $path, $handler, $middleware); }
    public function put(string $path, mixed $handler, array $middleware = []): void    { $this->add('PUT', $path, $handler, $middleware); }
    public function patch(string $path, mixed $handler, array $middleware = []): void  { $this->add('PATCH', $path, $handler, $middleware); }
    public function delete(string $path, mixed $handler, array $middleware = []): void { $this->add('DELETE', $path, $handler, $middleware); }

    private function add(string $method, string $path, mixed $handler, array $middleware): void
    {
        [$pattern, $paramNames] = $this->compilePattern($path);
        $this->routes[$method][] = [
            'pattern'    => $pattern,
            'handler'    => $handler,
            'params'     => $paramNames,
            'middleware' => $middleware,
        ];
    }

    private function compilePattern(string $path): array
    {
        $path = rtrim($path, '/') ?: '/';
        $paramNames = [];
        $regex = preg_replace_callback(
            '#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#',
            static function (array $m) use (&$paramNames): string {
                $paramNames[] = $m[1];
                return '([^/]+)';
            },
            $path
        );
        return ['#^' . $regex . '$#', $paramNames];
    }

    public function dispatch(Request $request): Response
    {
        $method = $request->method();
        $path = $request->path();

        if (!isset($this->routes[$method])) {
            throw HttpException::notFound();
        }

        foreach ($this->routes[$method] as $route) {
            if (!preg_match($route['pattern'], $path, $matches)) {
                continue;
            }

            array_shift($matches);
            $params = [];
            foreach ($route['params'] as $i => $name) {
                $params[$name] = $matches[$i] ?? null;
            }

            $handler = fn (Request $r): Response => $this->invoke($route['handler'], $r, $params);

            // Global audit: inject the audit middleware for all state-changing
            // methods. Routes never need to remember to add 'audit' themselves
            // — the guarantee is structural. GET/HEAD/OPTIONS are read-only and
            // not logged.
            //
            // Placed at the OUTERMOST position so it runs even when other
            // middleware (csrf, permission) short-circuits and produces a
            // 403/419. A rejected POST still leaves a trace — security-critical.
            $middleware = $route['middleware'];
            if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
                array_unshift($middleware, 'audit');
            }

            // No middleware at all (after injection) — call directly.
            if (empty($middleware)) {
                return $handler($request);
            }

            /** @var Kernel $kernel */
            $kernel = $this->container->get(Kernel::class);
            return $kernel->run($middleware, $request, $handler);
        }

        throw HttpException::notFound();
    }

    private function invoke(mixed $handler, Request $request, array $params): Response
    {
        if (is_array($handler) && isset($handler[0], $handler[1])) {
            $class = $handler[0];
            $method = $handler[1];
            if (!class_exists($class)) {
                throw new \RuntimeException("Controller not found: {$class}");
            }
            $controller = new $class($this->container);
            if (!method_exists($controller, $method)) {
                throw new \RuntimeException("Method {$method} not found on {$class}");
            }
            return $controller->{$method}($request, $params);
        }

        if ($handler instanceof \Closure) {
            return $handler($request, $params);
        }

        throw new \RuntimeException('Invalid route handler.');
    }
}

<?php

declare(strict_types=1);

use CAS\SecurityAudit\Analyzers\ControllerAuthorizationAnalyzer;
use CAS\SecurityAudit\Analyzers\RouteAnalyzer;

require dirname(__DIR__, 2) . '/vendor/autoload.php';
require __DIR__ . '/analyzers/RouteAnalyzer.php';
require __DIR__ . '/analyzers/ControllerAuthorizationAnalyzer.php';

$root = dirname(__DIR__, 2);
$analyzer = new ControllerAuthorizationAnalyzer();
$routeAnalyzer = new RouteAnalyzer();

$controllerMethods = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator(
        $root . '/app',
        FilesystemIterator::SKIP_DOTS
    )
);

foreach ($iterator as $file) {
    if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
        continue;
    }

    foreach ($analyzer->analyzeFile($file->getPathname()) as $method) {
        $key = $method['short_class'] . '::' . $method['method'];
        $controllerMethods[$key][] = $method;
    }
}

// Match App::loadRoutes() and route-audit.php exactly.
$routeLoadOrder = ['web', 'auth', 'client', 'public'];
$registrations = [];
$registrationOrder = 0;

foreach ($routeLoadOrder as $loadIndex => $name) {
    $routeFile = $root . '/routes/' . $name . '.php';

    if (!is_file($routeFile)) {
        continue;
    }

    foreach ($routeAnalyzer->analyzeFile($routeFile) as $route) {
        $route['file'] = str_replace($root . '/', '', $routeFile);
        $route['load_order'] = $loadIndex + 1;
        $route['registration_order'] = ++$registrationOrder;
        $registrations[] = $route;
    }
}

// First matching literal method/path registration wins at runtime.
$routes = [];
$seenRouteKeys = [];
$shadowedRouteRegistrations = 0;

foreach ($registrations as $route) {
    if ($route['path'] === null) {
        $routes[] = $route;
        continue;
    }

    $key = $route['method'] . ' ' . $route['path'];

    if (isset($seenRouteKeys[$key])) {
        $shadowedRouteRegistrations++;
        continue;
    }

    $seenRouteKeys[$key] = true;
    $routes[] = $route;
}

$results = [];

foreach ($routes as $route) {
    $handlerClass = $route['handler']['class'] ?? null;
    $handlerMethod = $route['handler']['method'] ?? null;

    $middlewareNames = array_map(
        static fn(string $item): string => strtolower(trim(explode(':', $item, 2)[0])),
        $route['middleware']
    );

    $hasPermissionMiddleware = in_array('permission', $middlewareNames, true);
    $hasAuthMiddleware = in_array('auth', $middlewareNames, true);

    $base = [
        'route' => $route,
        'auth_middleware' => $hasAuthMiddleware,
        'permission_middleware' => $hasPermissionMiddleware,
        'middleware_known' => $route['middleware_known'],
        'controller_method' => null,
    ];

    if ($handlerClass === null || $handlerMethod === null) {
        $status = !$route['middleware_known'] && !$hasPermissionMiddleware
            ? 'MIDDLEWARE_UNKNOWN'
            : 'UNRESOLVED_HANDLER';

        $results[] = array_merge($base, [
            'status' => $status,
            'confidence' => 'COVERAGE_GAP',
            'message' => $hasPermissionMiddleware
                ? 'Closure or dynamic handler could not be resolved. Explicit permission middleware is declared and should be verified separately.'
                : (!$route['middleware_known']
                    ? 'Handler is unresolved and middleware contains dynamic expressions. Authorization coverage cannot be established.'
                    : 'Handler is a closure or dynamic expression. Route middleware was still analysed, but controller-level checks cannot be correlated.'),
        ]);
        continue;
    }

    $shortClass = substr($handlerClass, strrpos('\\' . $handlerClass, '\\'));
    $key = $shortClass . '::' . $handlerMethod;
    $candidates = $controllerMethods[$key] ?? [];

    if (count($candidates) > 1) {
        $exact = array_values(array_filter(
            $candidates,
            static fn(array $candidate): bool =>
                ltrim($candidate['class'], '\\') === ltrim($handlerClass, '\\')
        ));

        if (count($exact) === 1) {
            $controllerMethod = $exact[0];
        } else {
            $results[] = array_merge($base, [
                'status' => 'AMBIGUOUS_HANDLER',
                'confidence' => 'COVERAGE_GAP',
                'candidate_classes' => array_column($candidates, 'class'),
                'message' => 'Multiple controller classes match this short class and method name. The handler cannot be safely correlated.',
            ]);
            continue;
        }
    } else {
        $controllerMethod = $candidates[0] ?? null;
    }

    if ($controllerMethod === null) {
        $results[] = array_merge($base, [
            'status' => 'CONTROLLER_METHOD_NOT_FOUND',
            'confidence' => 'COVERAGE_GAP',
            'message' => 'Could not resolve the route handler to an application class method.',
        ]);
        continue;
    }

    if ($hasPermissionMiddleware) {
        $status = 'ROUTE_PERMISSION_MIDDLEWARE';
        $message = 'Route declares explicit permission middleware.';
        $confidence = 'HIGH';
    } elseif (!$route['middleware_known']) {
        $status = 'MIDDLEWARE_UNKNOWN';
        $message = 'Middleware contains dynamic expressions. Absence of permission middleware cannot be established.';
        $confidence = 'COVERAGE_GAP';
    } elseif ($controllerMethod['has_explicit_authorization']) {
        $status = 'CONTROLLER_AUTHORIZATION_PRESENT';
        $message = 'Controller method contains an authorization-related call. Verify its condition, permission and execution path.';
        $confidence = 'REVIEW';
    } else {
        $status = 'NO_EXPLICIT_PERMISSION_FOUND';
        $message = 'No explicit permission middleware or recognized authorization call was found in the controller method. Service-level checks, inherited methods and indirect authorization are not yet correlated.';
        $confidence = 'REVIEW';
    }

    $results[] = array_merge($base, [
        'status' => $status,
        'confidence' => $confidence,
        'controller_method' => [
            'class' => $controllerMethod['class'],
            'method' => $controllerMethod['method'],
            'file' => str_replace($root . '/', '', $controllerMethod['file']),
            'line' => $controllerMethod['line'],
            'authorization_calls' => $controllerMethod['authorization_calls'],
        ],
        'message' => $message,
    ]);
}

$summary = [];

foreach ($results as $result) {
    $summary[$result['status']] = ($summary[$result['status']] ?? 0) + 1;
}

$report = [
    'generated_at' => date(DATE_ATOM),
    'scope' => 'Application PHP classes and route declarations',
    'controller_methods_discovered' => count($controllerMethods),
    'route_registrations_discovered' => count($registrations),
    'shadowed_route_registrations' => $shadowedRouteRegistrations,
    'effective_routes_correlated' => count($results),
    'route_loading_order' => $routeLoadOrder,
    'dispatch_strategy' => 'First matching registration wins',
    'summary' => $summary,
    'results' => $results,
];

$outputDirectory = __DIR__ . '/reports';

if (!is_dir($outputDirectory)) {
    mkdir($outputDirectory, 0775, true);
}

$outputFile = $outputDirectory . '/controller-auth-audit.json';

file_put_contents(
    $outputFile,
    json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
);

echo "CONTROLLER AUTHORIZATION AUDIT\n";
echo "==============================\n";
echo "Controller methods: {$report['controller_methods_discovered']}\n";
echo "Route registrations discovered: {$report['route_registrations_discovered']}\n";
echo "Shadowed registrations: {$report['shadowed_route_registrations']}\n";
echo "Effective routes correlated: {$report['effective_routes_correlated']}\n\n";

foreach ($summary as $status => $count) {
    printf("%-38s %d\n", $status, $count);
}

echo "\nREVIEW ITEMS\n";

foreach ($results as $result) {
    if ($result['status'] === 'ROUTE_PERMISSION_MIDDLEWARE') {
        continue;
    }

    $route = $result['route'];
    $controller = $result['controller_method'];

    printf(
        "\n[%s] %s %s\n  Route: %s:%d\n  Handler: %s::%s\n  %s\n",
        $result['confidence'],
        $route['method'],
        $route['path'] ?? '[dynamic path]',
        $route['file'],
        $route['line'],
        $controller['class'] ?? ($route['handler']['class'] ?? '[unresolved]'),
        $controller['method'] ?? ($route['handler']['method'] ?? '[unresolved]'),
        $result['message']
    );

    if ($controller !== null && $controller['authorization_calls'] !== []) {
        foreach ($controller['authorization_calls'] as $call) {
            printf(
                "  Authorization call: %s (%s), controller line %d\n",
                $call['name'],
                $call['kind'],
                $call['line']
            );
        }
    }
}

echo "\nJSON report: {$outputFile}\n";

<?php

declare(strict_types=1);

use CAS\SecurityAudit\Analyzers\RouteAnalyzer;

require dirname(__DIR__, 2) . '/vendor/autoload.php';
require __DIR__ . '/analyzers/RouteAnalyzer.php';

$root = dirname(__DIR__, 2);
$routeRoot = $root . '/routes';
$analyzer = new RouteAnalyzer();

// Must match App::loadRoutes() exactly.
$routeLoadOrder = ['web', 'auth', 'client', 'public'];
$routes = [];
$registrationOrder = 0;

foreach ($routeLoadOrder as $loadIndex => $name) {
    $absolutePath = $routeRoot . '/' . $name . '.php';

    if (!is_file($absolutePath)) {
        continue;
    }

    foreach ($analyzer->analyzeFile($absolutePath) as $route) {
        $route['file'] = str_replace($root . '/', '', $absolutePath);
        $route['load_order'] = $loadIndex + 1;
        $route['registration_order'] = ++$registrationOrder;
        $routes[] = $route;
    }
}

$findings = [];

$addFinding = static function (
    string $rule,
    string $severity,
    string $confidence,
    string $message,
    array $route,
    array $evidence = []
) use (&$findings): void {
    $findings[] = [
        'rule' => $rule,
        'severity' => $severity,
        'confidence' => $confidence,
        'message' => $message,
        'method' => $route['method'],
        'path' => $route['path'],
        'handler' => $route['handler'],
        'file' => $route['file'],
        'line' => $route['line'],
        'middleware' => $route['middleware'],
        'evidence' => $evidence,
    ];
};

$middlewareNames = static function (array $route): array {
    return array_values(array_unique(array_map(
        static fn(string $item): string =>
            strtolower(trim(explode(':', $item, 2)[0])),
        $route['middleware']
    )));
};

$getPermissionPolicies = static function (array $route): array {
    $policies = [];

    foreach ($route['middleware'] as $item) {
        if (str_starts_with(strtolower($item), 'permission:')) {
            $policies[] = trim(substr($item, strlen('permission:')));
        }
    }

    return array_values(array_unique($policies));
};

$registrationEvidence = static function (array $items): array {
    return array_map(static fn(array $item): array => [
        'file' => $item['file'],
        'line' => $item['line'],
        'load_order' => $item['load_order'],
        'registration_order' => $item['registration_order'],
        'middleware' => $item['middleware'],
        'middleware_known' => $item['middleware_known'],
        'handler' => $item['handler'],
    ], $items);
};

$groups = [];

foreach ($routes as $route) {
    if ($route['path'] !== null) {
        $groups[$route['method'] . ' ' . $route['path']][] = $route;
    }
}

// One consolidated finding per duplicate method/path.
foreach ($groups as $key => $registrations) {
    if (count($registrations) < 2) {
        continue;
    }

    // Router::add() appends registrations. dispatch() returns the first match.
    $effective = $registrations[0];
    $effectiveNames = $middlewareNames($effective);
    $allMiddlewareNames = [];
    $permissionPolicies = [];
    $handlerSignatures = [];
    $middlewareUnknown = false;

    foreach ($registrations as $registration) {
        $allMiddlewareNames = array_merge(
            $allMiddlewareNames,
            $middlewareNames($registration)
        );

        $permissionPolicies = array_merge(
            $permissionPolicies,
            $getPermissionPolicies($registration)
        );

        $handlerSignatures[] = json_encode($registration['handler']);
        $middlewareUnknown = $middlewareUnknown || !$registration['middleware_known'];
    }

    $allMiddlewareNames = array_values(array_unique($allMiddlewareNames));
    $permissionPolicies = array_values(array_unique($permissionPolicies));

    $missingFromEffective = array_values(array_diff(
        $allMiddlewareNames,
        $effectiveNames
    ));

    $highRiskMissing = array_values(array_intersect(
        $missingFromEffective,
        ['auth', 'csrf']
    ));

    $permissionConflict = count($permissionPolicies) > 1;
    $handlerConflict = count(array_unique($handlerSignatures)) > 1;

    if ($middlewareUnknown) {
        $severity = 'MEDIUM';
        $confidence = 'COVERAGE_GAP';
        $message = 'Duplicate registrations found, but at least one middleware list is dynamic or incompletely resolved.';
    } elseif ($highRiskMissing !== []) {
        $severity = 'HIGH';
        $confidence = 'HIGH';
        $message = 'The effective first registration omits authentication or CSRF middleware declared by a later shadowed registration.';
    } elseif ($permissionConflict) {
        $severity = 'MEDIUM';
        $confidence = 'HIGH';
        $message = 'Duplicate registrations declare different permission policies. The first registration determines the effective policy.';
    } elseif ($missingFromEffective !== []) {
        $severity = 'MEDIUM';
        $confidence = 'REVIEW';
        $message = 'The effective first registration omits middleware declared by a later shadowed registration.';
    } else {
        $severity = 'MEDIUM';
        $confidence = 'REVIEW';
        $message = 'Duplicate method/path registration detected. The first registration is effective.';
    }

    $addFinding(
        'ROUTE-006',
        $severity,
        $confidence,
        $message,
        $effective,
        [
            'route_key' => $key,
            'dispatch_strategy' => 'first_matching_registration_wins',
            'effective_registration' => $registrationEvidence([$effective])[0],
            'shadowed_registrations' => $registrationEvidence(
                array_slice($registrations, 1)
            ),
            'all_registrations' => $registrationEvidence($registrations),
            'middleware_missing_from_effective' => $missingFromEffective,
            'permission_policies' => $permissionPolicies,
            'permission_policy_conflict' => $permissionConflict,
            'handler_conflict' => $handlerConflict,
            'middleware_resolution_incomplete' => $middlewareUnknown,
        ]
    );
}

// Only effective routes are assessed for runtime middleware risks.
$effectiveRoutes = [];

foreach ($groups as $registrations) {
    $effectiveRoutes[] = $registrations[0];
}

// Dynamic paths cannot be grouped by a literal method/path key.
foreach ($routes as $route) {
    if ($route['path'] === null) {
        $effectiveRoutes[] = $route;
    }
}

foreach ($effectiveRoutes as $route) {
    $method = $route['method'];
    $path = $route['path'];
    $middlewareKnown = $route['middleware_known'];
    $names = $middlewareNames($route);

    $hasAuth = in_array('auth', $names, true);
    $hasGuest = in_array('guest', $names, true);
    $hasCsrf = in_array('csrf', $names, true);
    $hasPermission = in_array('permission', $names, true);

    if (!$middlewareKnown) {
        $addFinding(
            'ROUTE-005',
            'INFO',
            'COVERAGE_GAP',
            'Middleware is dynamic or could not be fully extracted.',
            $route
        );
    }

    if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)
        && $middlewareKnown
        && !$hasCsrf) {
        $addFinding(
            'ROUTE-002',
            'HIGH',
            'HIGH',
            'Effective state-changing route does not declare CSRF middleware.',
            $route,
            ['required_middleware' => 'csrf']
        );
    }

    $pathLower = strtolower((string) $path);

    $isSensitive = preg_match(
        '~^/(recruit/admin|recruit/account|recruit/users|users|hr|admin|settings)(/|$)~',
        $pathLower
    ) === 1;

    $isGuestEndpoint = $hasGuest
        || preg_match(
            '~^/(login|password/(forgot|reset)|health)(/|$)~',
            $pathLower
        ) === 1;

    if ($isSensitive && !$isGuestEndpoint && $middlewareKnown && !$hasAuth) {
        $addFinding(
            'ROUTE-003',
            'HIGH',
            'HIGH',
            'Effective sensitive route does not declare authentication middleware.',
            $route,
            ['required_middleware' => 'auth']
        );
    }

    $isAdminRoute = preg_match(
        '~^/recruit/admin(/|$)~',
        $pathLower
    ) === 1;

    if ($isAdminRoute && !$isGuestEndpoint && $middlewareKnown && !$hasPermission) {
        $addFinding(
            'ROUTE-004',
            'MEDIUM',
            'REVIEW',
            'Effective administrative route has no explicit permission middleware. Confirm whether controller-level authorization is intentional.',
            $route,
            ['expected_pattern' => 'permission:<permission-slug>']
        );
    }

    if ($method === 'GET') {
        $handlerMethod = strtolower(
            (string) ($route['handler']['method'] ?? '')
        );

        $stateChangingNames = [
            'logout', 'delete', 'destroy', 'remove',
            'revoke', 'archive', 'restore',
        ];

        if (in_array($handlerMethod, $stateChangingNames, true)
            || preg_match(
                '~/(logout|delete|remove|revoke|archive|restore)(/|$)~',
                $pathLower
            )) {
            $addFinding(
                'ROUTE-001',
                'HIGH',
                'HIGH',
                'Effective GET route appears to invoke a state-changing action.',
                $route,
                ['handler_method' => $route['handler']['method']]
            );
        }
    }
}

$report = [
    'generated_at' => date(DATE_ATOM),
    'project_root' => $root,
    'route_loading_order' => $routeLoadOrder,
    'dispatch_strategy' => 'first_matching_registration_wins',
    'route_files_scanned' => count(array_unique(array_column($routes, 'file'))),
    'routes_discovered' => count($routes),
    'effective_routes_evaluated' => count($effectiveRoutes),
    'findings_count' => count($findings),
    'findings' => $findings,
];

$outputDirectory = __DIR__ . '/reports';

if (!is_dir($outputDirectory)) {
    mkdir($outputDirectory, 0775, true);
}

$outputFile = $outputDirectory . '/route-audit.json';

file_put_contents(
    $outputFile,
    json_encode(
        $report,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    )
);

echo "ROUTE SECURITY AUDIT\n";
echo "====================\n";
echo "Route files scanned:        {$report['route_files_scanned']}\n";
echo "Routes discovered:          {$report['routes_discovered']}\n";
echo "Effective routes evaluated: {$report['effective_routes_evaluated']}\n";
echo "Findings:                   {$report['findings_count']}\n\n";

foreach ($findings as $finding) {
    printf(
        "[%s] %s | %s | %s %s\n  %s:%d\n  %s\n",
        $finding['severity'],
        $finding['rule'],
        $finding['confidence'],
        $finding['method'],
        $finding['path'] ?? '[dynamic path]',
        $finding['file'],
        $finding['line'],
        $finding['message']
    );
}

echo "\nJSON report: {$outputFile}\n";

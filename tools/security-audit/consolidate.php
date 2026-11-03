<?php

declare(strict_types=1);

/**
 * CAS Suit Security Audit Consolidator
 *
 * Reads existing analyzer reports and creates a unified report.
 * Does not modify application source code or analyzer reports.
 */

$base = __DIR__ . '/reports';

$inputs = [
    'tenant' => $base . '/security-audit.json',
    'routes' => $base . '/route-audit.json',
    'controllers' => $base . '/controller-auth-audit.json',
    'polymorphic' => $base . '/polymorphic-ownership-audit.json',
    'verified' => $base . '/verified-findings.json',
];

$reports = [];

foreach ($inputs as $name => $path) {
    if (!is_file($path)) {
        fwrite(STDERR, "Missing report: {$path}\n");
        exit(1);
    }

    try {
        $data = json_decode(
            file_get_contents($path),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
    } catch (Throwable $exception) {
        fwrite(STDERR, "Invalid report {$path}: {$exception->getMessage()}\n");
        exit(1);
    }

    if (!is_array($data)) {
        fwrite(STDERR, "Invalid report structure: {$path}\n");
        exit(1);
    }

    $reports[$name] = $data;
}

$findings = [];
$observations = [];

$addFinding = static function (
    string $analyzer,
    string $rule,
    string $category,
    string $severity,
    string $confidence,
    string $status,
    string $message,
    array $location,
    array $evidence = []
) use (&$findings): void {
    $findings[] = [
        'id' => sprintf('AUD-%04d', count($findings) + 1),
        'rule' => $rule,
        'category' => $category,
        'severity' => $severity,
        'confidence' => $confidence,
        'status' => $status,
        'source_analyzer' => $analyzer,
        'location' => $location,
        'message' => $message,
        'evidence' => $evidence,
    ];
};

/*
 * Tenant SQL and hardcoded tenant ID findings.
 */
foreach ($reports['tenant']['findings'] ?? [] as $item) {
    $rule = (string) ($item['rule'] ?? 'UNKNOWN');
    $category = match ($rule) {
        'TENANT-001', 'TENANT-002' => 'TENANT_ISOLATION',
        default => 'OTHER',
    };

    $addFinding(
        'tenant-audit',
        $rule,
        $category,
        (string) ($item['severity'] ?? 'MEDIUM'),
        (string) ($item['confidence'] ?? 'REVIEW'),
        'REVIEW',
        (string) ($item['message'] ?? $item['evidence'] ?? 'Potential tenant isolation issue requires review.'),
        [
            'file' => $item['file'] ?? null,
            'line' => $item['line'] ?? null,
        ],
        $item
    );
}

/*
 * Evidence-backed findings established through source investigation.
 */
foreach ($reports['verified']['findings'] ?? [] as $item) {
    $findings[] = [
        'id' => (string) ($item['id'] ?? 'SEC-UNKNOWN'),
        'rule' => (string) ($item['id'] ?? 'SEC-UNKNOWN'),
        'category' => (string) ($item['category'] ?? 'OTHER'),
        'severity' => (string) ($item['severity'] ?? 'MEDIUM'),
        'confidence' => (string) ($item['confidence'] ?? 'REVIEW'),
        'status' => (string) ($item['status'] ?? 'REVIEW'),
        'source_analyzer' => 'verified-register',
        'location' => [
            'file' => $item['file'] ?? null,
            'line' => $item['line'] ?? null,
        ],
        'message' => (string) ($item['title'] ?? 'Verified finding'),
        'impact' => $item['impact'] ?? null,
        'remediation' => $item['remediation'] ?? null,
        'verification_basis' => $item['verification_basis'] ?? null,
        'evidence' => $item['evidence'] ?? [],
    ];
}

/*
 * Route findings.
 */
foreach ($reports['routes']['findings'] ?? [] as $item) {
    $confidence = (string) ($item['confidence'] ?? 'REVIEW');

    $status = match ($confidence) {
        'HIGH' => 'VERIFIED_STATIC_DEFECT',
        'COVERAGE_GAP' => 'COVERAGE_GAP',
        default => 'REVIEW',
    };

    $addFinding(
        'route-audit',
        (string) ($item['rule'] ?? 'ROUTE-UNKNOWN'),
        'ROUTE_SECURITY',
        (string) ($item['severity'] ?? 'MEDIUM'),
        $confidence,
        $status,
        (string) ($item['message'] ?? 'Route finding requires review.'),
        [
            'file' => $item['file'] ?? null,
            'line' => $item['line'] ?? null,
            'method' => $item['method'] ?? null,
            'path' => $item['path'] ?? null,
        ],
        $item['evidence'] ?? $item
    );
}

/*
 * Polymorphic ownership results.
 *
 * These identify ownership-sensitive operations for review.
 * They are not automatically classified as confirmed vulnerabilities.
 */
foreach ($reports['polymorphic']['results'] ?? [] as $item) {
    $addFinding(
        'polymorphic-ownership-audit',
        (string) ($item['rule'] ?? 'POLY-OWNERSHIP-UNKNOWN'),
        'FILE_AND_POLYMORPHIC_OWNERSHIP',
        (string) ($item['severity'] ?? 'MEDIUM'),
        (string) ($item['confidence'] ?? 'REVIEW'),
        'REVIEW',
        (string) ($item['message'] ?? 'Polymorphic ownership validation requires review.'),
        [
            'file' => $item['file'] ?? null,
            'line' => $item['line'] ?? null,
        ],
        $item
    );
}

/*
 * Controller authorization results.
 *
 * These are correlation observations, not automatically vulnerabilities.
 */
foreach ($reports['controllers']['results'] ?? [] as $item) {
    $status = (string) ($item['status'] ?? 'UNKNOWN');
    $confidence = (string) ($item['confidence'] ?? 'REVIEW');
    $route = $item['route'] ?? [];
    $controller = $item['controller_method'] ?? [];

    $observation = [
        'source_analyzer' => 'controller-auth-audit',
        'status' => $status,
        'confidence' => $confidence,
        'route' => [
            'method' => $route['method'] ?? null,
            'path' => $route['path'] ?? null,
            'file' => $route['file'] ?? null,
            'line' => $route['line'] ?? null,
        ],
        'controller' => [
            'class' => $controller['class'] ?? ($route['handler']['class'] ?? null),
            'method' => $controller['method'] ?? ($route['handler']['method'] ?? null),
            'file' => $controller['file'] ?? null,
            'line' => $controller['line'] ?? null,
        ],
        'auth_middleware' => $item['auth_middleware'] ?? null,
        'permission_middleware' => $item['permission_middleware'] ?? null,
        'middleware_known' => $item['middleware_known'] ?? null,
        'message' => $item['message'] ?? '',
    ];

    $observations[] = $observation;

    if ($status === 'ROUTE_PERMISSION_MIDDLEWARE') {
        continue;
    }

    $findingStatus = match ($status) {
        'MIDDLEWARE_UNKNOWN', 'UNRESOLVED_HANDLER' => 'COVERAGE_GAP',
        default => 'REVIEW',
    };

    $addFinding(
        'controller-auth-audit',
        'AUTHZ-' . $status,
        'AUTHORIZATION_COVERAGE',
        'MEDIUM',
        $confidence,
        $findingStatus,
        (string) ($item['message'] ?? 'Authorization coverage requires review.'),
        [
            'file' => $route['file'] ?? null,
            'line' => $route['line'] ?? null,
            'method' => $route['method'] ?? null,
            'path' => $route['path'] ?? null,
            'controller_file' => $controller['file'] ?? null,
            'controller_line' => $controller['line'] ?? null,
        ],
        $observation
    );
}

$severityCounts = [];
$statusCounts = [];
$ruleCounts = [];

foreach ($findings as $finding) {
    $severity = $finding['severity'];
    $status = $finding['status'];
    $rule = $finding['rule'];

    $severityCounts[$severity] = ($severityCounts[$severity] ?? 0) + 1;
    $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;
    $ruleCounts[$rule] = ($ruleCounts[$rule] ?? 0) + 1;
}

ksort($severityCounts);
ksort($statusCounts);
ksort($ruleCounts);

$report = [
    'generated_at' => date(DATE_ATOM),
    'project_root' => $reports['tenant']['project_root'] ?? null,
    'mode' => 'READ ONLY',
    'consolidation_version' => 1,
    'source_reports' => array_map(
        static fn (array $report): array => [
            'generated_at' => $report['generated_at'] ?? null,
        ],
        $reports
    ),
    'summary' => [
        'findings_count' => count($findings),
        'observations_count' => count($observations),
        'by_severity' => $severityCounts,
        'by_status' => $statusCounts,
        'by_rule' => $ruleCounts,
    ],
    'findings' => $findings,
    'authorization_observations' => $observations,
];

$output = $base . '/consolidated-security-audit.json';

file_put_contents(
    $output,
    json_encode(
        $report,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    )
);

echo "CONSOLIDATED SECURITY AUDIT\n";
echo "===========================\n";
echo "Findings:      " . count($findings) . "\n";
echo "Observations:  " . count($observations) . "\n";

foreach ($statusCounts as $status => $count) {
    printf("%-20s %d\n", $status, $count);
}

echo "\nJSON report: {$output}\n";

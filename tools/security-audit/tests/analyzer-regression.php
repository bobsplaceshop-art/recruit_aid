<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/vendor/autoload.php';
require dirname(__DIR__) . '/analyzers/RouteAnalyzer.php';
require dirname(__DIR__) . '/analyzers/ControllerAuthorizationAnalyzer.php';

use CAS\SecurityAudit\Analyzers\ControllerAuthorizationAnalyzer;
use CAS\SecurityAudit\Analyzers\RouteAnalyzer;

$passed = 0;
$failed = 0;

$assert = static function (bool $condition, string $message) use (&$passed, &$failed): void {
    if ($condition) {
        echo "[PASS] {$message}\n";
        $passed++;
    } else {
        echo "[FAIL] {$message}\n";
        $failed++;
    }
};

$fixtures = sys_get_temp_dir() . '/recruitaid-security-audit-' . getmypid();
mkdir($fixtures, 0775, true);

try {
    $routeFile = $fixtures . '/routes.php';

    file_put_contents($routeFile, <<<'PHP'
<?php

$router->post('/users/update', [UserController::class, 'update'], [
    'auth',
    'csrf',
    'permission:core.users.edit',
]);

$router->get('/users', [UserController::class, 'index'], ['auth']);

$router->post('/dynamic', [UserController::class, 'save'], $middleware);
PHP);

    $routeAnalyzer = new RouteAnalyzer();
    $routes = $routeAnalyzer->analyzeFile($routeFile);

    $assert(count($routes) === 3, 'Extracts all literal route declarations');

    $assert(
        ($routes[0]['method'] ?? null) === 'POST'
        && ($routes[0]['path'] ?? null) === '/users/update',
        'Extracts HTTP method and literal route path'
    );

    $assert(
        ($routes[0]['handler']['class'] ?? null) === 'UserController'
        && ($routes[0]['handler']['method'] ?? null) === 'update',
        'Extracts class and method route handler'
    );

    $assert(
        ($routes[0]['middleware'] ?? []) === [
            'auth',
            'csrf',
            'permission:core.users.edit',
        ],
        'Extracts literal middleware declarations'
    );

    $assert(
        ($routes[2]['middleware_known'] ?? true) === false,
        'Marks dynamic middleware as unresolved'
    );

    $assert(
        ($routes[2]['middleware'] ?? []) === [],
        'Does not invent middleware values for dynamic expressions'
    );

    $controllerFile = $fixtures . '/UserController.php';

    file_put_contents($controllerFile, <<<'PHP'
<?php

namespace App\Controllers;

class UserController
{
    public function index(): void
    {
        $this->authorization->can('core.users.view');
    }

    public function update(): void
    {
        $this->authorization->can('core.users.edit');
    }

    public function publicPage(): void
    {
        echo 'Public';
    }
}
PHP);

    $controllerAnalyzer = new ControllerAuthorizationAnalyzer();
    $methods = $controllerAnalyzer->analyzeFile($controllerFile);

    $assert(count($methods) === 3, 'Discovers controller methods');

    $assert(
        ($methods[0]['class'] ?? null) === 'App\Controllers\UserController',
        'Resolves controller namespace and class'
    );

    $assert(
        ($methods[0]['has_explicit_authorization'] ?? false) === true,
        'Detects authorization calls in controller methods'
    );

    $assert(
        ($methods[2]['has_explicit_authorization'] ?? true) === false,
        'Does not mark methods without authorization calls as protected'
    );

    $malformedFile = $fixtures . '/malformed.php';
    file_put_contents($malformedFile, '<?php class Broken { public function (');

    $assert(
        $routeAnalyzer->analyzeFile($malformedFile) === [],
        'Malformed route PHP fails safely'
    );

    $assert(
        $controllerAnalyzer->analyzeFile($malformedFile) === [],
        'Malformed controller PHP fails safely'
    );
} finally {
    foreach (glob($fixtures . '/*') ?: [] as $file) {
        unlink($file);
    }

    rmdir($fixtures);
}

echo "\nResults: {$passed} passed, {$failed} failed\n";

exit($failed === 0 ? 0 : 1);

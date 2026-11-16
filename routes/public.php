<?php
declare(strict_types=1);

use CorporateAid\Core\Router;
use CorporateAid\Recruit\Controllers\PublicJobController;

/** @var Router $router */

// Public job board — no authentication required.
//
// The controller enforces the public visibility filter itself (is_public=1
// AND status not in closed/cancelled/filled/draft/on_hold). A vacancy that
// exists but is not public returns 404, not 403, so the response does not
// confirm the vacancy's existence.

$router->get('/jobs',        [PublicJobController::class, 'index'], []);
$router->get('/jobs/{code}', [PublicJobController::class, 'show'],  []);

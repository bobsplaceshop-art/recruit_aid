<?php
declare(strict_types=1);

use CorporateAid\Core\Response;
use CorporateAid\Core\Router;

/** @var Router $router */

// Placeholder stub — proves the router loads public.php and that
// public routes reach the router without the auth middleware.
//
// The real public job board replaces this stub in the next patch
// with PublicJobController::index and ::show.

$router->get('/jobs', static function () {
    return Response::text('Public job board — coming soon');
}, []);

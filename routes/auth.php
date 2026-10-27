<?php
declare(strict_types=1);

use CorporateAid\Core\Router;
use CorporateAid\Recruit\Controllers\AuthController;
use CorporateAid\Recruit\Controllers\PasswordResetController;

/** @var Router $router */

$router->get('/login',  [AuthController::class, 'showLogin'], ['guest']);
$router->post('/login', [AuthController::class, 'login'],     ['guest', 'csrf', 'ratelimit:login']);
$router->post('/logout',[AuthController::class, 'logout'],    ['auth', 'csrf']);

// ---------------------------------------------------------------------
// Password reset (guest only)
// ---------------------------------------------------------------------
$router->get('/password/forgot',        [PasswordResetController::class, 'showForgot'],    ['guest']);
$router->post('/password/forgot',       [PasswordResetController::class, 'sendResetLink'], ['guest', 'csrf', 'ratelimit:password_forgot']);
$router->get('/password/reset/{token}', [PasswordResetController::class, 'showReset'],     ['guest']);
$router->post('/password/reset',        [PasswordResetController::class, 'processReset'],  ['guest', 'csrf', 'ratelimit:password_reset']);

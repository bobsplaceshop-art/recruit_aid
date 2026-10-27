<?php
declare(strict_types=1);

namespace CorporateAid\Platform\Middleware;

use CorporateAid\Core\Auth;
use CorporateAid\Core\Request;
use CorporateAid\Core\Response;

/**
 * Guest-only routes (login, forgot password). Redirects logged-in
 * users to the dashboard.
 *
 * Middleware model migration 2026-09-24 — see AuthMiddleware for notes.
 */
final class GuestMiddleware
{
    private Auth $auth;

    public function __construct(Auth $auth)
    {
        $this->auth = $auth;
    }

    public function handle(Request $request, callable $next): Response
    {
        if ($this->auth->check()) {
            return Response::redirect('/dashboard');
        }
        return $next($request);
    }
}

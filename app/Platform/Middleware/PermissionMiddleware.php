<?php
declare(strict_types=1);

namespace CorporateAid\Platform\Middleware;

use CorporateAid\Core\Auth;
use CorporateAid\Core\Request;
use CorporateAid\Core\Response;

/**
 * Blocks users who do not have the given permission.
 * Routed via: ['permission:vacancies.create']
 *
 * Middleware model migration 2026-09-24 — see AuthMiddleware for notes.
 */
final class PermissionMiddleware
{
    private Auth $auth;
    private string $permission;

    public function __construct(Auth $auth, string $permission = '')
    {
        $this->auth = $auth;
        $this->permission = $permission;
    }

    public function handle(Request $request, callable $next): Response
    {
        $user = $this->auth->user();

        if ($user === null) {
            return Response::redirect('/login');
        }

        if ($this->permission === '' || $user->can($this->permission)) {
            return $next($request);
        }

        // Logged in but lacks permission → 403
        http_response_code(403);
        return new Response(
            '<h1>Forbidden</h1><p>You do not have permission to view this page.</p>',
            403,
            ['Content-Type' => 'text/html; charset=utf-8']
        );
    }
}

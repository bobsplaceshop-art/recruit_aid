<?php
declare(strict_types=1);

namespace CorporateAid\Platform\Middleware;

use CorporateAid\Core\Auth;
use CorporateAid\Core\Exceptions\HttpException;
use CorporateAid\Core\Request;
use CorporateAid\Core\Response;

/**
 * Blocks users who do not have the given permission.
 * Routed via: ['permission:vacancies.create']
 *
 * Middleware model migration 2026-09-24 — see AuthMiddleware for notes.
 *
 * When access is denied, throws HttpException::forbidden(). The App's
 * existing exception handler (App::renderException) picks up the 403
 * status and renders resources/views/errors/403.php with the branded
 * "Access denied" page — no view code lives in this middleware.
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

        // Logged in but lacks permission → App::renderException() renders
        // resources/views/errors/403.php with the message below.
        throw HttpException::forbidden(
            'You do not have permission to view this page.'
        );
    }
}

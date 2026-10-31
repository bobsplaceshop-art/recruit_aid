<?php
declare(strict_types=1);

namespace CorporateAid\Platform\Middleware;

use CorporateAid\Core\Auth;
use CorporateAid\Core\Request;
use CorporateAid\Core\Response;

/**
 * Requires a logged-in user. Redirects to /login otherwise.
 *
 * ------------------------------------------------------------------
 * Middleware model migration 2026-09-24 (see docs/CHANGELOG-middleware.md):
 *   Old signature: handle(Request): ?Response     (gate-style)
 *   New signature: handle(Request, callable): Response (onion-style)
 *
 * Behavior unchanged. On "pass", calls $next($request) and returns
 * its Response instead of returning null.
 * ------------------------------------------------------------------
 */
final class AuthMiddleware
{
    private Auth $auth;

    public function __construct(Auth $auth)
    {
        $this->auth = $auth;
    }

    public function handle(Request $request, callable $next): Response
    {
        if ($this->auth->check()) {
            $response = $next($request);
            
            // Prevent browser Back-Forward cache (bfcache) from showing
            // a logged-in page after logout.
            return $response->withHeaders([
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'Pragma'        => 'no-cache',
                'Expires'       => '0',
            ]);
        }

        $intended = $request->path();
        $url = '/login';
        if ($intended !== '/' && $intended !== '/login') {
            $url .= '?intended=' . rawurlencode($intended);
        }

        return Response::redirect($url);
    }
}

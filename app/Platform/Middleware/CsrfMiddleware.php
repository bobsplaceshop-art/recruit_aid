<?php
declare(strict_types=1);

namespace CorporateAid\Platform\Middleware;

use CorporateAid\Core\Csrf;
use CorporateAid\Core\Request;
use CorporateAid\Core\Response;

/**
 * Verifies CSRF token on every state-changing request (POST/PUT/PATCH/DELETE).
 *
 * Middleware model migration 2026-09-24 — see AuthMiddleware for notes.
 *
 * Status code history:
 *   Before 2026-09-24: 419 ("Authentication Timeout", a Laravel convention)
 *   After:             403 (standard "Forbidden", understood by every HTTP client)
 *
 * Reason for change: PHP's http_response_code() does not recognize 419 —
 * it silently fell back to 500 when we tried to send it, producing a
 * mismatch between the response the middleware returned (419 body, 419
 * audit log row) and the status the browser saw (500). 403 is a
 * first-class code in every PHP build and every HTTP client. Semantically
 * it is also correct: the request was understood and refused.
 */
final class CsrfMiddleware
{
    private Csrf $csrf;

    public function __construct(Csrf $csrf)
    {
        $this->csrf = $csrf;
    }

    public function handle(Request $request, callable $next): Response
    {
        $method = $request->method();
        if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $next($request);
        }

        $token = $request->input($this->csrf->fieldName())
              ?? $request->header($this->csrf->headerName());

        if (!$this->csrf->verify(is_string($token) ? $token : null)) {
            // Reject the request. The message avoids leaking whether the
            // session, the token, or the request was the problem.
            return new Response(
                '<h1>Session expired</h1><p>Please go back, refresh the page, and try again.</p>',
                403,
                ['Content-Type' => 'text/html; charset=utf-8']
            );
        }

        return $next($request);
    }
}

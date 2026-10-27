<?php
declare(strict_types=1);

namespace CorporateAid\Platform\Middleware;

use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Platform\Support\RateLimiter;

/**
 * Rate limiting middleware.
 * Configured per-route as:  'ratelimit:action_name,max_attempts,window_minutes'
 *
 * Example: 'ratelimit:login,5,15'
 *   → 5 attempts per IP per 15 minutes for the 'login' action
 *
 * On exceeded: returns a clean 429 page with a "wait N minutes" message.
 * Does NOT count the current request as an attempt (the controller does that
 * when it actually fails — successful requests shouldn't count).
 *
 * Middleware model migration 2026-09-24 — see AuthMiddleware for notes.
 */
final class RateLimitMiddleware
{
    public function __construct(
        private RateLimiter $limiter,
        private string $action = '',
        private int $max = 5,
        private int $windowMinutes = 15
    ) {
    }

    public function handle(Request $request, callable $next): Response
    {
        if ($this->action === '') {
            // misconfigured; fail open (don't block the user)
            return $next($request);
        }

        $ip = $request->ip();
        $state = $this->limiter->check($this->action, $ip, $this->max, $this->windowMinutes);

        if ($state['allowed']) {
            return $next($request);
        }

        // Build a friendly page and return it
        $wait = $this->limiter->humanWait($state['seconds_until_reset']);

        http_response_code(429);
        $html = '<!doctype html><html><head><meta charset="utf-8"><title>Too many attempts</title>'
              . '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">'
              . '<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">'
              . '<link href="/assets/css/app.css" rel="stylesheet"></head>'
              . '<body style="min-height:100vh;display:flex;align-items:center;justify-content:center;padding:2rem;">'
              . '<div class="ra-card" style="max-width:520px;width:100%;text-align:center;padding:2.5rem;">'
              . '<div style="font-size:3rem;color:var(--ra-warn-fg);"><i class="bi bi-hourglass-split"></i></div>'
              . '<h1 class="h4 mt-3 mb-2" style="color:var(--ra-text-strong);">Too many attempts</h1>'
              . '<p class="muted mb-4">For your security, we\'ve paused this action. Please try again in <strong>'
              . e($wait) . '</strong>.</p>'
              . '<a href="/login" class="ra-btn ra-btn-primary"><i class="bi bi-arrow-left"></i> Back to sign in</a>'
              . '</div></body></html>';

        return new Response($html, 429, ['Content-Type' => 'text/html; charset=utf-8']);
    }
}

<?php
declare(strict_types=1);

namespace CorporateAid\Platform\Middleware;

use CorporateAid\Core\Csrf;
use CorporateAid\Core\Request;
use CorporateAid\Core\Response;

/**
 * Verifies CSRF token on every state-changing request (POST/PUT/PATCH/DELETE).
 *
 * On failure, returns 403 with the errors/403.php view rendered with
 * a "Session expired" message and a "Sign in again" link.
 */
final class CsrfMiddleware
{
    public function __construct(private Csrf $csrf) {}

    public function handle(Request $request, callable $next): Response
    {
        $method = $request->method();
        if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $next($request);
        }

        $token = $request->input($this->csrf->fieldName())
              ?? $request->header($this->csrf->headerName());

        if (!$this->csrf->verify(is_string($token) ? $token : null)) {
            $html = $this->renderForbidden(
                'Your session has expired or the request could not be verified. Please sign in again to continue.'
            );
            return new Response($html, 403, ['Content-Type' => 'text/html; charset=utf-8']);
        }

        return $next($request);
    }

    private function renderForbidden(string $message): string
    {
        // Resolve the view file directly. We can't use View here because
        // View needs a Config and this middleware fires before the app
        // has fully wired the container's View service in some edge cases.
        $errorMessage = $message;
        $loginLink = true;

        ob_start();
        $viewPath = dirname(__DIR__, 3) . '/resources/views/errors/403.php';
        if (is_file($viewPath)) {
            require $viewPath;
        } else {
            echo '<h1>Session expired</h1><p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>';
            echo '<p><a href="/login">Sign in again</a></p>';
        }
        return (string) ob_get_clean();
    }
}

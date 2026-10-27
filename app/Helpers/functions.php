<?php
declare(strict_types=1);

/**
 * Recruit Aid - global helper functions.
 * Loaded automatically by Composer's autoloader.
 */

if (!function_exists('e')) {
    function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('config')) {
    function config(string $key, mixed $default = null): mixed
    {
        return \CorporateAid\Core\App::instance()->container()->get('config')->get($key, $default);
    }
}

if (!function_exists('base_path')) {
    function base_path(string $suffix = ''): string
    {
        return \CorporateAid\Core\App::instance()->basePath($suffix);
    }
}

if (!function_exists('asset')) {
    function asset(string $path): string
    {
        return '/assets/' . ltrim($path, '/');
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        try {
            return \CorporateAid\Core\App::instance()
                ->container()
                ->get(\CorporateAid\Core\Csrf::class)
                ->token();
        } catch (\Throwable) {
            return '';
        }
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
    }
}

if (!function_exists('method_field')) {
    function method_field(string $method): string
    {
        return '<input type="hidden" name="_method" value="' . e(strtoupper($method)) . '">';
    }
}

if (!function_exists('flash')) {
    function flash(string $key, mixed $default = null): mixed
    {
        try {
            return \CorporateAid\Core\App::instance()
                ->container()
                ->get(\CorporateAid\Core\Session::class)
                ->getFlash($key, $default);
        } catch (\Throwable) {
            return $default;
        }
    }
}

if (!function_exists('auth_user')) {
    function auth_user(): ?\CorporateAid\Platform\Models\User
    {
        try {
            return \CorporateAid\Core\App::instance()
                ->container()
                ->get(\CorporateAid\Core\Auth::class)
                ->user();
        } catch (\Throwable) {
            return null;
        }
    }
}

if (!function_exists('view_partial')) {
    /**
     * Render a partial template. Returns HTML string.
     * Partials are expected to be self-contained (no layout).
     */
    function view_partial(string $name, array $data = []): string
    {
        try {
            /** @var \CorporateAid\Core\View $view */
            $view = \CorporateAid\Core\App::instance()
                ->container()
                ->get(\CorporateAid\Core\View::class);
            return $view->render($name, $data);
        } catch (\Throwable $e) {
            \CorporateAid\Platform\Support\Logger::error('view_partial(' . $name . ') failed: ' . $e->getMessage());
            return '';
        }
    }
}

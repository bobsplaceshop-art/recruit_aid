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
        return \CorporateAid\Core\App::instance()->container()->get(\CorporateAid\Core\Config::class)->get($key, $default);
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
if (!function_exists('app_setting')) {
    /**
     * Read a system setting from the cached service. Returns the default
     * if the setting doesn't exist or the container isn't ready.
     */
    function app_setting(string $key, mixed $default = null): mixed
    {
        static $service = null;
        if ($service === null) {
            try {
                $service = \CorporateAid\Core\App::instance()
                    ->container()
                    ->get(\CorporateAid\Platform\Services\SystemSettingService::class);
            } catch (\Throwable) {
                return $default;
            }
        }
        try {
            return $service->get($key, $default);
        } catch (\Throwable) {
            return $default;
        }
    }
}

if (!function_exists('human_time')) {
    /**
     * Format a timestamp as a human-readable relative time.
     * "just now", "3 minutes ago", "2 hours ago", "5 days ago",
     * then absolute date for anything older than 30 days.
     */
    function human_time(?string $datetime): string
    {
        if ($datetime === null || $datetime === '') {
            return '';
        }

        $ts = strtotime($datetime);
        if ($ts === false) {
            return $datetime;
        }

        $diff = time() - $ts;

        if ($diff < 0) {
            return 'just now';
        }
        if ($diff < 60) {
            return 'just now';
        }
        if ($diff < 3600) {
            $m = (int) floor($diff / 60);
            return $m . ' minute' . ($m === 1 ? '' : 's') . ' ago';
        }
        if ($diff < 86400) {
            $h = (int) floor($diff / 3600);
            return $h . ' hour' . ($h === 1 ? '' : 's') . ' ago';
        }
        if ($diff < 2592000) {
            $d = (int) floor($diff / 86400);
            return $d . ' day' . ($d === 1 ? '' : 's') . ' ago';
        }

        return date('M j, Y', $ts);
    }
}

}

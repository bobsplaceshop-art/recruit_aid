<?php
declare(strict_types=1);

namespace CorporateAid\Core;

use RuntimeException;

/**
 * Renders PHP templates from resources/views/.
 * Views are plain PHP files. Layouts wrap views; partials are fragments.
 */
final class View
{
    private string $viewPath;
    private Config $config;
    private array $shared = [];

    public function __construct(string $viewPath, Config $config)
    {
        $this->viewPath = rtrim($viewPath, '/\\');
        $this->config = $config;
    }

    public function share(string $key, mixed $value): void
    {
        $this->shared[$key] = $value;
    }

    public function shareMany(array $values): void
    {
        foreach ($values as $k => $v) {
            $this->shared[$k] = $v;
        }
    }

    /**
     * Render a view. If $layout is given, the view's output is passed
     * into the layout as $content.
     */
    public function render(string $view, array $data = [], ?string $layout = null): string
    {
        $content = $this->renderFile($view, $data);

        if ($layout !== null) {
            return $this->renderFile($layout, array_merge($data, ['content' => $content]));
        }

        return $content;
    }

    private function renderFile(string $name, array $data): string
    {
        $file = $this->resolvePath($name);

        $vars = array_merge($this->shared, $data);
        extract($vars, EXTR_SKIP);

        ob_start();
        try {
            require $file;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string) ob_get_clean();
    }

    /**
     * Turn 'auth/login' into 'resources/views/auth/login.php'.
     * Accepts either 'auth/login' or 'auth/login.php'.
     */
    private function resolvePath(string $name): string
    {
        // Convert dot notation to slashes: 'dashboard.index' → 'dashboard/index'
        $name = str_replace('.', '/', $name);

        // Strip a trailing '.php' properly (if present).
        if (str_ends_with($name, '.php')) {
            $name = substr($name, 0, -4);
        }

        $file = $this->viewPath . '/' . $name . '.php';

        if (!is_file($file)) {
            throw new RuntimeException("View not found: {$name} (looked in {$file})");
        }

        return $file;
    }
}

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

    /** @var array<string, mixed> */
    private array $shared = [];

    public function __construct(string $viewPath)
    {
        $this->viewPath = rtrim($viewPath, '/\\');
    }

    public function share(string $key, mixed $value): void
    {
        $this->shared[$key] = $value;
    }

    /** @param array<string, mixed> $values */
    public function shareMany(array $values): void
    {
        foreach ($values as $k => $v) {
            $this->shared[$k] = $v;
        }
    }

    /**
     * Render a view. If $layout is given, the view's output is passed
     * into the layout as $content.
     *
     * @param array<string, mixed> $data
     */
    public function render(string $view, array $data = [], ?string $layout = null): string
    {
        $content = $this->renderFile($view, $data);

        if ($layout !== null) {
            return $this->renderFile($layout, array_merge($data, ['content' => $content]));
        }

        return $content;
    }

    /** @param array<string, mixed> $data */
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
     */
    private function resolvePath(string $name): string
    {
        $name = str_replace('.', '/', $name);

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

<?php
declare(strict_types=1);

namespace CorporateAid\Core;

use RuntimeException;

/**
 * Renders PHP templates from resources/views/.
 * Views are plain PHP files. Layouts wrap views; partials are fragments.
 *
 * Note on internal variable naming:
 *
 *   renderFile() uses EXTR_SKIP to inject $data into the template's
 *   local scope. That means if a data key collides with one of
 *   renderFile()'s own local variables (name, file, vars, data, this),
 *   EXTR_SKIP would silently skip injecting it and the view would see
 *   the wrong value. We prefix every internal variable with `_view`
 *   (which is not a valid view-data key by convention) so collisions
 *   are impossible.
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

    /** @param array<string, mixed> $_viewData */
    private function renderFile(string $_viewName, array $_viewData): string
    {
        $_viewFile = $this->resolvePath($_viewName);

        $_viewVars = array_merge($this->shared, $_viewData);
        extract($_viewVars, EXTR_SKIP);

        ob_start();
        try {
            require $_viewFile;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string) ob_get_clean();
    }

    /**
     * Turn 'auth/login' into 'resources/views/auth/login.php'.
     */
    private function resolvePath(string $_viewName): string
    {
        $_viewName = str_replace('.', '/', $_viewName);

        if (str_ends_with($_viewName, '.php')) {
            $_viewName = substr($_viewName, 0, -4);
        }

        $_viewFile = $this->viewPath . '/' . $_viewName . '.php';

        if (!is_file($_viewFile)) {
            throw new RuntimeException("View not found: {$_viewName} (looked in {$_viewFile})");
        }

        return $_viewFile;
    }
}

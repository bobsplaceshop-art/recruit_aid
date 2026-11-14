<?php
declare(strict_types=1);

namespace CorporateAid\Platform\Controllers;

use CorporateAid\Core\Auth;
use CorporateAid\Core\Container;
use CorporateAid\Core\Database;
use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Core\Row;
use CorporateAid\Core\View;
use CorporateAid\Platform\Modules\ModuleRegistrar;
use CorporateAid\Platform\Support\Logger;

/**
 * The CAS portal (module launcher for platform operators).
 *
 * Renders one tile per registered module. Tile state depends on:
 *   - core_module_state.is_enabled       (module activated for this install?)
 *   - $auth->can("{key}.dashboard.access") (user has access to module data?)
 *
 * Three tile states:
 *   - active:      enabled AND user has access → clickable link to /{key}/dashboard
 *   - no_access:   enabled but user lacks permission → disabled + hint
 *   - coming_soon: not enabled (or no state row) → disabled + "Coming soon"
 *
 * The doctrine: RBAC-ARCHITECTURE.md §14.3 (resource-gating invariant).
 * cas.dashboard.access grants the dashboard, not every resource on it.
 */
final class PortalController
{
    public function __construct(private Container $container) {}

    /** @param array<string, string> $params */
    public function index(Request $request, array $params = []): Response
    {
        /** @var View $view */
        $view = $this->container->get(View::class);
        /** @var Auth $auth */
        $auth = $this->container->get(Auth::class);
        /** @var Database $db */
        $db = $this->container->get(Database::class);

        // Only global admins (platform operators) see the portal. Tenant
        // users are redirected to their default application.
        if (!$auth->canAccessGlobalDashboard()) {
            $defaultApp = $auth->getDefaultApplication();
            if ($defaultApp !== null) {
                return Response::redirect('/' . $defaultApp . '/dashboard');
            }
            return Response::redirect('/no-access');
        }

        $modules = ModuleRegistrar::all();
        unset($modules['core']);

        // Enabled state comes from core_module_state, not the registry.
        // A module registered in app/Modules/ but not enabled in the DB
        // renders as "coming soon" — deployed code, not yet activated.
        $stateRows = $db->select("SELECT `module_key`, `is_enabled` FROM `core_module_state`");
        $enabled = [];
        foreach ($stateRows as $row) {
            $enabled[Row::str($row, 'module_key')] = Row::int($row, 'is_enabled') === 1;
        }

        $user = $auth->user();
        $tenantId = $user?->tenantId ?? 1;

        $metrics   = [];
        $canAccess = [];
        foreach ($modules as $key => $module) {
            $canAccess[$key] = $auth->can("{$key}.dashboard.access");

            // Skip metrics for disabled modules: a disabled module may
            // not have its tables yet (registered before migrations ran).
            // Calling getDashboardMetrics in that state would throw and
            // 500 the whole portal.
            if (!empty($enabled[$key])) {
                try {
                    $metrics[$key] = $module->getDashboardMetrics($tenantId, $this->container);
                } catch (\Throwable $e) {
                    // Metric failures must not break the portal — the tile
                    // still renders, just without metrics.
                    $metrics[$key] = [];
                    Logger::warning(
                        "Portal metrics failed for module {$key}: " . $e->getMessage()
                    );
                }
            }
        }

        $html = $view->render('portal/index', [
            'title'     => 'CAS Suite',
            'user'      => $user,
            'modules'   => $modules,
            'metrics'   => $metrics,
            'canAccess' => $canAccess,
            'enabled'   => $enabled,
        ], 'layouts/portal');

        return Response::html($html);
    }
}

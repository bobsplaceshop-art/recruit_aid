<?php
declare(strict_types=1);

namespace CorporateAid\Platform\Controllers\Cas;

use CorporateAid\Core\Auth;
use CorporateAid\Core\Container;
use CorporateAid\Core\Database;
use CorporateAid\Core\PDOParams;
use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Core\Row;
use CorporateAid\Core\TenantContext;
use CorporateAid\Core\View;

/**
 * Platform dashboard. Aggregates only — never shows per-record PII.
 *
 * cas.dashboard.access grants this page. It does NOT grant any resource
 * reached from it. Each tile links to a route with its own permission
 * middleware, and the platform user's effective tenant must be set (via
 * /cas/context/switch) for tenant-scoped queries to return data.
 */
final class CasDashboardController
{
    public function __construct(private Container $container) {}

    /** @param array<string, string> $params */
    public function index(Request $request, array $params = []): Response
    {
        /** @var Auth $auth */
        $auth = $this->container->get(Auth::class);
        /** @var View $view */
        $view = $this->container->get(View::class);
        /** @var Database $db */
        $db = $this->container->get(Database::class);
        /** @var TenantContext $tenant */
        $tenant = $this->container->get(TenantContext::class);

        $user = $auth->user();
        if ($user === null) {
            return Response::redirect('/login');
        }

        // Aggregate-only: counts and statuses. No per-record columns.
        $tenantCounts = $db->select(
            "SELECT `status`, COUNT(*) AS n FROM `core_tenants` GROUP BY `status`"
        );
        $statusTotals = ['active' => 0, 'suspended' => 0, 'archived' => 0];
        foreach ($tenantCounts as $row) {
            $status = Row::str($row, 'status');
            if (array_key_exists($status, $statusTotals)) {
                $statusTotals[$status] = Row::int($row, 'n');
            }
        }

        $activeTenants = $db->select(
            "SELECT t.`id`, t.`name`, t.`status`
             FROM `core_tenants` t
             WHERE t.`status` = 'active' AND t.`deleted_at` IS NULL
             ORDER BY t.`name` ASC
             LIMIT 50"
        );

        $html = $view->render('cas/dashboard', [
            'title'                => 'CAS Dashboard',
            'user'                 => $user,
            'status_totals'        => $statusTotals,
            'active_tenants'       => $activeTenants,
            'effective_tenant_id'  => $tenant->id(),
            'home_tenant_id'       => $tenant->homeId(),
        ], 'layouts/app');

        return Response::html($html);
    }
}

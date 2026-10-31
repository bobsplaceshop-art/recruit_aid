<?php
declare(strict_types=1);

namespace CorporateAid\Platform\Controllers;

use CorporateAid\Core\Container;
use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Core\View;
use CorporateAid\Core\Auth;
use CorporateAid\Platform\Modules\ModuleRegistrar;

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
        
        // Security check: Only global admins can see the dashboard
        if (!$auth->canAccessGlobalDashboard()) {
            // Redirect to their default app if they try to access the portal
            $defaultApp = $auth->getDefaultApplication();
            if ($defaultApp !== null) {
                return Response::redirect('/' . $defaultApp . '/dashboard');
            }
            return Response::redirect('/no-access');
        }

        $modules = ModuleRegistrar::all();
        unset($modules['core']);

        // Fetch metrics for each module
        $metrics = [];
        foreach ($modules as $key => $module) {
            $metrics[$key] = $module->getDashboardMetrics(1, $this->container); // Hardcoded tenant 1 for now, will use $auth->tenantId later
        }

        $html = $view->render('portal/index', [
            'title'    => 'CAS Suite',
            'user'     => $auth->user(),
            'modules'  => $modules,
            'metrics'  => $metrics,
        ], 'layouts/portal'); 

        return Response::html($html);
    }
}

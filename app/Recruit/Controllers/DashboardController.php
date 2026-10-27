<?php
declare(strict_types=1);

namespace CorporateAid\Recruit\Controllers;

use CorporateAid\Core\Auth;
use CorporateAid\Core\Container;
use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Core\View;
use CorporateAid\Platform\Services\DashboardService;

final class DashboardController
{
    private Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function index(Request $request, array $params = []): Response
    {
        /** @var Auth $auth */
        $auth = $this->container->get(Auth::class);
        /** @var View $view */
        $view = $this->container->get(View::class);
        /** @var DashboardService $dashboard */
        $dashboard = $this->container->get(DashboardService::class);

        $user = $auth->user();
        $data = $dashboard->forCurrentUser();

        $html = $view->render('dashboard/index', array_merge($data, [
            'title' => 'Dashboard',
            'user'  => $user,
        ]), 'layouts/app');

        return Response::html($html);
    }
}

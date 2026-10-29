<?php
declare(strict_types=1);

namespace CorporateAid\Recruit\Controllers;

use CorporateAid\Core\Auth;
use CorporateAid\Core\Container;
use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Core\View;
use CorporateAid\Platform\Repositories\ActivityRepository;

/**
 * Global Activity Feed.
 * Route: GET /activity?page=N&q=search
 */
final class GlobalActivityController
{
    public function __construct(private Container $container) {}

    /** @param array<string, string> $params */
    public function index(Request $request, array $params = []): Response
    {
        $page = max(1, $request->queryInt('page', 1));
        $search = trim($request->queryStringValue('q', ''));
        $perPage = 30;
        $offset = ($page - 1) * $perPage;

        /** @var ActivityRepository $repo */
        $repo = $this->container->get(ActivityRepository::class);

        $activities = $repo->findGlobalPaginated($search !== '' ? $search : null, $offset, $perPage);
        $total = $repo->countGlobal($search !== '' ? $search : null);
        $totalPages = max(1, (int) ceil($total / $perPage));

        /** @var View $view */
        $view = $this->container->get(View::class);
        /** @var Auth $auth */
        $auth = $this->container->get(Auth::class);

        return Response::html($view->render('activity/global', [
            'title'      => 'Activity Feed',
            'user'       => $auth->user(),
            'activities' => $activities,
            'search'     => $search,
            'total'      => $total,
            'page'       => $page,
            'perPage'    => $perPage,
            'totalPages' => $totalPages,
        ], 'layouts/app'));
    }
}

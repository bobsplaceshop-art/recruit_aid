<?php
declare(strict_types=1);

namespace CorporateAid\Recruit\Controllers;

use CorporateAid\Core\Auth;
use CorporateAid\Core\Container;
use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Core\View;
use CorporateAid\Platform\Services\GlobalSearchService;

final class GlobalSearchController
{
    public function __construct(private Container $container) {}

    /** @param array<string, string> $params */
    public function index(Request $request, array $params = []): Response
    {
        /** @var View $view */
        $view = $this->container->get(View::class);
        /** @var Auth $auth */
        $auth = $this->container->get(Auth::class);
        /** @var GlobalSearchService $search */
        $search = $this->container->get(GlobalSearchService::class);

        $term = trim($request->queryStringValue('q', ''));
        $result = $search->search($term);

        $html = $view->render('search/results', [
            'title'  => $term === '' ? 'Search' : 'Search: ' . $term,
            'user'   => $auth->user(),
            'term'   => $result['term'],
            'total'  => $result['total'],
            'groups' => $result['groups'],
        ], 'layouts/app');

        return Response::html($html);
    }

    /**
     * GET /api/search?q=term — JSON autocomplete endpoint
     * Returns top N results per group for the topbar dropdown.
     */
    /** @param array<string, string> $params */
    public function api(Request $request, array $params = []): Response
    {
        $term = trim($request->queryStringValue('q', ''));

        // Autocomplete: require minimum 2 chars to reduce noise
        if (strlen($term) < 2) {
            return Response::json([
                'term'   => $term,
                'total'  => 0,
                'groups' => [],
            ]);
        }

        /** @var GlobalSearchService $search */
        $search = $this->container->get(GlobalSearchService::class);

        // 5 per group for autocomplete (vs 20 on the full page)
        $result = $search->search($term, 5);

        return Response::json($result);
    }
}

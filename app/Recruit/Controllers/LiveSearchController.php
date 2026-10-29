<?php
declare(strict_types=1);

namespace CorporateAid\Recruit\Controllers;

use CorporateAid\Core\Container;
use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Platform\LiveSearch\LiveSearchRegistry;
use CorporateAid\Platform\LiveSearch\LiveSearchService;

final class LiveSearchController
{
    public function __construct(private Container $container) {}

    /** @param array<string, string> $params */
    public function search(Request $request, array $params = []): Response
    {
        $slug = trim($request->queryStringValue('entity', ''));
        $term = trim($request->queryStringValue('q', ''));
        $page = max(1, $request->queryInt('page', 1));

        if ($slug === '') {
            return Response::json(['error' => 'Missing entity parameter'], 400);
        }

        $repo = LiveSearchRegistry::resolve($slug);
        if ($repo === null) {
            return Response::json(['error' => 'Unknown entity: ' . $slug], 404);
        }

        /** @var LiveSearchService $service */
        $service = $this->container->get(LiveSearchService::class);
        $result = $service->run($repo->liveSearchConfig(), $term, $page, 25);

        return Response::json([
            'entity'      => $slug,
            'term'        => $term,
            'rows'        => $result['rows'],
            'total'       => $result['total'],
            'page'        => $result['page'],
            'per_page'    => $result['per_page'],
            'total_pages' => $result['total_pages'],
        ]);
    }
}

<?php
declare(strict_types=1);

namespace CorporateAid\Recruit\Controllers;

use CorporateAid\Core\Auth;
use CorporateAid\Core\Container;
use CorporateAid\Core\Exceptions\HttpException;
use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Core\View;
use CorporateAid\Platform\Repositories\ActivityRepository;
use CorporateAid\Recruit\Repositories\ContactRepository;
use CorporateAid\Recruit\Repositories\EngagementRepository;
use CorporateAid\Recruit\Repositories\OrganisationRepository;
use CorporateAid\Recruit\Repositories\VacancyRepository;

/**
 * Dedicated "all activity" page for a specific entity.
 * Route: GET /activity/{type}/{id}?page=N&return_to=...
 */
final class ActivityController
{
    public function __construct(private Container $container) {}

    /** @param array<string, string> $params */
    public function show(Request $request, array $params = []): Response
    {
        $entityType = (string) ($params['type'] ?? '');
        $entityId   = (int) ($params['id'] ?? 0);
        $page       = max(1, $request->queryInt('page', 1));
        $returnTo   = trim($request->queryStringValue('return_to', ''));
        $perPage    = 30;

        $validTypes = ['organisation', 'contact', 'engagement', 'vacancy'];
        if (!in_array($entityType, $validTypes, true) || $entityId < 1) {
            throw HttpException::notFound();
        }

        $entity = $this->loadEntity($entityType, $entityId);
        if ($entity === null) {
            throw HttpException::notFound();
        }

        // Override back URL if returning to a global list
        if ($returnTo === 'contacts') {
            $entity['backUrl'] = '/contacts';
        }

        /** @var ActivityRepository $repo */
        $repo = $this->container->get(ActivityRepository::class);

        $offset     = ($page - 1) * $perPage;
        $activities = $repo->forEntityPaginated($entityType, $entityId, $offset, $perPage);
        $total      = $repo->countForEntity($entityType, $entityId);
        $totalPages = max(1, (int) ceil($total / $perPage));

        /** @var View $view */
        $view = $this->container->get(View::class);
        /** @var Auth $auth */
        $auth = $this->container->get(Auth::class);

        return Response::html($view->render('activity/show', [
            'title'      => 'Activity · ' . $entity['label'],
            'user'       => $auth->user(),
            'entityType' => $entityType,
            'entityId'   => $entityId,
            'entity'     => $entity,
            'activities' => $activities,
            'total'      => $total,
            'page'       => $page,
            'perPage'    => $perPage,
            'totalPages' => $totalPages,
            'returnTo'   => $returnTo,
        ], 'layouts/app'));
    }

    /** @return array{label: string, code: string, backUrl: string}|null */
    private function loadEntity(string $type, int $id): ?array
    {
        switch ($type) {
            case 'organisation':
                /** @var OrganisationRepository $repo */
                $repo = $this->container->get(OrganisationRepository::class);
                $e = $repo->findById($id);
                if ($e === null) return null;
                return [
                    'label'   => $e->name,
                    'code'    => $e->code,
                    'backUrl' => '/organisations/' . $e->code,
                ];

            case 'contact':
                /** @var ContactRepository $repo */
                $repo = $this->container->get(ContactRepository::class);
                $e = $repo->findById($id);
                if ($e === null) return null;
                /** @var OrganisationRepository $orgRepo */
                $orgRepo = $this->container->get(OrganisationRepository::class);
                $org = $orgRepo->findById($e->organisationId);
                $orgCode = $org ? $org->code : (string) $e->organisationId;
                return [
                    'label'   => $e->fullName(),
                    'code'    => $e->code,
                    'backUrl' => '/organisations/' . $orgCode . '/contacts/' . $e->code,
                ];

            case 'engagement':
                /** @var EngagementRepository $repo */
                $repo = $this->container->get(EngagementRepository::class);
                $e = $repo->findById($id);
                if ($e === null) return null;
                return [
                    'label'   => $e->name,
                    'code'    => $e->code,
                    'backUrl' => '/engagements/' . $e->code,
                ];

            case 'vacancy':
                /** @var VacancyRepository $repo */
                $repo = $this->container->get(VacancyRepository::class);
                $e = $repo->findById($id);
                if ($e === null) return null;
                return [
                    'label'   => $e->title,
                    'code'    => $e->code,
                    'backUrl' => '/vacancies/' . $e->code,
                ];
        }

        return null;
    }
}

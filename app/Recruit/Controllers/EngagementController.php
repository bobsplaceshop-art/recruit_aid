<?php
declare(strict_types=1);

namespace CorporateAid\Recruit\Controllers;

use CorporateAid\Core\Auth;
use CorporateAid\Core\Container;
use CorporateAid\Core\Exceptions\HttpException;
use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Core\Session;
use CorporateAid\Core\View;
use CorporateAid\Recruit\Repositories\EngagementRepository;
use CorporateAid\Recruit\Repositories\OrganisationRepository;
use CorporateAid\Recruit\Services\EngagementService;

final class EngagementController
{
    public function __construct(private Container $container) {}

    public function index(Request $request, array $params = []): Response
    {
        /** @var View $view */
        $view = $this->container->get(View::class);
        /** @var Auth $auth */
        $auth = $this->container->get(Auth::class);
        /** @var EngagementRepository $engagements */
        $engagements = $this->container->get(EngagementRepository::class);

        $search = trim($request->queryStringValue('q', ''));
        $status = trim($request->queryStringValue('status', ''));
        $page   = max(1, $request->queryInt('page', 1));

        $result = $engagements->paginate(['search' => $search, 'status' => $status], $page, 25);
        $counts = $engagements->countsByStatus();

        /** @var OrganisationRepository $orgs */
        $orgs = $this->container->get(OrganisationRepository::class);
        $orgMap = [];
        foreach ($result['rows'] as $e) {
            if (!isset($orgMap[$e->organisationId])) {
                $orgMap[$e->organisationId] = $orgs->findById($e->organisationId);
            }
        }

        $html = $view->render('engagements/index', [
            'title'       => 'Engagements',
            'user'        => $auth->user(),
            'engagements' => $result['rows'],
            'orgMap'      => $orgMap,
            'total'       => $result['total'],
            'page'        => $result['page'],
            'total_pages' => $result['total_pages'],
            'counts'      => $counts,
            'search'      => $search,
            'status'      => $status,
        ], 'layouts/app');

        return Response::html($html);
    }

    public function showCreate(Request $request, array $params = []): Response
    {
        /** @var View $view */
        $view = $this->container->get(View::class);
        /** @var Auth $auth */
        $auth = $this->container->get(Auth::class);
        /** @var EngagementRepository $engagements */
        $engagements = $this->container->get(EngagementRepository::class);
        /** @var OrganisationRepository $orgs */
        $orgs = $this->container->get(OrganisationRepository::class);

        $preselectedOrg = null;
        $orgCode = $request->queryStringValue('organisation', '');
        
        if ($orgCode !== '') {
            $preselectedOrg = $orgs->findByCode($orgCode);
        }

        // Fetch all organisations (any status) so prospects can be engaged too.
        $orgList = $orgs->paginate([], 1, 500)['rows'];

        $html = $view->render('engagements/form', [
            'title'          => 'New Engagement',
            'user'           => $auth->user(),
            'engagement'     => null,
            'organisations'  => $orgList,
            'preselectedOrg' => $preselectedOrg, // The view expects the OBJECT
            'packages'       => $engagements->packages(),
            'old'            => [],
            'errors'         => [],
        ], 'layouts/app');

        return Response::html($html);
    }

    public function store(Request $request, array $params = []): Response
    {
        /** @var EngagementService $service */
        $service = $this->container->get(EngagementService::class);
        /** @var OrganisationRepository $orgs */
        $orgs = $this->container->get(OrganisationRepository::class);
        /** @var Session $session */
        $session = $this->container->get(Session::class);
        /** @var View $view */
        $view = $this->container->get(View::class);
        /** @var Auth $auth */
        $auth = $this->container->get(Auth::class);
        /** @var EngagementRepository $engagements */
        $engagements = $this->container->get(EngagementRepository::class);

        $orgCode = trim($request->inputString('organisation_code', ''));
        $org = $orgs->findByCode($orgCode);

        if (!$org) {
            $session->flash('error', 'Please choose a valid organisation.');
            return Response::redirect('/engagements/create');
        }

        $input = $this->collectInput($request);
        $result = $service->create($org->id, $input);

        if (!empty($result['errors'])) {
            $html = $view->render('engagements/form', [
                'title'          => 'New Engagement',
                'user'           => $auth->user(),
                'engagement'     => null,
                'organisations'  => $orgs->paginate(['status' => 'active'], 1, 500)['rows'],
                'preselectedOrg' => $org,
                'packages'       => $engagements->packages(),
                'old'            => $input,
                'errors'         => $result['errors'],
            ], 'layouts/app');
            return Response::html($html)->withHeader('X-Form-Error', '1');
        }

        $session->flash('success', 'Engagement created: ' . $result['engagement']->name);
        return Response::redirect('/engagements/' . $result['engagement']->code);
    }

    public function show(Request $request, array $params = []): Response
    {
        $code = ($params['code'] ?? '');

        /** @var EngagementRepository $engagements */
        $engagements = $this->container->get(EngagementRepository::class);
        $engagement = $engagements->findByCode($code);

        if (!$engagement) {
            throw HttpException::notFound('That engagement does not exist, or has been archived.');
        }

        /** @var OrganisationRepository $orgs */
        $orgs = $this->container->get(OrganisationRepository::class);
        $org = $orgs->findById($engagement->organisationId);

        /** @var View $view */
        $view = $this->container->get(View::class);
        /** @var Auth $auth */
        $auth = $this->container->get(Auth::class);
        /** @var EngagementService $service */
        $service = $this->container->get(EngagementService::class);

        $recruiters = $engagements->recruitersFor($engagement->id);
        $capacity = $service->capacityStatus($engagement->id);

        /** @var \CorporateAid\Platform\Repositories\UserRepository $users */
        $users = $this->container->get(\CorporateAid\Platform\Repositories\UserRepository::class);
        $staffUsers = $users->listForAssignment();

        $html = $view->render('engagements/show', [
            'title'        => $engagement->name,
            'user'         => $auth->user(),
            'engagement'   => $engagement,
            'organisation' => $org,
            'recruiters'   => $recruiters,
            'capacity'     => $capacity,
            'staffUsers'   => $staffUsers,
        ], 'layouts/app');

        return Response::html($html);
    }

    public function showEdit(Request $request, array $params = []): Response
    {
        $code = ($params['code'] ?? '');

        /** @var EngagementRepository $engagements */
        $engagements = $this->container->get(EngagementRepository::class);
        $engagement = $engagements->findByCode($code);

        if (!$engagement) {
            throw HttpException::notFound('That engagement does not exist, or has been archived.');
        }

        /** @var OrganisationRepository $orgs */
        $orgs = $this->container->get(OrganisationRepository::class);

        /** @var View $view */
        $view = $this->container->get(View::class);
        /** @var Auth $auth */
        $auth = $this->container->get(Auth::class);

        $html = $view->render('engagements/form', [
            'title'          => 'Edit Engagement · ' . $engagement->name,
            'user'           => $auth->user(),
            'engagement'     => $engagement,
            'organisations'  => $orgs->paginate(['status' => 'active'], 1, 500)['rows'],
            'preselectedOrg' => $orgs->findById($engagement->organisationId),
            'packages'       => $engagements->packages(),
            'old'            => [],
            'errors'         => [],
        ], 'layouts/app');

        return Response::html($html);
    }

    public function update(Request $request, array $params = []): Response
    {
        $code = ($params['code'] ?? '');

        /** @var EngagementRepository $engagements */
        $engagements = $this->container->get(EngagementRepository::class);
        $engagement = $engagements->findByCode($code);

        if (!$engagement) {
            throw HttpException::notFound('That engagement does not exist, or has been archived.');
        }

        /** @var EngagementService $service */
        $service = $this->container->get(EngagementService::class);
        /** @var OrganisationRepository $orgs */
        $orgs = $this->container->get(OrganisationRepository::class);
        /** @var Session $session */
        $session = $this->container->get(Session::class);
        /** @var View $view */
        $view = $this->container->get(View::class);
        /** @var Auth $auth */
        $auth = $this->container->get(Auth::class);

        $input = $this->collectInput($request);
        $result = $service->update($engagement->id, $input);

        if (!$result['success']) {
            $html = $view->render('engagements/form', [
                'title'          => 'Edit Engagement · ' . $engagement->name,
                'user'           => $auth->user(),
                'engagement'     => $engagement,
                'organisations'  => $orgs->paginate(['status' => 'active'], 1, 500)['rows'],
                'preselectedOrg' => $orgs->findById($engagement->organisationId),
                'packages'       => $engagements->packages(),
                'old'            => $input,
                'errors'         => $result['errors'],
            ], 'layouts/app');
            return Response::html($html)->withHeader('X-Form-Error', '1');
        }

        $session->flash('success', 'Engagement updated.');
        return Response::redirect('/engagements/' . $engagement->code);
    }

    public function archive(Request $request, array $params = []): Response
    {
        return $this->lifecycleAction($request, $params, 'archive');
    }

    public function restore(Request $request, array $params = []): Response
    {
        return $this->lifecycleAction($request, $params, 'restore');
    }

    public function destroy(Request $request, array $params = []): Response
    {
        $code = ($params['code'] ?? '');
        /** @var EngagementRepository $engagements */
        $engagements = $this->container->get(EngagementRepository::class);
        $engagement = $engagements->findByCode($code, true);

        if (!$engagement) {
            throw HttpException::notFound('That engagement does not exist.');
        }

        /** @var EngagementService $service */
        $service = $this->container->get(EngagementService::class);
        /** @var Session $session */
        $session = $this->container->get(Session::class);

        $phrase = $request->inputString('confirmation', '');
        $reason = trim($request->inputString('reason', '')) ?: null;

        $result = $service->delete($engagement->id, $phrase, $reason);

        if ($result->success) {
            $session->flash('success', $result->message);
            return Response::redirect('/engagements');
        }

        $session->flash('error', $result->message);
        return Response::redirect('/engagements/' . $engagement->code);
    }

    public function deleteInfo(Request $request, array $params = []): Response
    {
        $code = ($params['code'] ?? '');
        /** @var EngagementRepository $engagements */
        $engagements = $this->container->get(EngagementRepository::class);
        $engagement = $engagements->findByCode($code, true);

        if (!$engagement) {
            return Response::json(['ok' => false, 'message' => 'Engagement not found.'], 404);
        }

        /** @var EngagementService $service */
        $service = $this->container->get(EngagementService::class);
        $result = $service->inspectDelete($engagement->id);

        if (!$result->success) {
            return Response::json(['ok' => false, 'message' => $result->message], 403);
        }

        return Response::json([
            'ok'              => true,
            'has_dependents'  => $result->hasDependents,
            'dependents'      => $result->dependents,
            'required_phrase' => $result->requiredPhrase,
        ]);
    }

    public function assignRecruiter(Request $request, array $params = []): Response
    {
        $code = ($params['code'] ?? '');
        $userId = $request->inputInt('user_id', 0);
        $role = $request->inputString('role', 'recruiter');

        /** @var EngagementRepository $engagements */
        $engagements = $this->container->get(EngagementRepository::class);
        $engagement = $engagements->findByCode($code);

        if (!$engagement) {
            throw HttpException::notFound('That engagement does not exist.');
        }

        /** @var EngagementService $service */
        $service = $this->container->get(EngagementService::class);
        /** @var Session $session */
        $session = $this->container->get(Session::class);

        if ($userId > 0) {
            $service->assignRecruiter($engagement->id, $userId, $role);
            $session->flash('success', 'Recruiter assigned.');
        } else {
            $session->flash('error', 'Please choose a user.');
        }

        return Response::redirect('/engagements/' . $engagement->code);
    }

    public function unassignRecruiter(Request $request, array $params = []): Response
    {
        $code = ($params['code'] ?? '');
        $userId = $request->inputInt('user_id', 0);

        /** @var EngagementRepository $engagements */
        $engagements = $this->container->get(EngagementRepository::class);
        $engagement = $engagements->findByCode($code);

        if (!$engagement) {
            throw HttpException::notFound('That engagement does not exist.');
        }

        /** @var EngagementService $service */
        $service = $this->container->get(EngagementService::class);
        /** @var Session $session */
        $session = $this->container->get(Session::class);

        if ($userId > 0) {
            $service->unassignRecruiter($engagement->id, $userId);
            $session->flash('success', 'Recruiter unassigned.');
        }

        return Response::redirect('/engagements/' . $engagement->code);
    }

    private function lifecycleAction(Request $request, array $params, string $method): Response
    {
        $code = ($params['code'] ?? '');
        /** @var EngagementRepository $engagements */
        $engagements = $this->container->get(EngagementRepository::class);
        $engagement = $engagements->findByCode($code, true);

        if (!$engagement) {
            throw HttpException::notFound('That engagement does not exist.');
        }

        /** @var EngagementService $service */
        $service = $this->container->get(EngagementService::class);
        /** @var Session $session */
        $session = $this->container->get(Session::class);

        $reason = trim($request->inputString('reason', '')) ?: null;

        try {
            $result = $method === 'archive'
                ? $service->archive($engagement->id, $reason)
                : $service->restore($engagement->id);

            $session->flash($result->success ? 'success' : 'error', $result->message);
        } catch (\Throwable $e) {
            $session->flash('error', 'Action failed: ' . $e->getMessage());
        }

        return Response::redirect('/engagements/' . $engagement->code);
    }

    private function collectInput(Request $request): array
    {
        $countries = $request->input('countries', []);
        if (is_string($countries)) $countries = array_map('trim', explode(',', $countries));
        if (!is_array($countries)) $countries = [];

        $stages = $request->input('stages_included', []);
        if (is_string($stages)) $stages = array_map('trim', explode(',', $stages));
        if (!is_array($stages)) $stages = [];

        $cf = $request->input('cf', []);
        if (!is_array($cf)) $cf = [];

        return [
            'package_id'                  => $request->input('package_id', null),
            'name'                        => trim($request->inputString('name', '')),
            'service_type'                => trim($request->inputString('service_type', '3_month')),
            'status'                      => trim($request->inputString('status', 'draft')),
            'start_date'                  => trim($request->inputString('start_date', '')),
            'end_date'                    => trim($request->inputString('end_date', '')),
            'agreed_active_role_capacity' => $request->inputInt('agreed_active_role_capacity', 1),
            'reporting_frequency'         => trim($request->inputString('reporting_frequency', 'monthly')),
            'countries'                   => $countries,
            'scope_description'           => trim($request->inputString('scope_description', '')),
            'stages_included'             => $stages,
            'notes'                       => trim($request->inputString('notes', '')),
            'cf'                          => $cf,
        ];
    }
}

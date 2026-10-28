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
use CorporateAid\Recruit\Repositories\ContactRepository;
use CorporateAid\Recruit\Repositories\EngagementRepository;
use CorporateAid\Recruit\Repositories\OrganisationRepository;
use CorporateAid\Platform\Repositories\UserRepository;
use CorporateAid\Recruit\Repositories\VacancyRepository;
use CorporateAid\Recruit\Repositories\VacancyStageRepository;
use CorporateAid\Recruit\Services\VacancyService;

final class VacancyController
{
    public function __construct(private Container $container) {}

    public function index(Request $request, array $params = []): Response
    {
        /** @var View $view */
        $view = $this->container->get(View::class);
        /** @var Auth $auth */
        $auth = $this->container->get(Auth::class);
        /** @var VacancyRepository $vacancies */
        $vacancies = $this->container->get(VacancyRepository::class);

        $search = trim($request->queryStringValue('q', ''));
        $status = trim($request->queryStringValue('status', ''));
        $page   = max(1, $request->queryInt('page', 1));

        $result = $vacancies->paginate(['search' => $search, 'status' => $status], $page, 25);
        $counts = $vacancies->countsByStatus();

        /** @var OrganisationRepository $orgs */
        $orgs = $this->container->get(OrganisationRepository::class);
        $orgMap = [];
        foreach ($result['rows'] as $v) {
            if (!isset($orgMap[$v->organisationId])) {
                $orgMap[$v->organisationId] = $orgs->findById($v->organisationId);
            }
        }

        $html = $view->render('vacancies/index', [
            'title'       => 'Vacancies',
            'user'        => $auth->user(),
            'vacancies'   => $result['rows'],
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

    /**
     * GET /vacancies/create
     * Optional ?organisation=ORG-XXX or ?engagement=ENG-XXX preselects.
     */
    public function showCreate(Request $request, array $params = []): Response
    {
        /** @var View $view */
        $view = $this->container->get(View::class);
        /** @var Auth $auth */
        $auth = $this->container->get(Auth::class);
        /** @var OrganisationRepository $orgs */
        $orgs = $this->container->get(OrganisationRepository::class);
        /** @var EngagementRepository $engagements */
        $engagements = $this->container->get(EngagementRepository::class);
        /** @var UserRepository $users */
        $users = $this->container->get(UserRepository::class);

        $preselectedOrg = null;
        $preselectedEngagement = null;

        $orgCode = $request->queryStringValue('organisation', '');
        if ($orgCode !== '') $preselectedOrg = $orgs->findByCode($orgCode);

        $engCode = $request->queryStringValue('engagement', '');
        if ($engCode !== '') {
            $preselectedEngagement = $engagements->findByCode($engCode);
            if (!$preselectedOrg && $preselectedEngagement) {
                $preselectedOrg = $orgs->findById($preselectedEngagement->organisationId);
            }
        }

        // Preload engagements and contacts grouped by organisation (for filtering)
        $engagementsByOrg = $this->buildEngagementsByOrg($engagements);
        $contactsByOrg = $this->buildContactsByOrg($orgs);

        $html = $view->render('vacancies/form', [
            'title'                 => 'New Vacancy',
            'user'                  => $auth->user(),
            'vacancy'               => null,
            'organisations'         => $orgs->paginate([], 1, 500)['rows'],
            'preselectedOrg'        => $preselectedOrg,
            'preselectedEngagement' => $preselectedEngagement,
            'staffUsers'            => $users->listForAssignment(),
            'engagementsByOrg'      => $engagementsByOrg,
            'contactsByOrg'         => $contactsByOrg,
            'requirements'          => [],
            'old'                   => [],
            'errors'                => [],
        ], 'layouts/app');

        return Response::html($html);
    }

    public function store(Request $request, array $params = []): Response
    {
        /** @var VacancyService $service */
        $service = $this->container->get(VacancyService::class);
        /** @var OrganisationRepository $orgs */
        $orgs = $this->container->get(OrganisationRepository::class);
        /** @var EngagementRepository $engagements */
        $engagements = $this->container->get(EngagementRepository::class);
        /** @var UserRepository $users */
        $users = $this->container->get(UserRepository::class);
        /** @var Session $session */
        $session = $this->container->get(Session::class);
        /** @var View $view */
        $view = $this->container->get(View::class);
        /** @var Auth $auth */
        $auth = $this->container->get(Auth::class);

        $orgCode = trim($request->inputString('organisation_code', ''));
        $org = $orgs->findByCode($orgCode);

        if (!$org) {
            $session->flash('error', 'Please choose a valid organisation.');
            return Response::redirect('/vacancies/create');
        }

        $input = $this->collectInput($request);
        $result = $service->create($org->id, $input);

        if (!empty($result['errors'])) {
            $orgsList = $orgs->paginate([], 1, 500)['rows'];
            $engagementsByOrg = $this->buildEngagementsByOrg($engagements);
            $contactsByOrg = $this->buildContactsByOrg($orgs);

            $html = $view->render('vacancies/form', [
                'title'          => 'New Vacancy',
                'user'           => $auth->user(),
                'vacancy'        => null,
                'organisations'  => $orgsList,
                'preselectedOrg' => $org,
                'preselectedEngagement' => null,
                'staffUsers'     => $users->listForAssignment(),
                'engagementsByOrg' => $engagementsByOrg,
                'contactsByOrg'    => $contactsByOrg,
                'requirements'   => [],
                'old'            => $input,
                'errors'         => $result['errors'],
            ], 'layouts/app');
            return Response::html($html)->withHeader('X-Form-Error', '1');
        }

        // Show capacity warning as a flash if present
        if ($result['capacity_warning']) {
            $w = $result['capacity_warning'];
            $session->flash('error',
                'Note: ' . $w['engagement_name'] . ' is at capacity (' . $w['current_active'] . '/' . $w['capacity'] . '). ' .
                'This vacancy was created but exceeds the engagement\'s agreed capacity.'
            );
        }

        $vacancy = $result['vacancy'];
        if ($vacancy === null) {
            $session->flash('error', 'Vacancy was created but could not be retrieved.');
            return Response::redirect('/vacancies');
        }

        $session->flash('success', 'Vacancy created: ' . $vacancy->title);
        return Response::redirect('/vacancies/' . $vacancy->code);
    }

    public function show(Request $request, array $params = []): Response
    {
        $code = ($params['code'] ?? '');

        /** @var VacancyRepository $vacancies */
        $vacancies = $this->container->get(VacancyRepository::class);
        $vacancy = $vacancies->findByCode($code);

        if (!$vacancy) {
            throw HttpException::notFound('That vacancy does not exist, or has been archived.');
        }

        /** @var OrganisationRepository $orgs */
        $orgs = $this->container->get(OrganisationRepository::class);
        $org = $orgs->findById($vacancy->organisationId);

        $engagement = null;
        if ($vacancy->engagementId) {
            /** @var EngagementRepository $engagements */
            $engagements = $this->container->get(EngagementRepository::class);
            $engagement = $engagements->findById($vacancy->engagementId);
        }

        /** @var VacancyStageRepository $stageRepo */
        $stageRepo = $this->container->get(VacancyStageRepository::class);
        $stages = $stageRepo->forVacancy($vacancy->id);

        $requirements = $vacancies->requirementsFor($vacancy->id);

        /** @var View $view */
        $view = $this->container->get(View::class);
        /** @var Auth $auth */
        $auth = $this->container->get(Auth::class);

        $html = $view->render('vacancies/show', [
            'title'        => $vacancy->title,
            'user'         => $auth->user(),
            'vacancy'      => $vacancy,
            'organisation' => $org,
            'engagement'   => $engagement,
            'stages'       => $stages,
            'requirements' => $requirements,
        ], 'layouts/app');

        return Response::html($html);
    }

    public function showEdit(Request $request, array $params = []): Response
    {
        $code = ($params['code'] ?? '');

        /** @var VacancyRepository $vacancies */
        $vacancies = $this->container->get(VacancyRepository::class);
        $vacancy = $vacancies->findByCode($code);

        if (!$vacancy) {
            throw HttpException::notFound('That vacancy does not exist, or has been archived.');
        }

        /** @var OrganisationRepository $orgs */
        $orgs = $this->container->get(OrganisationRepository::class);
        /** @var UserRepository $users */
        $users = $this->container->get(UserRepository::class);

        /** @var View $view */
        $view = $this->container->get(View::class);
        /** @var Auth $auth */
        $auth = $this->container->get(Auth::class);

        /** @var EngagementRepository $engagements */
        $engagements = $this->container->get(EngagementRepository::class);
        $engagementsByOrg = $this->buildEngagementsByOrg($engagements);
        $contactsByOrg = $this->buildContactsByOrg($orgs);

        $html = $view->render('vacancies/form', [
            'title'                 => 'Edit Vacancy · ' . $vacancy->title,
            'user'                  => $auth->user(),
            'vacancy'               => $vacancy,
            'organisations'         => $orgs->paginate([], 1, 500)['rows'],
            'preselectedOrg'        => $orgs->findById($vacancy->organisationId),
            'preselectedEngagement' => $vacancy->engagementId ? $engagements->findById($vacancy->engagementId) : null,
            'staffUsers'            => $users->listForAssignment(),
            'engagementsByOrg'      => $engagementsByOrg,
            'contactsByOrg'         => $contactsByOrg,
            'requirements'          => $vacancies->requirementsFor($vacancy->id),
            'old'                   => [],
            'errors'                => [],
        ], 'layouts/app');

        return Response::html($html);
    }

    public function update(Request $request, array $params = []): Response
    {
        $code = ($params['code'] ?? '');

        /** @var VacancyRepository $vacancies */
        $vacancies = $this->container->get(VacancyRepository::class);
        $vacancy = $vacancies->findByCode($code);

        if (!$vacancy) {
            throw HttpException::notFound('That vacancy does not exist, or has been archived.');
        }

        /** @var VacancyService $service */
        $service = $this->container->get(VacancyService::class);
        /** @var OrganisationRepository $orgs */
        $orgs = $this->container->get(OrganisationRepository::class);
        /** @var UserRepository $users */
        $users = $this->container->get(UserRepository::class);
        /** @var Session $session */
        $session = $this->container->get(Session::class);
        /** @var View $view */
        $view = $this->container->get(View::class);
        /** @var Auth $auth */
        $auth = $this->container->get(Auth::class);

        $input = $this->collectInput($request);
        $result = $service->update($vacancy->id, $input);

        if (!$result['success']) {
            $html = $view->render('vacancies/form', [
                'title'          => 'Edit Vacancy · ' . $vacancy->title,
                'user'           => $auth->user(),
                'vacancy'        => $vacancy,
                'organisations'  => $orgs->paginate([], 1, 500)['rows'],
                'preselectedOrg' => $orgs->findById($vacancy->organisationId),
                'preselectedEngagement' => $vacancy->engagementId ? $this->container->get(EngagementRepository::class)->findById($vacancy->engagementId) : null,
                'staffUsers'     => $users->listForAssignment(),
                'requirements'   => $vacancies->requirementsFor($vacancy->id),
                'old'            => $input,
                'errors'         => $result['errors'],
            ], 'layouts/app');
            return Response::html($html)->withHeader('X-Form-Error', '1');
        }

        $session->flash('success', 'Vacancy updated.');
        return Response::redirect('/vacancies/' . $vacancy->code);
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
        /** @var VacancyRepository $vacancies */
        $vacancies = $this->container->get(VacancyRepository::class);
        $vacancy = $vacancies->findByCode($code, true);

        if (!$vacancy) {
            throw HttpException::notFound('That vacancy does not exist.');
        }

        /** @var VacancyService $service */
        $service = $this->container->get(VacancyService::class);
        /** @var Session $session */
        $session = $this->container->get(Session::class);

        $phrase = $request->inputString('confirmation', '');
        $reason = trim($request->inputString('reason', '')) ?: null;

        $result = $service->delete($vacancy->id, $phrase, $reason);

        if ($result->success) {
            $session->flash('success', $result->message);
            return Response::redirect('/vacancies');
        }

        $session->flash('error', $result->message);
        return Response::redirect('/vacancies/' . $vacancy->code);
    }

    public function deleteInfo(Request $request, array $params = []): Response
    {
        $code = ($params['code'] ?? '');
        /** @var VacancyRepository $vacancies */
        $vacancies = $this->container->get(VacancyRepository::class);
        $vacancy = $vacancies->findByCode($code, true);

        if (!$vacancy) {
            return Response::json(['ok' => false, 'message' => 'Vacancy not found.'], 404);
        }

        /** @var VacancyService $service */
        $service = $this->container->get(VacancyService::class);
        $result = $service->inspectDelete($vacancy->id);

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

    private function lifecycleAction(Request $request, array $params, string $method): Response
    {
        $code = ($params['code'] ?? '');
        /** @var VacancyRepository $vacancies */
        $vacancies = $this->container->get(VacancyRepository::class);
        $vacancy = $vacancies->findByCode($code, true);

        if (!$vacancy) {
            throw HttpException::notFound('That vacancy does not exist.');
        }

        /** @var VacancyService $service */
        $service = $this->container->get(VacancyService::class);
        /** @var Session $session */
        $session = $this->container->get(Session::class);

        $reason = trim($request->inputString('reason', '')) ?: null;

        try {
            $result = $method === 'archive'
                ? $service->archive($vacancy->id, $reason)
                : $service->restore($vacancy->id);

            $session->flash($result->success ? 'success' : 'error', $result->message);
        } catch (\Throwable $e) {
            $session->flash('error', 'Action failed: ' . $e->getMessage());
        }

        return Response::redirect('/vacancies/' . $vacancy->code);
    }

    private function collectInput(Request $request): array
    {
        $skills = $request->input('skills', []);
        if (is_string($skills)) $skills = array_map('trim', explode(',', $skills));
        if (!is_array($skills)) $skills = [];

        $languages = $request->input('languages', []);
        if (is_string($languages)) $languages = array_map('trim', explode(',', $languages));
        if (!is_array($languages)) $languages = [];

        $cf = $request->input('cf', []);
        if (!is_array($cf)) $cf = [];

        // Requirement lists (as multi-line textareas)
        $respList = $this->linesToList($request->input('responsibilities_list', ''));
        $reqList  = $this->linesToList($request->input('requirements_list', ''));
        $prefList = $this->linesToList($request->input('preferred_list', ''));

        return [
            'engagement_id'             => $request->input('engagement_id', null),
            'title'                     => trim($request->inputString('title', '')),
            'department'                => trim($request->inputString('department', '')),
            'country_code'              => trim($request->inputString('country_code', '')),
            'city'                      => trim($request->inputString('city', '')),
            'work_mode'                 => trim($request->inputString('work_mode', 'onsite')),
            'employment_type'           => trim($request->inputString('employment_type', 'permanent')),
            'seniority'                 => trim($request->inputString('seniority', 'mid')),
            'positions_count'           => $request->inputInt('positions_count', 1),
            'opening_date'              => trim($request->inputString('opening_date', '')),
            'target_hire_date'          => trim($request->inputString('target_hire_date', '')),
            'recruiter_id'              => $request->input('recruiter_id', null),
            'hiring_manager_contact_id' => $request->input('hiring_manager_contact_id', null),
            'salary_min'                => trim($request->inputString('salary_min', '')),
            'salary_max'                => trim($request->inputString('salary_max', '')),
            'currency'                  => trim($request->inputString('currency', '')),
            'description'               => trim($request->inputString('description', '')),
            'responsibilities'          => trim($request->inputString('responsibilities', '')),
            'requirements'              => trim($request->inputString('requirements', '')),
            'preferred_qualifications'  => trim($request->inputString('preferred_qualifications', '')),
            'responsibilities_list'     => $respList,
            'requirements_list'         => $reqList,
            'preferred_list'            => $prefList,
            'skills'                    => $skills,
            'languages'                 => $languages,
            'assessment_criteria'       => trim($request->inputString('assessment_criteria', '')),
            'internal_notes'            => trim($request->inputString('internal_notes', '')),
            'client_notes'              => trim($request->inputString('client_notes', '')),
            'status'                    => trim($request->inputString('status', 'draft')),
            'is_public'                 => $request->inputBool('is_public', false),
            'cf'                        => $cf,
        ];
    }

    private function linesToList(mixed $value): array
    {
        if (!is_string($value)) return [];
        $lines = preg_split('/\r?\n/', $value) ?: [];
        $out = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line !== '') $out[] = $line;
        }
        return $out;
    }

    /**
     * Preload active engagements grouped by organisation_id.
     * @return array<int, array<int, array{id:int, code:string, name:string}>>
     */
    private function buildEngagementsByOrg(\CorporateAid\Recruit\Repositories\EngagementRepository $engagements): array
    {
        $all = $engagements->paginate([], 1, 1000)['rows'];
        $out = [];
        foreach ($all as $e) {
            $out[$e->organisationId][] = [
                'id'   => $e->id,
                'code' => $e->code,
                'name' => $e->name,
            ];
        }
        return $out;
    }

    /**
     * Preload active contacts grouped by organisation_id.
     * @return array<int, array<int, array{id:int, name:string, code:string, position:?string}>>
     */
    private function buildContactsByOrg(\CorporateAid\Recruit\Repositories\OrganisationRepository $orgs): array
    {
        /** @var ContactRepository $contactRepo */
        $contactRepo = $this->container->get(ContactRepository::class);
        $allContacts = $contactRepo->allActive();
        $out = [];
        foreach ($allContacts as $c) {
            $out[$c->organisationId][] = [
                'id'       => $c->id,
                'code'     => $c->code,
                'name'     => $c->fullName(),
                'position' => $c->position,
            ];
        }
        return $out;
    }
}

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
use CorporateAid\Platform\Repositories\UserRepository;
use CorporateAid\Recruit\Repositories\CandidateRepository;
use CorporateAid\Recruit\Services\CandidateService;
use CorporateAid\Platform\Services\LookupService;
use CorporateAid\Platform\Repositories\LookupRepository;

final class CandidateController
{
    public function __construct(private Container $container) {}

    /** @param array<string, string> $params */
    public function index(Request $request, array $params = []): Response
    {
        /** @var View $view */
        $view = $this->container->get(View::class);
        /** @var Auth $auth */
        $auth = $this->container->get(Auth::class);
        /** @var CandidateRepository $candidates */
        $candidates = $this->container->get(CandidateRepository::class);

        $search = trim($request->queryStringValue('q', ''));
        $status = trim($request->queryStringValue('status', ''));
        $page   = max(1, $request->queryInt('page', 1));

        $filters = [];
        if ($search !== '') $filters['search'] = $search;
        if ($status !== '') $filters['status'] = $status;

        $result = $candidates->paginate($filters, $page, 25);

        return Response::html($view->render('candidates/index', [
            'title'       => 'Candidates',
            'user'        => $auth->user(),
            'rows'        => $result['rows'],
            'total'       => $result['total'],
            'page'        => $result['page'],
            'perPage'     => $result['per_page'],
            'totalPages'  => $result['total_pages'],
            'search'      => $search,
            'status'      => $status,
        ], 'layouts/app'));
    }

    /** @param array<string, string> $params */
    public function show(Request $request, array $params = []): Response
    {
        $code = (string) ($params['code'] ?? '');
        /** @var CandidateRepository $candidates */
        $candidates = $this->container->get(CandidateRepository::class);
        $candidate = $candidates->findByCode($code);
        if (!$candidate) throw HttpException::notFound('That candidate does not exist, or has been archived.');

        /** @var View $view */
        $view = $this->container->get(View::class);
        /** @var Auth $auth */
        $auth = $this->container->get(Auth::class);

        return Response::html($view->render('candidates/show', [
            'title'      => $candidate->fullName(),
            'user'       => $auth->user(),
            'candidate'  => $candidate,
            'skills'     => $candidates->skillsFor($candidate->id),
            'experience' => $candidates->experienceFor($candidate->id),
            'education'  => $candidates->educationFor($candidate->id),
            'tags'       => $candidates->tagsFor($candidate->id),
        ], 'layouts/app'));
    }

    /** @param array<string, string> $params */
    public function showCreate(Request $request, array $params = []): Response
    {
        /** @var View $view */
        $view = $this->container->get(View::class);
        /** @var Auth $auth */
        $auth = $this->container->get(Auth::class);
        /** @var UserRepository $users */
        $users = $this->container->get(UserRepository::class);

        return Response::html($view->render('candidates/form', [
            'title'      => 'New Candidate',
            'user'       => $auth->user(),
            'candidate'  => null,
            'staffUsers' => $users->listForAssignment(),
            'educationLevels' => $this->container->get(LookupRepository::class)->activeFor('education_level'),
            'old'        => [],
            'errors'     => [],
        ], 'layouts/app'));
    }

    /** @param array<string, string> $params */
    public function store(Request $request, array $params = []): Response
    {
        /** @var CandidateService $service */
        $service = $this->container->get(CandidateService::class);
        $result = $service->create($request->all());

        if (!empty($result['errors'])) {
            /** @var View $view */
            $view = $this->container->get(View::class);
            /** @var Auth $auth */
            $auth = $this->container->get(Auth::class);
            /** @var UserRepository $users */
            $users = $this->container->get(UserRepository::class);

            return Response::html($view->render('candidates/form', [
                'title'      => 'New Candidate',
                'user'       => $auth->user(),
                'candidate'  => null,
                'staffUsers' => $users->listForAssignment(),
                'educationLevels' => $this->container->get(LookupRepository::class)->activeFor('education_level'),
                'old'        => $request->all(),
                'errors'     => $result['errors'],
            ], 'layouts/app'));
        }

        $created = $result['candidate'];
        $code = $created !== null ? $created->code : '';
        /** @var Session $session */
        $session = $this->container->get(Session::class);
        $session->flash('success', 'Candidate created successfully.');
        return Response::redirect('/candidates/' . $code);
    }

    /** @param array<string, string> $params */
    public function showEdit(Request $request, array $params = []): Response
    {
        $code = (string) ($params['code'] ?? '');
        /** @var CandidateRepository $candidates */
        $candidates = $this->container->get(CandidateRepository::class);
        $candidate = $candidates->findByCode($code);
        if (!$candidate) throw HttpException::notFound('That candidate does not exist, or has been archived.');

        /** @var View $view */
        $view = $this->container->get(View::class);
        /** @var Auth $auth */
        $auth = $this->container->get(Auth::class);
        /** @var UserRepository $users */
        $users = $this->container->get(UserRepository::class);

        // Preload child rows for form
        $skills = $candidates->skillsFor($candidate->id);
        $experience = $candidates->experienceFor($candidate->id);
        $education = $candidates->educationFor($candidate->id);
        $tags = $candidates->tagsFor($candidate->id);

        return Response::html($view->render('candidates/form', [
            'title'      => 'Edit ' . $candidate->fullName(),
            'user'       => $auth->user(),
            'candidate'  => $candidate,
            'staffUsers' => $users->listForAssignment(),
            'educationLevels' => $this->container->get(LookupRepository::class)->activeFor('education_level'),
            'old'        => [
                'skills'     => implode("\n", array_map(static fn($s) => $s->skillName, $skills)),
                'experience' => array_map(static fn($e) => [
                    'job_title' => $e->jobTitle, 'company' => $e->company, 'location' => $e->location,
                    'start_date' => $e->startDate, 'end_date' => $e->endDate, 'is_current' => $e->isCurrent,
                    'description' => $e->description,
                ], $experience),
                'education'  => array_map(static fn($e) => [
                    'institution' => $e->institution, 'degree' => $e->degree, 'field_of_study' => $e->fieldOfStudy,
                    'start_year' => $e->startYear, 'end_year' => $e->endYear, 'grade' => $e->grade,
                    'description' => $e->description,
                ], $education),
                'tags'       => implode(', ', array_map(static fn($t) => $t->tag, $tags)),
            ],
            'errors'     => [],
        ], 'layouts/app'));
    }

    /** @param array<string, string> $params */
    public function update(Request $request, array $params = []): Response
    {
        $code = (string) ($params['code'] ?? '');
        /** @var CandidateRepository $candidates */
        $candidates = $this->container->get(CandidateRepository::class);
        $candidate = $candidates->findByCode($code);
        if (!$candidate) throw HttpException::notFound('That candidate does not exist, or has been archived.');

        /** @var CandidateService $service */
        $service = $this->container->get(CandidateService::class);
        $result = $service->update($candidate->id, $request->all());

        if (!$result['success']) {
            /** @var View $view */
            $view = $this->container->get(View::class);
            /** @var Auth $auth */
            $auth = $this->container->get(Auth::class);
            /** @var UserRepository $users */
            $users = $this->container->get(UserRepository::class);

            return Response::html($view->render('candidates/form', [
                'title'      => 'Edit ' . $candidate->fullName(),
                'user'       => $auth->user(),
                'candidate'  => $candidate,
                'staffUsers' => $users->listForAssignment(),
                'educationLevels' => $this->container->get(LookupRepository::class)->activeFor('education_level'),
                'old'        => $request->all(),
                'errors'     => $result['errors'],
            ], 'layouts/app'));
        }

        /** @var Session $session */
        $session = $this->container->get(Session::class);
        $session->flash('success', 'Candidate updated.');
        return Response::redirect('/candidates/' . $code);
    }

    /** @param array<string, string> $params */
    public function archive(Request $request, array $params = []): Response
    {
        $code = (string) ($params['code'] ?? '');
        /** @var CandidateRepository $candidates */
        $candidates = $this->container->get(CandidateRepository::class);
        $candidate = $candidates->findByCode($code);
        if (!$candidate) throw HttpException::notFound('That candidate does not exist.');

        /** @var CandidateService $service */
        $service = $this->container->get(CandidateService::class);
        $service->archive($candidate->id);

        /** @var Session $session */
        $session = $this->container->get(Session::class);
        $session->flash('success', 'Candidate archived.');
        return Response::redirect('/candidates');
    }

    /** @param array<string, string> $params */
    public function restore(Request $request, array $params = []): Response
    {
        $code = (string) ($params['code'] ?? '');
        /** @var CandidateRepository $candidates */
        $candidates = $this->container->get(CandidateRepository::class);
        $candidate = $candidates->findByCode($code, true);
        if (!$candidate) throw HttpException::notFound('That candidate does not exist.');

        /** @var CandidateService $service */
        $service = $this->container->get(CandidateService::class);
        $service->restore($candidate->id);

        /** @var Session $session */
        $session = $this->container->get(Session::class);
        $session->flash('success', 'Candidate restored.');
        return Response::redirect('/candidates');
    }
}

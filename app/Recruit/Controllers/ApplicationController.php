<?php
declare(strict_types=1);

namespace CorporateAid\Recruit\Controllers;

use CorporateAid\Core\Auth;
use CorporateAid\Core\Container;
use CorporateAid\Core\Exceptions\HttpException;
use CorporateAid\Core\Database;
use CorporateAid\Core\PDOParams;
use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Core\Session;
use CorporateAid\Core\TenantContext;
use CorporateAid\Core\View;
use CorporateAid\Recruit\Repositories\ApplicationRepository;
use CorporateAid\Recruit\Repositories\CandidateRepository;
use CorporateAid\Recruit\Repositories\VacancyRepository;
use CorporateAid\Recruit\Services\ApplicationService;

final class ApplicationController
{
    public function __construct(private Container $container) {}

    /** @param array<string, string> $params */
    public function index(Request $request, array $params = []): Response
    {
        /** @var View $view */
        $view = $this->container->get(View::class);
        /** @var Auth $auth */
        $auth = $this->container->get(Auth::class);
        /** @var ApplicationRepository $apps */
        $apps = $this->container->get(ApplicationRepository::class);
        /** @var CandidateRepository $candidates */
        $candidates = $this->container->get(CandidateRepository::class);
        /** @var VacancyRepository $vacancies */
        $vacancies = $this->container->get(VacancyRepository::class);
        /** @var TenantContext $tenant */
        $tenant = $this->container->get(TenantContext::class);

        $q = trim($request->queryStringValue('q', ''));
        $page = max(1, $request->queryInt('page', 1));

        $db = $this->container->get(Database::class);
        $where = ["a.`deleted_at` IS NULL", "a.`tenant_id` = ?"];
        $paramsSql = [$tenant->id()];
        
        if ($q !== '') {
            $where[] = "(c.`first_name` LIKE ? OR c.`last_name` LIKE ? OR v.`title` LIKE ? OR a.`code` LIKE ?)";
            $term = '%' . $q . '%';
            $paramsSql[] = $term; 
            $paramsSql[] = $term; 
            $paramsSql[] = $term; 
            $paramsSql[] = $term;
        }
        $whereSql = 'WHERE ' . implode(' AND ', $where);

        $total = $db->scalarInt(
            "SELECT COUNT(*) FROM `recruit_applications` a 
             JOIN `recruit_candidates` c ON c.`id` = a.`candidate_id` 
             JOIN `recruit_vacancies` v ON v.`id` = a.`vacancy_id` 
             $whereSql", 
            PDOParams::positional($paramsSql)
        );

        $perPage = 25;
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;

        $rows = $db->select("
            SELECT a.*, c.first_name, c.last_name, c.code AS cand_code, v.title AS vac_title, v.code AS vac_code
            FROM `recruit_applications` a
            JOIN `recruit_candidates` c ON c.`id` = a.`candidate_id`
            JOIN `recruit_vacancies` v ON v.`id` = a.`vacancy_id`
            $whereSql
            ORDER BY a.`applied_at` DESC
            LIMIT $perPage OFFSET $offset
        ", PDOParams::positional($paramsSql));

        return Response::html($view->render('applications/index', [
            'title'       => 'Applications',
            'user'        => $auth->user(),
            'rows'        => $rows,
            'total'       => $total,
            'page'        => $page,
            'totalPages'  => $totalPages,
            'q'           => $q,
        ], 'layouts/app'));
    }

    /**
     * GET /vacancies/{code}/add-candidate
     * Show the candidate picker for a vacancy.
     *
     * @param array<string, string> $params
     */
    public function addCandidateForm(Request $request, array $params = []): Response
    {
        $code = (string) ($params['code'] ?? '');
        /** @var VacancyRepository $vacancies */
        $vacancies = $this->container->get(VacancyRepository::class);
        $vacancy = $vacancies->findByCode($code);
        if (!$vacancy) throw HttpException::notFound('That vacancy does not exist, or has been archived.');

        $q = trim($request->queryStringValue('q', ''));
        $page = max(1, $request->queryInt('page', 1));

        /** @var CandidateRepository $candidates */
        $candidates = $this->container->get(CandidateRepository::class);
        $result = $candidates->paginate($q !== '' ? ['search' => $q] : [], $page, 20);

        // Filter out candidates already in this vacancy's pipeline
        /** @var ApplicationRepository $apps */
        $apps = $this->container->get(ApplicationRepository::class);
        $existingIds = [];
        foreach ($apps->forVacancy($vacancy->id) as $a) {
            $existingIds[$a->candidateId] = true;
        }
        $available = array_values(array_filter(
            $result['rows'],
            static fn($c) => !isset($existingIds[$c->id])
        ));

        /** @var View $view */
        $view = $this->container->get(View::class);
        /** @var Auth $auth */
        $auth = $this->container->get(Auth::class);

        return Response::html($view->render('applications/add-candidate', [
            'title'       => 'Add candidate to ' . $vacancy->title,
            'user'        => $auth->user(),
            'vacancy'     => $vacancy,
            'candidates'  => $available,
            'q'           => $q,
            'page'        => $result['page'],
            'totalPages'  => $result['total_pages'],
        ], 'layouts/app'));
    }

    /**
     * POST /vacancies/{code}/add-candidate
     * Create an application linking the chosen candidate to this vacancy.
     *
     * @param array<string, string> $params
     */
    public function addCandidate(Request $request, array $params = []): Response
    {
        $code = (string) ($params['code'] ?? '');
        /** @var VacancyRepository $vacancies */
        $vacancies = $this->container->get(VacancyRepository::class);
        $vacancy = $vacancies->findByCode($code);
        if (!$vacancy) throw HttpException::notFound('That vacancy does not exist, or has been archived.');

        $candidateId = $request->inputInt('candidate_id', 0);
        if ($candidateId < 1) {
            /** @var Session $session */
            $session = $this->container->get(Session::class);
            $session->flash('error', 'Please pick a candidate.');
            return Response::redirect('/vacancies/' . $vacancy->code . '/add-candidate');
        }

        /** @var ApplicationService $service */
        $service = $this->container->get(ApplicationService::class);
        $result = $service->create($vacancy->id, $candidateId, [
            'source' => $request->input('source'),
            'notes'  => $request->input('notes'),
        ]);

        /** @var Session $session */
        $session = $this->container->get(Session::class);

        if (!empty($result['errors'])) {
            $session->flash('error', implode(' ', $result['errors']));
            return Response::redirect('/vacancies/' . $vacancy->code . '/add-candidate');
        }

        $session->flash('success', 'Candidate added to the pipeline.');
        return Response::redirect('/vacancies/' . $vacancy->code);
    }

    /**
     * POST /applications/{code}/move
     * Move an application to a new stage, honoring the effective pipeline mode.
     *
     * @param array<string, string> $params
     */
    public function move(Request $request, array $params = []): Response
    {
        $code = (string) ($params['code'] ?? '');
        /** @var ApplicationRepository $apps */
        $apps = $this->container->get(ApplicationRepository::class);
        $application = $apps->findByCode($code);
        if (!$application) throw HttpException::notFound('That application does not exist.');

        $newStageId = $request->inputInt('stage_id', 0);
        if ($newStageId < 1) throw HttpException::notFound('Target stage required.');

        /** @var ApplicationService $service */
        $service = $this->container->get(ApplicationService::class);
        $result = $service->moveToStage($application->id, $newStageId);

        /** @var VacancyRepository $vacancies */
        $vacancies = $this->container->get(VacancyRepository::class);
        $vacancy = $vacancies->findById($application->vacancyId);
        $redirect = $vacancy !== null ? '/vacancies/' . $vacancy->code : '/candidates/' . $application->candidateId;

        /** @var Session $session */
        $session = $this->container->get(Session::class);

        if (!$result['success']) {
            $session->flash('error', $result['error'] ?? 'Could not move the application.');
        } else {
            $passed = $result['passed_through'];
            $suffix = empty($passed)
                ? ''
                : ' (' . count($passed) . ' stage(s) recorded as passed through)';
            $session->flash('success', 'Application moved' . $suffix . '.');
        }

        return Response::redirect($redirect);
    }
}

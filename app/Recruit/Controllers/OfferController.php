<?php
declare(strict_types=1);

namespace CorporateAid\Recruit\Controllers;

use CorporateAid\Core\Auth;
use CorporateAid\Core\Container;
use CorporateAid\Core\Database;
use CorporateAid\Core\Exceptions\HttpException;
use CorporateAid\Core\PDOParams;
use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Core\Row;
use CorporateAid\Core\Session;
use CorporateAid\Core\View;
use CorporateAid\Recruit\Models\Offer;
use CorporateAid\Recruit\Repositories\ApplicationRepository;
use CorporateAid\Recruit\Repositories\CandidateRepository;
use CorporateAid\Recruit\Repositories\OfferRepository;
use CorporateAid\Recruit\Repositories\VacancyRepository;
use CorporateAid\Recruit\Services\OfferService;

final class OfferController
{
    public function __construct(private Container $container) {}

    /** @param array<string, string> $params */
    public function index(Request $request, array $params = []): Response
    {
        /** @var Auth $auth */
        $auth = $this->container->get(Auth::class);
        /** @var Database $db */
        $db = $this->container->get(Database::class);

        $q = trim($request->queryStringValue('q', ''));
        $status = trim($request->queryStringValue('status', ''));
        $page = max(1, $request->queryInt('page', 1));
        $perPage = 25;

        $where = ["o.`deleted_at` IS NULL"];
        $paramsSql = [];
        if ($q !== '') {
            $where[] = "(c.`first_name` LIKE ? OR c.`last_name` LIKE ? OR v.`title` LIKE ? OR o.`code` LIKE ?)";
            $term = '%' . $q . '%';
            $paramsSql[] = $term; $paramsSql[] = $term; $paramsSql[] = $term; $paramsSql[] = $term;
        }
        if ($status !== '' && $status !== 'all') {
            $where[] = "o.`status` = ?";
            $paramsSql[] = $status;
        }
        $whereSql = 'WHERE ' . implode(' AND ', $where);
        $paramObj = PDOParams::positional($paramsSql);

        $total = $db->scalarInt("
            SELECT COUNT(*)
            FROM `recruit_offers` o
            JOIN `recruit_applications` a ON a.`id` = o.`application_id`
            JOIN `recruit_candidates` c ON c.`id` = a.`candidate_id`
            JOIN `recruit_vacancies` v ON v.`id` = a.`vacancy_id`
            $whereSql", $paramObj);

        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;

        $rows = $db->select("
            SELECT o.*, c.`first_name`, c.`last_name`, c.`code` AS `cand_code`,
                   v.`title` AS `vac_title`, v.`code` AS `vac_code`,
                   a.`code` AS `app_code`
            FROM `recruit_offers` o
            JOIN `recruit_applications` a ON a.`id` = o.`application_id`
            JOIN `recruit_candidates` c ON c.`id` = a.`candidate_id`
            JOIN `recruit_vacancies` v ON v.`id` = a.`vacancy_id`
            $whereSql
            ORDER BY o.`created_at` DESC
            LIMIT $perPage OFFSET $offset", $paramObj);

        $counts = ['all' => 0, 'draft' => 0, 'sent' => 0, 'accepted' => 0, 'declined' => 0, 'expired' => 0, 'withdrawn' => 0];
        $statusRows = $db->select("SELECT `status`, COUNT(*) AS n FROM `recruit_offers` WHERE `deleted_at` IS NULL GROUP BY `status`");
        foreach ($statusRows as $r) {
            $s = Row::str($r, 'status');
            if (isset($counts[$s])) $counts[$s] = Row::int($r, 'n');
            $counts['all'] += Row::int($r, 'n');
        }

        /** @var View $view */
        $view = $this->container->get(View::class);

        return Response::html($view->render('offers/index', [
            'title'      => 'Offers',
            'user'       => $auth->user(),
            'rows'       => $rows,
            'total'      => $total,
            'page'       => $page,
            'totalPages' => $totalPages,
            'q'          => $q,
            'status'     => $status,
            'counts'     => $counts,
        ], 'layouts/app'));
    }

    /** @param array<string, string> $params */
    public function createForm(Request $request, array $params = []): Response
    {
        $appCode = (string) ($params['code'] ?? '');
        /** @var ApplicationRepository $apps */
        $apps = $this->container->get(ApplicationRepository::class);
        $application = $apps->findByCode($appCode);
        if (!$application) throw HttpException::notFound('Application not found.');

        // One active offer per application
        /** @var OfferRepository $offers */
        $offers = $this->container->get(OfferRepository::class);
        $existing = $offers->findByApplication($application->id);
        if ($existing !== null && $existing->isActive()) {
            /** @var Session $session */
            $session = $this->container->get(Session::class);
            $session->flash('error', 'This application already has an active offer (' . $existing->code . ').');
            return Response::redirect('/offers/' . $existing->code);
        }

        /** @var CandidateRepository $cands */
        $cands = $this->container->get(CandidateRepository::class);
        /** @var VacancyRepository $vacs */
        $vacs = $this->container->get(VacancyRepository::class);
        /** @var Database $db */
        $db = $this->container->get(Database::class);

        $candidate = $cands->findById($application->candidateId);
        $vacancy = $vacs->findById($application->vacancyId);
        $currencies = $db->select(
            "SELECT `code`, `name`, `symbol` FROM `core_currencies` WHERE `is_active` = 1 ORDER BY `code` ASC"
        );

        /** @var View $view */
        $view = $this->container->get(View::class);
        /** @var Auth $auth */
        $auth = $this->container->get(Auth::class);

        $suggestedCurrency = 'KES';
        $suggestedSalary = '';
        if ($vacancy !== null) {
            if ($vacancy->currency !== null) $suggestedCurrency = $vacancy->currency;
            if ($vacancy->salaryMax !== null)      $suggestedSalary = (string) $vacancy->salaryMax;
            elseif ($vacancy->salaryMin !== null)  $suggestedSalary = (string) $vacancy->salaryMin;
        }

        return Response::html($view->render('offers/form', [
            'title'       => 'Create Offer',
            'user'        => $auth->user(),
            'application' => $application,
            'candidate'   => $candidate,
            'vacancy'     => $vacancy,
            'currencies'  => $currencies,
            'suggestedCurrency' => $suggestedCurrency,
            'suggestedSalary'   => $suggestedSalary,
            'old'         => [],
            'errors'      => [],
        ], 'layouts/app'));
    }

    /** @param array<string, string> $params */
    public function store(Request $request, array $params = []): Response
    {
        $appCode = (string) ($params['code'] ?? '');
        /** @var ApplicationRepository $apps */
        $apps = $this->container->get(ApplicationRepository::class);
        $application = $apps->findByCode($appCode);
        if (!$application) throw HttpException::notFound('Application not found.');

        /** @var OfferService $service */
        $service = $this->container->get(OfferService::class);
        $result = $service->create($application->id, $request->all());

        /** @var Session $session */
        $session = $this->container->get(Session::class);

        if (!empty($result['errors'])) {
            /** @var CandidateRepository $cands */
            $cands = $this->container->get(CandidateRepository::class);
            /** @var VacancyRepository $vacs */
            $vacs = $this->container->get(VacancyRepository::class);
            /** @var Database $db */
            $db = $this->container->get(Database::class);
            $currencies = $db->select(
                "SELECT `code`, `name`, `symbol` FROM `core_currencies` WHERE `is_active` = 1 ORDER BY `code` ASC"
            );

            /** @var View $view */
            $view = $this->container->get(View::class);
            /** @var Auth $auth */
            $auth = $this->container->get(Auth::class);

            return Response::html($view->render('offers/form', [
                'title'       => 'Create Offer',
                'user'        => $auth->user(),
                'application' => $application,
                'candidate'   => $cands->findById($application->candidateId),
                'vacancy'     => $vacs->findById($application->vacancyId),
                'currencies'  => $currencies,
                'suggestedCurrency' => $request->inputString('currency', 'KES'),
                'suggestedSalary'   => $request->inputString('offered_salary', ''),
                'old'         => $request->all(),
                'errors'      => $result['errors'],
            ], 'layouts/app'));
        }

        $code = $result['offer'] !== null ? $result['offer']->code : '';
        $session->flash('success', 'Offer draft created.');
        return Response::redirect('/offers/' . $code);
    }

    /** @param array<string, string> $params */
    public function show(Request $request, array $params = []): Response
    {
        $code = (string) ($params['code'] ?? '');
        /** @var OfferRepository $offers */
        $offers = $this->container->get(OfferRepository::class);
        $offer = $offers->findByCode($code);
        if (!$offer) throw HttpException::notFound('Offer not found.');

        /** @var ApplicationRepository $apps */
        $apps = $this->container->get(ApplicationRepository::class);
        /** @var CandidateRepository $cands */
        $cands = $this->container->get(CandidateRepository::class);
        /** @var VacancyRepository $vacs */
        $vacs = $this->container->get(VacancyRepository::class);

        $application = $apps->findById($offer->applicationId);
        $candidate = $application !== null ? $cands->findById($application->candidateId) : null;
        $vacancy = $application !== null ? $vacs->findById($application->vacancyId) : null;

        $history = $offers->historyFor($offer->id);

        /** @var View $view */
        $view = $this->container->get(View::class);
        /** @var Auth $auth */
        $auth = $this->container->get(Auth::class);

        return Response::html($view->render('offers/show', [
            'title'       => 'Offer ' . $offer->code,
            'user'        => $auth->user(),
            'offer'       => $offer,
            'application' => $application,
            'candidate'   => $candidate,
            'vacancy'     => $vacancy,
            'history'     => $history,
        ], 'layouts/app'));
    }

    /** @param array<string, string> $params */
    public function send(Request $request, array $params = []): Response
    {
        return $this->transition((string) ($params['code'] ?? ''), 'send');
    }

    /** @param array<string, string> $params */
    public function accept(Request $request, array $params = []): Response
    {
        return $this->transition((string) ($params['code'] ?? ''), 'accept', $request->inputString('response_notes') ?: null);
    }

    /** @param array<string, string> $params */
    public function decline(Request $request, array $params = []): Response
    {
        return $this->transition((string) ($params['code'] ?? ''), 'decline', $request->inputString('response_notes') ?: null);
    }

    /** @param array<string, string> $params */
    public function withdraw(Request $request, array $params = []): Response
    {
        return $this->transition((string) ($params['code'] ?? ''), 'withdraw', $request->inputString('notes') ?: null);
    }

    private function transition(string $code, string $action, ?string $notes = null): Response
    {
        /** @var OfferRepository $offers */
        $offers = $this->container->get(OfferRepository::class);
        $offer = $offers->findByCode($code);
        if (!$offer) throw HttpException::notFound('Offer not found.');

        /** @var OfferService $service */
        $service = $this->container->get(OfferService::class);

        $result = match ($action) {
            'send'     => $service->send($offer->id),
            'accept'   => $service->accept($offer->id, $notes),
            'decline'  => $service->decline($offer->id, $notes),
            'withdraw' => $service->withdraw($offer->id, $notes),
            default    => ['success' => false, 'error' => 'Invalid offer action.'],
        };

        /** @var Session $session */
        $session = $this->container->get(Session::class);

        if (!$result['success']) {
            $session->flash('error', $result['error'] ?? 'Could not update the offer.');
        } else {
            $pastTense = match ($action) {
                'send'     => 'sent',
                'accept'   => 'accepted',
                'decline'  => 'declined',
                'withdraw' => 'withdrawn',
                default    => $action,
            };
            $session->flash('success', 'Offer ' . $pastTense . ' successfully.');
        }

        return Response::redirect('/offers/' . $code);
    }
}

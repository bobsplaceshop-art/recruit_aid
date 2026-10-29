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
use CorporateAid\Recruit\Repositories\ApplicationRepository;
use CorporateAid\Recruit\Repositories\CandidateRepository;
use CorporateAid\Recruit\Repositories\InterviewRepository;
use CorporateAid\Recruit\Repositories\ScorecardRepository;
use CorporateAid\Recruit\Repositories\VacancyRepository;
use CorporateAid\Recruit\Services\InterviewService;

final class InterviewController
{
    public function __construct(private Container $container) {}

    /**
     * GET /interviews
     * List all upcoming interviews for the current user.
     */
    /** @param array<string, string> $params */
    public function index(Request $request, array $params = []): Response
    {
        /** @var Auth $auth */
        $auth = $this->container->get(Auth::class);
        /** @var InterviewRepository $repo */
        $repo = $this->container->get(InterviewRepository::class);
        /** @var ApplicationRepository $apps */
        $apps = $this->container->get(ApplicationRepository::class);
        /** @var CandidateRepository $cands */
        $cands = $this->container->get(CandidateRepository::class);
        /** @var VacancyRepository $vacs */
        $vacs = $this->container->get(VacancyRepository::class);

        $user = $auth->user();
        if ($user === null) throw HttpException::forbidden();

        // For super_admin, show all upcoming. Otherwise, only ones they're a participant in.
        if ($user->isSuperAdmin()) {
            $upcoming = $repo->upcomingForUser(0, 50); // placeholder — we'll just show all via findall below
        } else {
            $upcoming = $repo->upcomingForUser($user->id, 50);
        }

        // Hydrate candidate + vacancy for each interview
        $rows = [];
        foreach ($upcoming as $int) {
            $app = $apps->findById($int->applicationId);
            if ($app === null) continue;
            $cand = $cands->findById($app->candidateId);
            $vac = $vacs->findById($app->vacancyId);
            if ($cand === null || $vac === null) continue;
            $rows[] = ['interview' => $int, 'application' => $app, 'candidate' => $cand, 'vacancy' => $vac];
        }

        /** @var View $view */
        $view = $this->container->get(View::class);

        return Response::html($view->render('interviews/index', [
            'title' => 'Interviews',
            'user'  => $user,
            'rows'  => $rows,
        ], 'layouts/app'));
    }

    /**
     * GET /applications/{code}/schedule-interview
     * Show the schedule form for a given application.
     */
    /** @param array<string, string> $params */
    public function scheduleForm(Request $request, array $params = []): Response
    {
        $appCode = (string) ($params['code'] ?? '');
        /** @var ApplicationRepository $apps */
        $apps = $this->container->get(ApplicationRepository::class);
        $application = $apps->findByCode($appCode);
        if (!$application) throw HttpException::notFound('Application not found.');

        /** @var CandidateRepository $cands */
        $cands = $this->container->get(CandidateRepository::class);
        /** @var VacancyRepository $vacs */
        $vacs = $this->container->get(VacancyRepository::class);
        /** @var UserRepository $users */
        $users = $this->container->get(UserRepository::class);

        $candidate = $cands->findById($application->candidateId);
        $vacancy   = $vacs->findById($application->vacancyId);

        /** @var View $view */
        $view = $this->container->get(View::class);
        /** @var Auth $auth */
        $auth = $this->container->get(Auth::class);

        return Response::html($view->render('interviews/form', [
            'title'       => 'Schedule Interview',
            'user'        => $auth->user(),
            'application' => $application,
            'candidate'   => $candidate,
            'vacancy'     => $vacancy,
            'staffUsers'  => $users->listForAssignment(),
            'old'         => [],
            'errors'      => [],
        ], 'layouts/app'));
    }

    /**
     * POST /applications/{code}/schedule-interview
     */
    /** @param array<string, string> $params */
    public function store(Request $request, array $params = []): Response
    {
        $appCode = (string) ($params['code'] ?? '');
        /** @var ApplicationRepository $apps */
        $apps = $this->container->get(ApplicationRepository::class);
        $application = $apps->findByCode($appCode);
        if (!$application) throw HttpException::notFound('Application not found.');

        // Parse participants from the form
        $participantIds = [];
        $raw = $request->input('participants');
        if (is_array($raw)) {
            foreach ($raw as $v) {
                if (is_numeric($v)) $participantIds[] = (int) $v;
            }
        }

        /** @var InterviewService $service */
        $service = $this->container->get(InterviewService::class);
        $result = $service->schedule($application->id, $request->all(), $participantIds);

        /** @var Session $session */
        $session = $this->container->get(Session::class);

        if (!empty($result['errors'])) {
            /** @var View $view */
            $view = $this->container->get(View::class);
            /** @var Auth $auth */
            $auth = $this->container->get(Auth::class);
            /** @var CandidateRepository $cands */
            $cands = $this->container->get(CandidateRepository::class);
            /** @var VacancyRepository $vacs */
            $vacs = $this->container->get(VacancyRepository::class);
            /** @var UserRepository $users */
            $users = $this->container->get(UserRepository::class);

            return Response::html($view->render('interviews/form', [
                'title'       => 'Schedule Interview',
                'user'        => $auth->user(),
                'application' => $application,
                'candidate'   => $cands->findById($application->candidateId),
                'vacancy'     => $vacs->findById($application->vacancyId),
                'staffUsers'  => $users->listForAssignment(),
                'old'         => $request->all(),
                'errors'      => $result['errors'],
            ], 'layouts/app'));
        }

        $created = $result['interview'];
        $code = $created !== null ? $created->code : '';
        $session->flash('success', 'Interview scheduled.');
        return Response::redirect('/interviews/' . $code);
    }

    /**
     * GET /interviews/{code}
     */
    /** @param array<string, string> $params */
    public function show(Request $request, array $params = []): Response
    {
        $code = (string) ($params['code'] ?? '');
        /** @var InterviewRepository $repo */
        $repo = $this->container->get(InterviewRepository::class);
        $interview = $repo->findByCode($code);
        if (!$interview) throw HttpException::notFound('Interview not found.');

        /** @var ApplicationRepository $apps */
        $apps = $this->container->get(ApplicationRepository::class);
        /** @var CandidateRepository $cands */
        $cands = $this->container->get(CandidateRepository::class);
        /** @var VacancyRepository $vacs */
        $vacs = $this->container->get(VacancyRepository::class);
        /** @var ScorecardRepository $scorecards */
        $scorecards = $this->container->get(ScorecardRepository::class);
        /** @var UserRepository $users */
        $users = $this->container->get(UserRepository::class);

        $application = $apps->findById($interview->applicationId);
        $candidate   = $application !== null ? $cands->findById($application->candidateId) : null;
        $vacancy     = $application !== null ? $vacs->findById($application->vacancyId) : null;

        // Participants with names
        $participantRows = [];
        foreach ($repo->participantsFor($interview->id) as $p) {
            $u = $users->findById($p->userId);
            if ($u !== null) {
                $participantRows[] = ['participant' => $p, 'user' => $u];
            }
        }

        // Scorecards (all, but respect the "only submitted ones visible to others" rule in the view)
        $allScorecards = $scorecards->forInterview($interview->id);
        $scorecardData = [];
        foreach ($allScorecards as $sc) {
            $scUser = $users->findById($sc->interviewerId);
            $scorecardData[] = [
                'scorecard' => $sc,
                'user'      => $scUser,
                'attributes' => $scorecards->attributesFor($sc->id),
            ];
        }

        /** @var Auth $auth */
        $auth = $this->container->get(Auth::class);
        /** @var View $view */
        $view = $this->container->get(View::class);

        return Response::html($view->render('interviews/show', [
            'title'          => 'Interview ' . $interview->code,
            'user'           => $auth->user(),
            'interview'      => $interview,
            'application'    => $application,
            'candidate'      => $candidate,
            'vacancy'        => $vacancy,
            'participants'   => $participantRows,
            'scorecards'     => $scorecardData,
        ], 'layouts/app'));
    }

    /**
     * POST /interviews/{code}/complete
     */
    /** @param array<string, string> $params */
    public function complete(Request $request, array $params = []): Response
    {
        $code = (string) ($params['code'] ?? '');
        /** @var InterviewRepository $repo */
        $repo = $this->container->get(InterviewRepository::class);
        $interview = $repo->findByCode($code);
        if (!$interview) throw HttpException::notFound();

        /** @var InterviewService $service */
        $service = $this->container->get(InterviewService::class);
        $service->complete($interview->id);

        /** @var Session $session */
        $session = $this->container->get(Session::class);
        $session->flash('success', 'Interview marked as completed.');
        return Response::redirect('/interviews/' . $code);
    }

    /**
     * POST /interviews/{code}/cancel
     */
    /** @param array<string, string> $params */
    public function cancel(Request $request, array $params = []): Response
    {
        $code = (string) ($params['code'] ?? '');
        /** @var InterviewRepository $repo */
        $repo = $this->container->get(InterviewRepository::class);
        $interview = $repo->findByCode($code);
        if (!$interview) throw HttpException::notFound();

        /** @var InterviewService $service */
        $service = $this->container->get(InterviewService::class);
        $service->cancel($interview->id);

        /** @var Session $session */
        $session = $this->container->get(Session::class);
        $session->flash('success', 'Interview cancelled.');
        return Response::redirect('/interviews/' . $code);
    }
}

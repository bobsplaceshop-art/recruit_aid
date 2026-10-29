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
use CorporateAid\Recruit\Repositories\InterviewRepository;
use CorporateAid\Recruit\Repositories\ScorecardRepository;
use CorporateAid\Recruit\Services\InterviewService;

final class ScorecardController
{
    public function __construct(private Container $container) {}

    /**
     * GET /interviews/{code}/scorecard
     * Show the scorecard form for the current user (must be a participant).
     */
    /** @param array<string, string> $params */
    public function form(Request $request, array $params = []): Response
    {
        $code = (string) ($params['code'] ?? '');
        /** @var Auth $auth */
        $auth = $this->container->get(Auth::class);
        $user = $auth->user();
        if ($user === null) throw HttpException::forbidden();

        /** @var InterviewRepository $repo */
        $repo = $this->container->get(InterviewRepository::class);
        $interview = $repo->findByCode($code);
        if (!$interview) throw HttpException::notFound();

        if (!$repo->isParticipant($interview->id, $user->id)) {
            throw HttpException::forbidden('You are not a participant in this interview.');
        }

        /** @var ScorecardRepository $scorecards */
        $scorecards = $this->container->get(ScorecardRepository::class);
        $existing = $scorecards->findByInterviewAndUser($interview->id, $user->id);
        $attributes = $existing !== null ? $scorecards->attributesFor($existing->id) : [];

        /** @var View $view */
        $view = $this->container->get(View::class);

        return Response::html($view->render('interviews/scorecard-form', [
            'title'      => 'Scorecard · ' . $interview->code,
            'user'       => $user,
            'interview'  => $interview,
            'existing'   => $existing,
            'attributes' => $attributes,
            'errors'     => [],
        ], 'layouts/app'));
    }

    /**
     * POST /interviews/{code}/scorecard
     */
    /** @param array<string, string> $params */
    public function submit(Request $request, array $params = []): Response
    {
        $code = (string) ($params['code'] ?? '');
        /** @var Auth $auth */
        $auth = $this->container->get(Auth::class);
        $user = $auth->user();
        if ($user === null) throw HttpException::forbidden();

        /** @var InterviewRepository $repo */
        $repo = $this->container->get(InterviewRepository::class);
        $interview = $repo->findByCode($code);
        if (!$interview) throw HttpException::notFound();

        $recommendation = $request->inputString('recommendation') ?: null;
        $notes = $request->inputString('notes') ?: null;

        // Parse attributes: attribute[N][competency_name|score|evidence]
        $attributes = [];
        $raw = $request->input('attributes');
        if (is_array($raw)) {
            foreach ($raw as $row) {
                if (!is_array($row)) continue;
                $name = isset($row['competency_name']) && is_scalar($row['competency_name']) ? trim((string) $row['competency_name']) : '';
                $score = isset($row['score']) && is_numeric($row['score']) ? (int) $row['score'] : 0;
                $evidence = isset($row['evidence']) && is_scalar($row['evidence']) ? trim((string) $row['evidence']) : null;
                if ($name === '' || $score < 1) continue;
                $attributes[] = ['competency_name' => $name, 'score' => $score, 'evidence' => $evidence];
            }
        }

        /** @var InterviewService $service */
        $service = $this->container->get(InterviewService::class);
        $result = $service->submitScorecard($interview->id, $user->id, $recommendation, $notes, $attributes);

        /** @var Session $session */
        $session = $this->container->get(Session::class);

        if (!$result['success']) {
            /** @var ScorecardRepository $scorecards */
            $scorecards = $this->container->get(ScorecardRepository::class);
            $existing = $scorecards->findByInterviewAndUser($interview->id, $user->id);

            /** @var View $view */
            $view = $this->container->get(View::class);

            return Response::html($view->render('interviews/scorecard-form', [
                'title'      => 'Scorecard · ' . $interview->code,
                'user'       => $user,
                'interview'  => $interview,
                'existing'   => $existing,
                'attributes' => $attributes,
                'errors'     => $result['errors'],
            ], 'layouts/app'));
        }

        $session->flash('success', 'Scorecard submitted.');
        return Response::redirect('/interviews/' . $code);
    }
}

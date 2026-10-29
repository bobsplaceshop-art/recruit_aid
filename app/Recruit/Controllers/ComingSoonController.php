<?php
declare(strict_types=1);

namespace CorporateAid\Recruit\Controllers;

use CorporateAid\Core\Auth;
use CorporateAid\Core\Container;
use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Core\View;

/**
 * Renders a branded "Coming Soon" page for features that are in the
 * sidebar but not yet built. Each method maps one sidebar link to one
 * feature label + description.
 *
 * When a feature ships, replace its route with the real controller and
 * remove the method here.
 */
final class ComingSoonController
{
    public function __construct(private Container $container) {}

    /** @param array<string, string> $params */
    public function candidates(Request $r, array $params = []): Response
    {
        return $this->render('Candidates', 'The candidate database — every person you\'ve ever considered for a role, with CVs, skills, experience, and application history.');
    }

    /** @param array<string, string> $params */
    public function applications(Request $r, array $params = []): Response
    {
        return $this->render('Applications', 'The bridge between candidates and vacancies — pipeline stage tracking, hiring team notes, and rejection reasons.');
    }

    /** @param array<string, string> $params */
    public function interviews(Request $r, array $params = []): Response
    {
        return $this->render('Interviews', 'Schedule interviews, invite interviewers, collect scorecards, and make data-driven hiring decisions.');
    }

    /** @param array<string, string> $params */
    public function offers(Request $r, array $params = []): Response
    {
        return $this->render('Offers', 'Generate offer letters, track acceptance, and manage counter-offers through a built-in approval workflow.');
    }

    /** @param array<string, string> $params */
    public function tasks(Request $r, array $params = []): Response
    {
        return $this->render('Tasks', 'Assign follow-ups to recruiters and hiring managers. Track what needs doing and by when.');
    }

    /** @param array<string, string> $params */
    public function calendar(Request $r, array $params = []): Response
    {
        return $this->render('Calendar', 'A unified calendar for interviews, deadlines, and check-ins across every engagement and vacancy.');
    }

    /** @param array<string, string> $params */
    public function activity(Request $r, array $params = []): Response
    {
        return $this->render('Activity', 'An immutable timeline of every action your team takes across the platform.');
    }

    /** @param array<string, string> $params */
    public function reports(Request $r, array $params = []): Response
    {
        return $this->render('Reports', 'Funnel analytics, time-to-fill, recruiter productivity, and custom saved views.');
    }

    /** @param array<string, string> $params */
    public function documents(Request $r, array $params = []): Response
    {
        return $this->render('Documents', 'A unified document library — contracts, compliance files, and candidate attachments.');
    }

    /** @param array<string, string> $params */
    public function audit(Request $r, array $params = []): Response
    {
        return $this->render('Audit Log', 'A complete HTTP-level log of every state-changing action taken in your install.');
    }

    private function render(string $label, string $description): Response
    {
        /** @var View $view */
        $view = $this->container->get(View::class);
        /** @var Auth $auth */
        $auth = $this->container->get(Auth::class);

        $html = $view->render('coming-soon', [
            'title'        => $label,
            'user'         => $auth->user(),
            'featureLabel' => $label,
            'description'  => $description,
        ], 'layouts/app');

        return Response::html($html);
    }
}

<?php
declare(strict_types=1);

namespace CorporateAid\Recruit\Controllers;

use CorporateAid\Core\Auth;
use CorporateAid\Core\Container;
use CorporateAid\Core\Exceptions\HttpException;
use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Core\View;
use CorporateAid\Recruit\Repositories\VacancyRepository;
use CorporateAid\Recruit\Services\ReportService;

final class ReportController
{
    public function __construct(private Container $container) {}

    /** @param array<string, string> $params */
    public function index(Request $request, array $params = []): Response
    {
        /** @var View $view */
        $view = $this->container->get(View::class);
        /** @var Auth $auth */
        $auth = $this->container->get(Auth::class);

        return Response::html($view->render('reports/index', [
            'title' => 'Reports',
            'user'  => $auth->user(),
        ], 'layouts/app'));
    }

    /** @param array<string, string> $params */
    public function funnel(Request $request, array $params = []): Response
    {
        /** @var VacancyRepository $vacancies */
        $vacancies = $this->container->get(VacancyRepository::class);
        /** @var ReportService $reports */
        $reports = $this->container->get(ReportService::class);
        /** @var View $view */
        $view = $this->container->get(View::class);
        /** @var Auth $auth */
        $auth = $this->container->get(Auth::class);

        $vacancyId = $request->queryInt('vacancy_id', 0);
        $vacancy = $vacancyId > 0 ? $vacancies->findById($vacancyId) : null;
        $funnel = $vacancy !== null ? $reports->funnelByVacancy($vacancy->id) : [];

        return Response::html($view->render('reports/funnel', [
            'title'      => 'Pipeline Funnel',
            'user'       => $auth->user(),
            'vacancies'  => $vacancies->paginate([], 1, 100)['rows'],
            'selected'   => $vacancy,
            'funnel'     => $funnel,
        ], 'layouts/app'));
    }

    /** @param array<string, string> $params */
    public function timeToHire(Request $request, array $params = []): Response
    {
        /** @var ReportService $reports */
        $reports = $this->container->get(ReportService::class);
        /** @var View $view */
        $view = $this->container->get(View::class);
        /** @var Auth $auth */
        $auth = $this->container->get(Auth::class);

        return Response::html($view->render('reports/time-to-hire', [
            'title' => 'Time to Hire',
            'user'  => $auth->user(),
            'rows'  => $reports->timeToHire(),
        ], 'layouts/app'));
    }

    /** @param array<string, string> $params */
    public function productivity(Request $request, array $params = []): Response
    {
        $days = max(1, min(365, $request->queryInt('days', 30)));

        /** @var ReportService $reports */
        $reports = $this->container->get(ReportService::class);
        /** @var View $view */
        $view = $this->container->get(View::class);
        /** @var Auth $auth */
        $auth = $this->container->get(Auth::class);

        return Response::html($view->render('reports/productivity', [
            'title' => 'Recruiter Productivity',
            'user'  => $auth->user(),
            'rows'  => $reports->recruiterProductivity($days),
            'days'  => $days,
        ], 'layouts/app'));
    }

    /** @param array<string, string> $params */
    public function exportTimeToHire(Request $request, array $params = []): void
    {
        /** @var ReportService $reports */
        $reports = $this->container->get(ReportService::class);
        $rows = $reports->timeToHire();

        $reports->streamCsv($rows, [
            'vacancy_code'  => 'Vacancy Code',
            'vacancy_title' => 'Vacancy Title',
            'hired_count'   => 'Hired',
            'avg_days'      => 'Avg Days to Hire',
        ], 'time-to-hire-' . date('Y-m-d') . '.csv');
    }
}

<?php
declare(strict_types=1);

namespace CorporateAid\Recruit\Controllers;

use CorporateAid\Core\Container;
use CorporateAid\Core\Exceptions\HttpException;
use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Core\View;
use CorporateAid\Recruit\Repositories\VacancyRepository;

final class PublicJobController
{
    public function __construct(private Container $container) {}

    /** @param array<string, string> $params */
    public function index(Request $request, array $params = []): Response
    {
        /** @var View $view */
        $view = $this->container->get(View::class);
        /** @var VacancyRepository $vacancies */
        $vacancies = $this->container->get(VacancyRepository::class);

        $page = max(1, $request->queryInt('page', 1));

        $result = $vacancies->paginate([
            'is_public'     => true,
            'status_not_in' => ['closed', 'cancelled', 'filled', 'draft', 'on_hold'],
        ], $page, 20);

        return Response::html($view->render('public/jobs/index', [
            'title'       => 'Open Roles',
            'rows'        => $result['rows'],
            'total'       => $result['total'],
            'page'        => $result['page'],
            'perPage'     => $result['per_page'],
            'totalPages'  => $result['total_pages'],
        ], 'layouts/public'));
    }

    /** @param array<string, string> $params */
    public function show(Request $request, array $params = []): Response
    {
        $code = (string) ($params['code'] ?? '');
        if ($code === '') {
            throw HttpException::notFound();
        }

        /** @var VacancyRepository $vacancies */
        $vacancies = $this->container->get(VacancyRepository::class);
        $vacancy = $vacancies->findByCode($code);

        // Public pages must not expose internal-only vacancies. Return 404
        // (not 403) so the response does not confirm the vacancy exists.
        if ($vacancy === null || !$vacancy->isPublic || !$vacancy->isOpen()) {
            throw HttpException::notFound();
        }

        /** @var View $view */
        $view = $this->container->get(View::class);

        return Response::html($view->render('public/jobs/show', [
            'title'   => $vacancy->title,
            'vacancy' => $vacancy,
        ], 'layouts/public'));
    }
}

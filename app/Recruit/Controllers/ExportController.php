<?php
declare(strict_types=1);

namespace CorporateAid\Recruit\Controllers;

use CorporateAid\Core\Auth;
use CorporateAid\Core\Container;
use CorporateAid\Core\Exceptions\HttpException;
use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Platform\Export\CsvExporter;
use CorporateAid\Recruit\Repositories\CandidateRepository;
use CorporateAid\Recruit\Repositories\EngagementRepository;
use CorporateAid\Recruit\Repositories\OrganisationRepository;
use CorporateAid\Recruit\Repositories\VacancyRepository;

final class ExportController
{
    public function __construct(private Container $container) {}

    /** @param array<string, string> $params */
    public function export(Request $request, array $params = []): Response
    {
        $entity = strtolower((string) ($params['entity'] ?? ''));
        $registry = $this->registry();
        if (!isset($registry[$entity])) {
            throw HttpException::notFound('No export available for this entity.');
        }

        $config = $registry[$entity];

        /** @var Auth $auth */
        $auth = $this->container->get(Auth::class);
        $user = $auth->user();
        if ($user === null || !$user->can($config['permission'])) {
            throw HttpException::forbidden();
        }

        /** @var CsvExporter $exporter */
        $exporter = $this->container->get(CsvExporter::class);

        /**
         * @var OrganisationRepository|EngagementRepository|VacancyRepository|CandidateRepository $repo
         */
        $repo = $this->container->get($config['repository']);

        $filters = $this->buildFilters($request);

        $result = $repo->paginate($filters, 1, 10000);
        $rows = $result['rows'];

        return $exporter->export($entity, $rows, $config['columns']);
    }

    /**
     * @return array{search?: string, status?: string}
     */
    private function buildFilters(Request $request): array
    {
        $filters = [];

        $q = trim($request->queryStringValue('q', ''));
        if ($q !== '') $filters['search'] = $q;

        $status = trim($request->queryStringValue('status', ''));
        if ($status !== '' && $status !== 'all') $filters['status'] = $status;

        return $filters;
    }

    /**
     * @return array<string, array{
     *   repository: class-string,
     *   permission: string,
     *   columns: array<string, string>
     * }>
     */
    private function registry(): array
    {
        return [
            'organisations' => [
                'repository' => OrganisationRepository::class,
                'permission' => 'recruit.organisations.view',
                'columns'    => [
                    'code'         => 'Code',
                    'name'         => 'Name',
                    'legal_name'   => 'Legal Name',
                    'industry'     => 'Industry',
                    'country_code' => 'Country',
                    'email'        => 'Email',
                    'phone'        => 'Phone',
                    'status'       => 'Status',
                    'created_at'   => 'Created At',
                ],
            ],
            'engagements' => [
                'repository' => EngagementRepository::class,
                'permission' => 'recruit.engagements.view',
                'columns'    => [
                    'code'                => 'Code',
                    'name'                => 'Name',
                    'service_type'        => 'Service Type',
                    'status'              => 'Status',
                    'start_date'          => 'Start Date',
                    'end_date'            => 'End Date',
                    'reporting_frequency' => 'Reporting',
                ],
            ],
            'vacancies' => [
                'repository' => VacancyRepository::class,
                'permission' => 'recruit.vacancies.view',
                'columns'    => [
                    'code'            => 'Code',
                    'title'           => 'Title',
                    'department'      => 'Department',
                    'country_code'    => 'Country',
                    'city'            => 'City',
                    'work_mode'       => 'Work Mode',
                    'employment_type' => 'Employment Type',
                    'seniority'       => 'Seniority',
                    'status'          => 'Status',
                    'opening_date'    => 'Opening Date',
                ],
            ],
            'candidates' => [
                'repository' => CandidateRepository::class,
                'permission' => 'recruit.candidates.view',
                'columns'    => [
                    'code'              => 'Code',
                    'first_name'        => 'First Name',
                    'last_name'         => 'Last Name',
                    'email'             => 'Email',
                    'phone'             => 'Phone',
                    'current_job_title' => 'Current Role',
                    'current_company'   => 'Current Company',
                    'seniority'         => 'Seniority',
                    'status'            => 'Status',
                ],
            ],
        ];
    }
}

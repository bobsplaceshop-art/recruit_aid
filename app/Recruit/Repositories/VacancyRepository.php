<?php
declare(strict_types=1);

namespace CorporateAid\Recruit\Repositories;

use CorporateAid\Core\Database;
use CorporateAid\Core\PDOParams;
use CorporateAid\Core\Row;
use CorporateAid\Core\TenantContext;
use CorporateAid\Platform\Support\CodeGenerator;
use CorporateAid\Recruit\Models\Vacancy;
use CorporateAid\Recruit\Models\VacancyRequirement;

final class VacancyRepository implements \CorporateAid\Platform\LiveSearch\LiveSearchable
{
    public function __construct(
        private Database $db,
        private TenantContext $tenant,
        private CodeGenerator $codes
    ) {}

    private function tenantId(): int
    {
        $tenantId = $this->tenant->id();

        if ($tenantId <= 0) {
            throw new \RuntimeException('An active tenant is required.');
        }

        return $tenantId;
    }

    private function positiveId(mixed $value, string $field): int
    {
        if (is_int($value) && $value > 0) {
            return $value;
        }

        if (is_string($value) && $value !== '' && ctype_digit($value)) {
            $id = filter_var($value, FILTER_VALIDATE_INT);

            if ($id !== false && $id > 0) {
                return $id;
            }
        }

        throw new \InvalidArgumentException("Invalid {$field} supplied.");
    }

    private function nullableId(mixed $value, string $field): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $this->positiveId($value, $field);
    }

    private function assertTenantOrganisation(int $organisationId): void
    {
        $tenantId = $this->tenantId();

        $exists = $this->db->scalarInt(
            "SELECT COUNT(*) FROM `recruit_client_organisations`
             WHERE `id` = ?
               AND `tenant_id` = ?
               AND `deleted_at` IS NULL",
            PDOParams::positional([$organisationId, $tenantId])
        );

        if ($exists !== 1) {
            throw new \InvalidArgumentException(
                'The selected client organisation is unavailable.'
            );
        }
    }

    private function assertTenantEngagement(
        int $engagementId,
        int $organisationId
    ): void {
        $tenantId = $this->tenantId();

        $exists = $this->db->scalarInt(
            "SELECT COUNT(*) FROM `recruit_engagements`
             WHERE `id` = ?
               AND `client_organisation_id` = ?
               AND `tenant_id` = ?
               AND `deleted_at` IS NULL",
            PDOParams::positional([$engagementId, $organisationId, $tenantId])
        );

        if ($exists !== 1) {
            throw new \InvalidArgumentException(
                'The selected engagement does not belong to this client organisation and tenant.'
            );
        }
    }

    private function assertTenantRecruiter(int $userId): void
    {
        $tenantId = $this->tenantId();

        $exists = $this->db->scalarInt(
            "SELECT COUNT(*) FROM `core_users`
             WHERE `id` = ?
               AND `tenant_id` = ?
               AND `status` = 'active'
               AND `deleted_at` IS NULL",
            PDOParams::positional([$userId, $tenantId])
        );

        if ($exists !== 1) {
            throw new \InvalidArgumentException(
                'The selected recruiter is not an active user in this tenant.'
            );
        }
    }

    private function assertTenantContact(
        int $contactId,
        int $organisationId
    ): void {
        $tenantId = $this->tenantId();

        $exists = $this->db->scalarInt(
            "SELECT COUNT(*) FROM `recruit_client_contacts`
             WHERE `id` = ?
               AND `client_organisation_id` = ?
               AND `tenant_id` = ?
               AND `deleted_at` IS NULL",
            PDOParams::positional([$contactId, $organisationId, $tenantId])
        );

        if ($exists !== 1) {
            throw new \InvalidArgumentException(
                'The selected hiring manager does not belong to this client organisation and tenant.'
            );
        }
    }

    private function assertTenantUser(int $userId): void
    {
        $this->assertTenantRecruiter($userId);
    }

    private function assertTenantVacancy(int $vacancyId): void
    {
        $tenantId = $this->tenantId();

        $exists = $this->db->scalarInt(
            "SELECT COUNT(*) FROM `recruit_vacancies`
             WHERE `id` = ?
               AND `tenant_id` = ?
               AND `deleted_at` IS NULL",
            PDOParams::positional([$vacancyId, $tenantId])
        );

        if ($exists !== 1) {
            throw new \InvalidArgumentException(
                'The vacancy is unavailable in the active tenant.'
            );
        }
    }

    public function findById(int $id): ?Vacancy
    {
        $row = $this->db->selectOne(
            "SELECT * FROM `recruit_vacancies`
             WHERE `id` = ? AND `tenant_id` = ?
             LIMIT 1",
            PDOParams::positional([$id, $this->tenantId()])
        );

        return $row ? Vacancy::fromRow($row) : null;
    }

    public function findByCode(string $code, bool $includeDeleted = false): ?Vacancy
    {
        $sql = "SELECT * FROM `recruit_vacancies`
                WHERE `code` = ? AND `tenant_id` = ?";

        if (!$includeDeleted) {
            $sql .= " AND `deleted_at` IS NULL";
        }

        $sql .= " LIMIT 1";

        $row = $this->db->selectOne(
            $sql,
            PDOParams::positional([$code, $this->tenantId()])
        );

        return $row ? Vacancy::fromRow($row) : null;
    }

    /**
     * @param array{search?:string,status?:string,include_deleted?:bool,client_organisation_id?:int,engagement_id?:int,recruiter_id?:int} $filters
     * @return array{rows:Vacancy[], total:int, page:int, per_page:int, total_pages:int}
     */
    public function paginate(array $filters = [], int $page = 1, int $perPage = 25): array
    {
        $where = ['`tenant_id` = ?'];
        $params = [$this->tenantId()];

        if (empty($filters['include_deleted'])) {
            $where[] = "`deleted_at` IS NULL";
        }

        if (!empty($filters['search'])) {
            $where[] = "(`title` LIKE ? OR `code` LIKE ? OR `city` LIKE ?)";
            $term = '%' . $filters['search'] . '%';
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }

        if (!empty($filters['status'])) {
            $where[] = "`status` = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['client_organisation_id'])) {
            $where[] = "`client_organisation_id` = ?";
            $params[] = (int) $filters['client_organisation_id'];
        }

        if (!empty($filters['engagement_id'])) {
            $where[] = "`engagement_id` = ?";
            $params[] = (int) $filters['engagement_id'];
        }

        if (!empty($filters['recruiter_id'])) {
            $where[] = "`recruiter_id` = ?";
            $params[] = (int) $filters['recruiter_id'];
        }

        $whereSql = 'WHERE ' . implode(' AND ', $where);
        $paramObj = PDOParams::positional($params);

        $total = $this->db->scalarInt(
            "SELECT COUNT(*) FROM `recruit_vacancies` {$whereSql}",
            $paramObj
        );

        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $totalPages = max(1, (int) ceil($total / $perPage));

        if ($page > $totalPages) {
            $page = $totalPages;
        }

        $offset = ($page - 1) * $perPage;

        $rows = $this->db->select(
            "SELECT * FROM `recruit_vacancies` {$whereSql}
             ORDER BY `created_at` DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $paramObj
        );

        return [
            'rows'        => array_map([Vacancy::class, 'fromRow'], $rows),
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => $totalPages,
        ];
    }

    /** @return Vacancy[] */
    public function forOrganisation(int $clientOrganisationId, bool $includeDeleted = false): array
    {
        $sql = "SELECT * FROM `recruit_vacancies`
                WHERE `client_organisation_id` = ? AND `tenant_id` = ?";

        if (!$includeDeleted) {
            $sql .= " AND `deleted_at` IS NULL";
        }

        $sql .= " ORDER BY `created_at` DESC";

        $rows = $this->db->select(
            $sql,
            PDOParams::positional([$clientOrganisationId, $this->tenantId()])
        );

        return array_map([Vacancy::class, 'fromRow'], $rows);
    }

    /** @return Vacancy[] */
    public function forEngagement(int $engagementId): array
    {
        $rows = $this->db->select(
            "SELECT * FROM `recruit_vacancies`
             WHERE `engagement_id` = ?
               AND `tenant_id` = ?
               AND `deleted_at` IS NULL
             ORDER BY `created_at` DESC",
            PDOParams::positional([$engagementId, $this->tenantId()])
        );

        return array_map([Vacancy::class, 'fromRow'], $rows);
    }

    public function activeCountForEngagement(int $engagementId): int
    {
        return $this->db->scalarInt(
            "SELECT COUNT(*) FROM `recruit_vacancies`
             WHERE `engagement_id` = ?
               AND `tenant_id` = ?
               AND `status` NOT IN ('draft','cancelled','closed')
               AND `deleted_at` IS NULL",
            PDOParams::positional([$engagementId, $this->tenantId()])
        );
    }

    /** @return array<string, int> */
    public function countsByStatus(): array
    {
        $rows = $this->db->select(
            "SELECT `status`, COUNT(*) AS n FROM `recruit_vacancies`
             WHERE `tenant_id` = ? AND `deleted_at` IS NULL
             GROUP BY `status`",
            PDOParams::positional([$this->tenantId()])
        );

        $counts = [
            'all' => 0, 'draft' => 0, 'pending_approval' => 0, 'open' => 0,
            'sourcing' => 0, 'screening' => 0, 'shortlisting' => 0, 'client_review' => 0,
            'interviewing' => 0, 'reference_check' => 0, 'offer' => 0,
            'filled' => 0, 'on_hold' => 0, 'cancelled' => 0, 'closed' => 0,
        ];

        foreach ($rows as $row) {
            $status = Row::str($row, 'status');
            $count = Row::int($row, 'n');
            $counts[$status] = $count;
            $counts['all'] += $count;
        }

        return $counts;
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $tenantId = $this->tenantId();

        $organisationId = $this->positiveId(
            $data['client_organisation_id'] ?? null,
            'client organisation ID'
        );

        $engagementId = $this->nullableId(
            $data['engagement_id'] ?? null,
            'engagement ID'
        );

        $recruiterId = $this->nullableId(
            $data['recruiter_id'] ?? null,
            'recruiter ID'
        );

        $hiringManagerId = $this->nullableId(
            $data['hiring_manager_contact_id'] ?? null,
            'hiring manager contact ID'
        );

        $createdBy = $this->nullableId(
            $data['created_by'] ?? null,
            'creator ID'
        );

        $this->assertTenantOrganisation($organisationId);

        if ($engagementId !== null) {
            $this->assertTenantEngagement($engagementId, $organisationId);
        }

        if ($recruiterId !== null) {
            $this->assertTenantRecruiter($recruiterId);
        }

        if ($hiringManagerId !== null) {
            $this->assertTenantContact($hiringManagerId, $organisationId);
        }

        if ($createdBy !== null) {
            $this->assertTenantUser($createdBy);
        }

        $sql = "INSERT INTO `recruit_vacancies`
            (`tenant_id`, `code`, `client_organisation_id`, `engagement_id`, `title`, `department`,
             `country_code`, `city`, `work_mode`, `employment_type`, `seniority`,
             `positions_count`, `opening_date`, `target_hire_date`,
             `recruiter_id`, `hiring_manager_contact_id`,
             `salary_min`, `salary_max`, `currency`,
             `description`, `responsibilities`, `requirements`, `preferred_qualifications`,
             `skills`, `languages`, `assessment_criteria`,
             `internal_notes`, `client_notes`, `status`,
             `is_public`, `created_by`)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        return $this->db->insert($sql, PDOParams::positional([
            $tenantId,
            $data['code'],
            $organisationId,
            $engagementId,
            $data['title'],
            $data['department'] ?? null,
            $data['country_code'] ?? null,
            $data['city'] ?? null,
            $data['work_mode'] ?? 'onsite',
            $data['employment_type'] ?? 'permanent',
            $data['seniority'] ?? 'mid',
            $data['positions_count'] ?? 1,
            $data['opening_date'] ?? null,
            $data['target_hire_date'] ?? null,
            $recruiterId,
            $hiringManagerId,
            $data['salary_min'] ?? null,
            $data['salary_max'] ?? null,
            $data['currency'] ?? null,
            $data['description'] ?? null,
            $data['responsibilities'] ?? null,
            $data['requirements'] ?? null,
            $data['preferred_qualifications'] ?? null,
            !empty($data['skills']) ? json_encode($data['skills']) : null,
            !empty($data['languages']) ? json_encode($data['languages']) : null,
            $data['assessment_criteria'] ?? null,
            $data['internal_notes'] ?? null,
            $data['client_notes'] ?? null,
            $data['status'] ?? 'draft',
            !empty($data['is_public']) ? 1 : 0,
            $createdBy,
        ]));
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, array{old:mixed,new:mixed}>
     */
    public function update(int $id, array $data): array
    {
        $tenantId = $this->tenantId();
        $existing = $this->findById($id);

        if (!$existing || $existing->isDeleted()) {
            throw new \RuntimeException('Vacancy not found.');
        }

        $organisationId = $existing->clientOrganisationId;

        $engagementId = array_key_exists('engagement_id', $data)
            ? $this->nullableId($data['engagement_id'], 'engagement ID')
            : $existing->engagementId;

        $recruiterId = array_key_exists('recruiter_id', $data)
            ? $this->nullableId($data['recruiter_id'], 'recruiter ID')
            : $existing->recruiterId;

        $hiringManagerId = array_key_exists('hiring_manager_contact_id', $data)
            ? $this->nullableId($data['hiring_manager_contact_id'], 'hiring manager contact ID')
            : $existing->hiringManagerContactId;

        if ($engagementId !== null) {
            $this->assertTenantEngagement($engagementId, $organisationId);
        }

        if ($recruiterId !== null) {
            $this->assertTenantRecruiter($recruiterId);
        }

        if ($hiringManagerId !== null) {
            $this->assertTenantContact($hiringManagerId, $organisationId);
        }

        $allowed = [
            'engagement_id', 'title', 'department', 'country_code', 'city',
            'work_mode', 'employment_type', 'seniority', 'positions_count',
            'opening_date', 'target_hire_date', 'recruiter_id', 'hiring_manager_contact_id',
            'salary_min', 'salary_max', 'currency',
            'description', 'responsibilities', 'requirements', 'preferred_qualifications',
            'skills', 'languages', 'assessment_criteria',
            'internal_notes', 'client_notes', 'status', 'is_public',
        ];

        $sets = [];
        $params = [];
        $changes = [];

        foreach ($allowed as $col) {
            if (!array_key_exists($col, $data)) {
                continue;
            }

            $newVal = $data[$col];

            $prop = match ($col) {
                'engagement_id'             => 'engagementId',
                'country_code'              => 'countryCode',
                'work_mode'                 => 'workMode',
                'employment_type'           => 'employmentType',
                'positions_count'           => 'positionsCount',
                'opening_date'              => 'openingDate',
                'target_hire_date'          => 'targetHireDate',
                'recruiter_id'              => 'recruiterId',
                'hiring_manager_contact_id' => 'hiringManagerContactId',
                'salary_min'                => 'salaryMin',
                'salary_max'                => 'salaryMax',
                'preferred_qualifications'  => 'preferredQualifications',
                'assessment_criteria'       => 'assessmentCriteria',
                'internal_notes'            => 'internalNotes',
                'client_notes'              => 'clientNotes',
                'is_public'                 => 'isPublic',
                default                     => $col,
            };

            $oldVal = $existing->$prop ?? null;
            $newForCompare = $newVal;

            if (in_array($col, ['skills', 'languages'], true)) {
                $newForCompare = !empty($newVal)
                    ? (is_array($newVal) ? $newVal : (is_string($newVal) ? json_decode($newVal, true) : []))
                    : [];

                $oldVal = $col === 'skills' ? $existing->skills : $existing->languages;
            }

            if ($col === 'is_public') {
                $newForCompare = (bool) $newVal;
            }

            if ($oldVal != $newForCompare) {
                $changes[$col] = ['old' => $oldVal, 'new' => $newForCompare];
            }

            $sets[] = "`{$col}` = ?";

            if (in_array($col, ['skills', 'languages'], true)) {
                $newVal = !empty($newVal) ? json_encode($newVal) : null;
            }

            if ($col === 'is_public') {
                $newVal = !empty($newVal) ? 1 : 0;
            }

            $params[] = $newVal;
        }

        if (empty($sets)) {
            return [];
        }

        $params[] = $id;
        $params[] = $tenantId;

        $this->db->execute(
            "UPDATE `recruit_vacancies`
             SET " . implode(', ', $sets) . "
             WHERE `id` = ?
               AND `tenant_id` = ?
               AND `deleted_at` IS NULL",
            PDOParams::positional($params)
        );

        return $changes;
    }

    public function softDelete(int $id): void
    {
        $this->db->execute(
            "UPDATE `recruit_vacancies`
             SET `deleted_at` = NOW()
             WHERE `id` = ? AND `tenant_id` = ? AND `deleted_at` IS NULL",
            PDOParams::positional([$id, $this->tenantId()])
        );
    }

    public function restore(int $id): void
    {
        $this->db->execute(
            "UPDATE `recruit_vacancies`
             SET `deleted_at` = NULL
             WHERE `id` = ? AND `tenant_id` = ? AND `deleted_at` IS NOT NULL",
            PDOParams::positional([$id, $this->tenantId()])
        );
    }

    public function nextCode(): string
    {
        return $this->codes->nextForTenant(
            'rec_vacancy_code',
            'VAC',
            $this->tenantId()
        );
    }

    /** @return VacancyRequirement[] */
    public function requirementsFor(int $vacancyId, ?string $type = null): array
    {
        $sql = "SELECT * FROM `recruit_vacancy_requirements`
                WHERE `vacancy_id` = ? AND `tenant_id` = ?";

        $params = [$vacancyId, $this->tenantId()];

        if ($type !== null) {
            $sql .= " AND `type` = ?";
            $params[] = $type;
        }

        $sql .= " ORDER BY `sort_order` ASC, `id` ASC";

        $rows = $this->db->select($sql, PDOParams::positional($params));

        return array_map([VacancyRequirement::class, 'fromRow'], $rows);
    }

    /** @param array<string, mixed> $items */
    public function syncRequirements(int $vacancyId, array $items): void
    {
        $this->assertTenantVacancy($vacancyId);

        $validTypes = ['responsibility', 'requirement', 'preferred', 'skill', 'language'];
        $normalized = [];

        foreach ($validTypes as $type) {
            $values = $items[$type] ?? [];

            if (!is_array($values)) {
                continue;
            }

            foreach ($values as $value) {
                $valueStr = is_scalar($value) ? trim((string) $value) : '';

                if ($valueStr !== '') {
                    $normalized[$type][] = $valueStr;
                }
            }
        }

        $tenantId = $this->tenantId();

        $this->db->execute(
            "DELETE FROM `recruit_vacancy_requirements`
             WHERE `vacancy_id` = ? AND `tenant_id` = ?",
            PDOParams::positional([$vacancyId, $tenantId])
        );

        $order = 0;

        foreach ($validTypes as $type) {
            foreach ($normalized[$type] ?? [] as $value) {
                $this->db->execute(
                    "INSERT INTO `recruit_vacancy_requirements`
                     (`tenant_id`, `vacancy_id`, `type`, `value`, `sort_order`)
                     VALUES (?, ?, ?, ?, ?)",
                    PDOParams::positional([$tenantId, $vacancyId, $type, $value, $order])
                );

                $order++;
            }
        }
    }

    public function liveSearchConfig(): array
    {
        return [
            'table'            => 'recruit_vacancies',
            'alias'            => 'v',
            'columns'          => ['v.title', 'v.code', 'v.city', 'v.department'],
            'order'            => 'v.created_at DESC',
            'permission'       => 'recruit.vacancies.view',
            'baseFilters'      => ['v.`deleted_at` IS NULL'],
            'joins'            => [
                'INNER JOIN `recruit_client_organisations` o
                 ON o.`id` = v.`client_organisation_id`
                 AND o.`tenant_id` = v.`tenant_id`',
            ],
            'rowView'          => 'vacancies/_row',
            'extraColumns'     => ['o.name AS organisation_name', 'o.code AS organisation_code'],
            'aggregateColumns' => [],
            'filterable'       => ['status' => 'v.`status` = ?'],
        ];
    }
}

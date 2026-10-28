<?php
declare(strict_types=1);

namespace CorporateAid\Recruit\Repositories;

use CorporateAid\Core\Database;
use CorporateAid\Core\PDOParams;
use CorporateAid\Core\Row;
use CorporateAid\Recruit\Models\Vacancy;
use CorporateAid\Recruit\Models\VacancyRequirement;

final class VacancyRepository
{
    public function __construct(private Database $db) {}

    public function findById(int $id): ?Vacancy
    {
        $row = $this->db->selectOne(
            "SELECT * FROM `recruit_vacancies` WHERE `id` = ? LIMIT 1",
            PDOParams::positional([$id])
        );
        return $row ? Vacancy::fromRow($row) : null;
    }

    public function findByCode(string $code, bool $includeDeleted = false): ?Vacancy
    {
        $sql = "SELECT * FROM `recruit_vacancies` WHERE `code` = ?";
        if (!$includeDeleted) $sql .= " AND `deleted_at` IS NULL";
        $sql .= " LIMIT 1";

        $row = $this->db->selectOne($sql, PDOParams::positional([$code]));
        return $row ? Vacancy::fromRow($row) : null;
    }

    /**
     * @param array{search?:string,status?:string,include_deleted?:bool,organisation_id?:int,engagement_id?:int,recruiter_id?:int} $filters
     * @return array{rows:Vacancy[], total:int, page:int, per_page:int, total_pages:int}
     */
    public function paginate(array $filters = [], int $page = 1, int $perPage = 25): array
    {
        $where = [];
        $params = [];

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

        if (!empty($filters['organisation_id'])) {
            $where[] = "`organisation_id` = ?";
            $params[] = (int) $filters['organisation_id'];
        }

        if (!empty($filters['engagement_id'])) {
            $where[] = "`engagement_id` = ?";
            $params[] = (int) $filters['engagement_id'];
        }

        if (!empty($filters['recruiter_id'])) {
            $where[] = "`recruiter_id` = ?";
            $params[] = (int) $filters['recruiter_id'];
        }

        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
        $paramObj = PDOParams::positional($params);

        $total = $this->db->scalarInt(
            "SELECT COUNT(*) FROM `recruit_vacancies` {$whereSql}",
            $paramObj
        );

        $page    = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $totalPages = max(1, (int) ceil($total / $perPage));
        if ($page > $totalPages) $page = $totalPages;
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
    public function forOrganisation(int $organisationId, bool $includeDeleted = false): array
    {
        $sql = "SELECT * FROM `recruit_vacancies` WHERE `organisation_id` = ?";
        if (!$includeDeleted) $sql .= " AND `deleted_at` IS NULL";
        $sql .= " ORDER BY `created_at` DESC";

        $rows = $this->db->select($sql, PDOParams::positional([$organisationId]));
        return array_map([Vacancy::class, 'fromRow'], $rows);
    }

    /** @return Vacancy[] */
    public function forEngagement(int $engagementId): array
    {
        $rows = $this->db->select(
            "SELECT * FROM `recruit_vacancies`
             WHERE `engagement_id` = ? AND `deleted_at` IS NULL
             ORDER BY `created_at` DESC",
            PDOParams::positional([$engagementId])
        );
        return array_map([Vacancy::class, 'fromRow'], $rows);
    }

    public function activeCountForEngagement(int $engagementId): int
    {
        return $this->db->scalarInt(
            "SELECT COUNT(*) FROM `recruit_vacancies`
             WHERE `engagement_id` = ?
               AND `status` NOT IN ('draft','cancelled','closed')
               AND `deleted_at` IS NULL",
            PDOParams::positional([$engagementId])
        );
    }

    /** @return array<string, int> */
    public function countsByStatus(): array
    {
        $rows = $this->db->select(
            "SELECT `status`, COUNT(*) AS n FROM `recruit_vacancies`
             WHERE `deleted_at` IS NULL
             GROUP BY `status`"
        );
        $counts = [
            'all' => 0, 'draft' => 0, 'pending_approval' => 0, 'open' => 0,
            'sourcing' => 0, 'screening' => 0, 'shortlisting' => 0, 'client_review' => 0,
            'interviewing' => 0, 'reference_check' => 0, 'offer' => 0,
            'filled' => 0, 'on_hold' => 0, 'cancelled' => 0, 'closed' => 0,
        ];
        foreach ($rows as $row) {
            $s = Row::str($row, 'status');
            $n = Row::int($row, 'n');
            $counts[$s] = $n;
            $counts['all'] += $n;
        }
        return $counts;
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $sql = "INSERT INTO `recruit_vacancies`
            (`code`, `organisation_id`, `engagement_id`, `title`, `department`,
             `country_code`, `city`, `work_mode`, `employment_type`, `seniority`,
             `positions_count`, `opening_date`, `target_hire_date`,
             `recruiter_id`, `hiring_manager_contact_id`,
             `salary_min`, `salary_max`, `currency`,
             `description`, `responsibilities`, `requirements`, `preferred_qualifications`,
             `skills`, `languages`, `assessment_criteria`,
             `internal_notes`, `client_notes`, `status`,
             `is_public`, `created_by`)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        return $this->db->insert($sql, PDOParams::positional([
            $data['code'],
            $data['organisation_id'],
            $data['engagement_id'] ?? null,
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
            $data['recruiter_id'] ?? null,
            $data['hiring_manager_contact_id'] ?? null,
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
            $data['created_by'] ?? null,
        ]));
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, array{old:mixed,new:mixed}>
     */
    public function update(int $id, array $data): array
    {
        $existing = $this->findById($id);
        if (!$existing) throw new \RuntimeException('Vacancy not found.');

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
            if (!array_key_exists($col, $data)) continue;

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
                $newForCompare = !empty($newVal) ? (is_array($newVal) ? $newVal : (is_string($newVal) ? json_decode($newVal, true) : [])) : [];
                $oldVal = $col === 'skills' ? $existing->skills : $existing->languages;
            }
            if ($col === 'is_public') $newForCompare = (bool) $newVal;

            if ($oldVal != $newForCompare) {
                $changes[$col] = ['old' => $oldVal, 'new' => $newForCompare];
            }

            $sets[] = "`{$col}` = ?";
            if (in_array($col, ['skills', 'languages'], true)) {
                $newVal = !empty($newVal) ? json_encode($newVal) : null;
            }
            if ($col === 'is_public') $newVal = !empty($newVal) ? 1 : 0;
            $params[] = $newVal;
        }

        if (empty($sets)) return [];

        $params[] = $id;
        $this->db->execute(
            "UPDATE `recruit_vacancies` SET " . implode(', ', $sets) . " WHERE `id` = ?",
            PDOParams::positional($params)
        );

        return $changes;
    }

    public function softDelete(int $id): void
    {
        $this->db->execute(
            "UPDATE `recruit_vacancies` SET `deleted_at` = NOW() WHERE `id` = ?",
            PDOParams::positional([$id])
        );
    }

    public function restore(int $id): void
    {
        $this->db->execute(
            "UPDATE `recruit_vacancies` SET `deleted_at` = NULL WHERE `id` = ?",
            PDOParams::positional([$id])
        );
    }

    public function nextCode(): string
    {
        $this->db->execute(
            "UPDATE `core_sequence_counters` SET `last_value` = `last_value` + 1 WHERE `name` = 'rec_vacancy_code'"
        );
        $num = $this->db->scalarInt(
            "SELECT `last_value` FROM `core_sequence_counters` WHERE `name` = 'rec_vacancy_code'"
        );
        return 'VAC-' . str_pad((string) $num, 6, '0', STR_PAD_LEFT);
    }

    /** @return VacancyRequirement[] */
    public function requirementsFor(int $vacancyId, ?string $type = null): array
    {
        $sql = "SELECT * FROM `recruit_vacancy_requirements` WHERE `vacancy_id` = ?";
        $params = [$vacancyId];
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
        $validTypes = ['responsibility','requirement','preferred','skill','language'];

        $this->db->execute(
            "DELETE FROM `recruit_vacancy_requirements` WHERE `vacancy_id` = ?",
            PDOParams::positional([$vacancyId])
        );

        $order = 0;
        foreach ($validTypes as $type) {
            if (empty($items[$type]) || !is_array($items[$type])) continue;
            foreach ($items[$type] as $value) {
                $valueStr = is_scalar($value) ? trim((string) $value) : '';
                if ($valueStr === '') continue;
                $this->db->execute(
                    "INSERT INTO `recruit_vacancy_requirements` (`vacancy_id`, `type`, `value`, `sort_order`)
                     VALUES (?, ?, ?, ?)",
                    PDOParams::positional([$vacancyId, $type, $valueStr, $order])
                );
                $order++;
            }
        }
    }
}

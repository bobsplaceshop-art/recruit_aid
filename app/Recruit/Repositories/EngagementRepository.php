<?php
declare(strict_types=1);

namespace CorporateAid\Recruit\Repositories;

use CorporateAid\Core\Database;
use CorporateAid\Core\PDOParams;
use CorporateAid\Core\Row;
use CorporateAid\Core\TenantContext;
use CorporateAid\Recruit\Models\Engagement;

final class EngagementRepository implements \CorporateAid\Platform\LiveSearch\LiveSearchable
{
    public function __construct(
        private Database $db,
        private TenantContext $tenant
    ) {}

    public function findById(int $id): ?Engagement
    {
        $row = $this->db->selectOne(
            "SELECT * FROM `recruit_engagements` WHERE `id` = ? AND `tenant_id` = ? LIMIT 1",
            PDOParams::positional([$id, $this->tenant->id()])
        );
        return $row ? Engagement::fromRow($row) : null;
    }

    public function findByCode(string $code, bool $includeDeleted = false): ?Engagement
    {
        $sql = "SELECT * FROM `recruit_engagements` WHERE `code` = ? AND `tenant_id` = ?";
        if (!$includeDeleted) {
            $sql .= " AND `deleted_at` IS NULL";
        }
        $sql .= " LIMIT 1";

        $row = $this->db->selectOne($sql, PDOParams::positional([$code, $this->tenant->id()]));
        return $row ? Engagement::fromRow($row) : null;
    }

    /**
     * @param array{search?:string,status?:string,include_deleted?:bool,client_organisation_id?:int} $filters
     * @return array{rows:Engagement[], total:int, page:int, per_page:int, total_pages:int}
     */
    public function paginate(array $filters = [], int $page = 1, int $perPage = 25): array
    {
        $where = ['`tenant_id` = ?'];
        $params = [$this->tenant->id()];

        if (empty($filters['include_deleted'])) {
            $where[] = "`deleted_at` IS NULL";
        }

        if (!empty($filters['search'])) {
            $where[] = "(`name` LIKE ? OR `code` LIKE ?)";
            $term = '%' . $filters['search'] . '%';
            $params[] = $term;
            $params[] = $term;
        }

        if (!empty($filters['status'])) {
            $valid = ['draft', 'proposal', 'pending_agreement', 'active', 'paused', 'completed', 'cancelled'];
            if (in_array($filters['status'], $valid, true)) {
                $where[] = "`status` = ?";
                $params[] = $filters['status'];
            }
        }

        if (!empty($filters['client_organisation_id'])) {
            $where[] = "`client_organisation_id` = ?";
            $params[] = (int) $filters['client_organisation_id'];
        }

        $whereSql = 'WHERE ' . implode(' AND ', $where);
        $paramObj = PDOParams::positional($params);

        $total = $this->db->scalarInt(
            "SELECT COUNT(*) FROM `recruit_engagements` {$whereSql}",
            $paramObj
        );

        $page    = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $totalPages = max(1, (int) ceil($total / $perPage));
        if ($page > $totalPages) $page = $totalPages;
        $offset = ($page - 1) * $perPage;

        $rows = $this->db->select(
            "SELECT * FROM `recruit_engagements` {$whereSql}
             ORDER BY `created_at` DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $paramObj
        );

        return [
            'rows'        => array_map([Engagement::class, 'fromRow'], $rows),
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => $totalPages,
        ];
    }

    /** @return Engagement[] */
    public function forOrganisation(int $clientOrganisationId): array
    {
        $rows = $this->db->select(
            "SELECT * FROM `recruit_engagements`
             WHERE `client_organisation_id` = ? AND `tenant_id` = ? AND `deleted_at` IS NULL
             ORDER BY `created_at` DESC",
            PDOParams::positional([$clientOrganisationId, $this->tenant->id()])
        );
        return array_map([Engagement::class, 'fromRow'], $rows);
    }

    /** @return array<string, int> */
    public function countsByStatus(): array
    {
        $rows = $this->db->select(
            "SELECT `status`, COUNT(*) AS n FROM `recruit_engagements`
             WHERE `tenant_id` = ? AND `deleted_at` IS NULL
             GROUP BY `status`",
            PDOParams::positional([$this->tenant->id()])
        );

        $counts = [
            'all' => 0, 'draft' => 0, 'proposal' => 0,
            'pending_agreement' => 0, 'active' => 0,
            'paused' => 0, 'completed' => 0, 'cancelled' => 0,
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
        $sql = "INSERT INTO `recruit_engagements`
            (`tenant_id`, `code`, `client_organisation_id`, `package_id`, `name`, `service_type`, `status`,
             `start_date`, `end_date`, `agreed_active_role_capacity`,
             `reporting_frequency`, `countries`, `scope_description`, `stages_included`,
             `notes`, `created_by`)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        return $this->db->insert($sql, PDOParams::positional([
            $this->tenant->id(),
            $data['code'],
            $data['client_organisation_id'],
            $data['package_id'] ?? null,
            $data['name'],
            $data['service_type'],
            $data['status'] ?? 'draft',
            $data['start_date'] ?? null,
            $data['end_date'] ?? null,
            $data['agreed_active_role_capacity'] ?? 1,
            $data['reporting_frequency'] ?? 'monthly',
            !empty($data['countries']) ? json_encode($data['countries']) : null,
            $data['scope_description'] ?? null,
            !empty($data['stages_included']) ? json_encode($data['stages_included']) : null,
            $data['notes'] ?? null,
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
        if (!$existing) {
            throw new \RuntimeException('Engagement not found.');
        }

        $allowed = [
            'package_id', 'name', 'service_type', 'status',
            'start_date', 'end_date', 'agreed_active_role_capacity',
            'reporting_frequency', 'countries', 'scope_description',
            'stages_included', 'notes', 'pipeline_mode',
        ];

        $sets = [];
        $params = [];
        $changes = [];

        foreach ($allowed as $col) {
            if (!array_key_exists($col, $data)) continue;

            $newVal = $data[$col];
            $prop = match ($col) {
                'package_id'                  => 'packageId',
                'service_type'                => 'serviceType',
                'start_date'                  => 'startDate',
                'end_date'                    => 'endDate',
                'agreed_active_role_capacity' => 'agreedActiveRoleCapacity',
                'reporting_frequency'         => 'reportingFrequency',
                'scope_description'           => 'scopeDescription',
                'stages_included'             => 'stagesIncluded',
                'pipeline_mode'               => 'pipelineMode',
                default                       => $col,
            };
            $oldVal = $existing->$prop ?? null;

            $newForCompare = $newVal;
            if (in_array($col, ['countries', 'stages_included'], true)) {
                $newForCompare = !empty($newVal) ? (is_array($newVal) ? $newVal : (is_string($newVal) ? json_decode($newVal, true) : [])) : [];
                $oldVal = $col === 'countries' ? $existing->countries : $existing->stagesIncluded;
            }

            if ($oldVal != $newForCompare) {
                $changes[$col] = ['old' => $oldVal, 'new' => $newForCompare];
            }

            $sets[] = "`{$col}` = ?";
            if (in_array($col, ['countries', 'stages_included'], true)) {
                $newVal = !empty($newVal) ? json_encode($newVal) : null;
            }
            $params[] = $newVal;
        }

        if (empty($sets)) return [];

        $params[] = $id;
        $params[] = $this->tenant->id();
        $this->db->execute(
            "UPDATE `recruit_engagements` SET " . implode(', ', $sets) . " WHERE `id` = ? AND `tenant_id` = ?",
            PDOParams::positional($params)
        );

        return $changes;
    }

    public function softDelete(int $id): void
    {
        $this->db->execute(
            "UPDATE `recruit_engagements` SET `deleted_at` = NOW() WHERE `id` = ? AND `tenant_id` = ?",
            PDOParams::positional([$id, $this->tenant->id()])
        );
    }

    public function restore(int $id): void
    {
        $this->db->execute(
            "UPDATE `recruit_engagements` SET `deleted_at` = NULL WHERE `id` = ? AND `tenant_id` = ?",
            PDOParams::positional([$id, $this->tenant->id()])
        );
    }

    public function nextCode(): string
    {
        $this->db->execute(
            "UPDATE `core_sequence_counters` SET `last_value` = `last_value` + 1 WHERE `name` = 'rec_engagement_code'"
        );
        $num = $this->db->scalarInt(
            "SELECT `last_value` FROM `core_sequence_counters` WHERE `name` = 'rec_engagement_code'"
        );

        return 'ENG-' . str_pad((string) $num, 6, '0', STR_PAD_LEFT);
    }

    /**
     * @return array<int, array{user_id: int, role: string, name: string, email: string}>
     */
    public function recruitersFor(int $engagementId): array
    {
        $rows = $this->db->select(
            "SELECT er.`user_id`, er.`role`, u.`name`, u.`email`
             FROM `recruit_engagement_recruiters` er
             JOIN `core_users` u ON u.`id` = er.`user_id`
             WHERE er.`engagement_id` = ? AND er.`tenant_id` = ?
             ORDER BY FIELD(er.`role`, 'lead', 'recruiter', 'researcher'), u.`name`",
            PDOParams::positional([$engagementId, $this->tenant->id()])
        );

        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                'user_id' => Row::int($row, 'user_id'),
                'role'    => Row::str($row, 'role'),
                'name'    => Row::str($row, 'name'),
                'email'   => Row::str($row, 'email'),
            ];
        }
        return $result;
    }

    public function assignRecruiter(int $engagementId, int $userId, string $role = 'recruiter'): void
    {
        $valid = ['lead', 'recruiter', 'researcher'];
        if (!in_array($role, $valid, true)) $role = 'recruiter';

        $this->db->execute(
            "INSERT INTO `recruit_engagement_recruiters` (`tenant_id`, `engagement_id`, `user_id`, `role`)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE `role` = VALUES(`role`)",
            PDOParams::positional([$this->tenant->id(), $engagementId, $userId, $role])
        );
    }

    public function unassignRecruiter(int $engagementId, int $userId): void
    {
        $this->db->execute(
            "DELETE FROM `recruit_engagement_recruiters` WHERE `engagement_id` = ? AND `user_id` = ? AND `tenant_id` = ?",
            PDOParams::positional([$engagementId, $userId, $this->tenant->id()])
        );
    }

    public function activeVacancyCount(int $engagementId): int
    {
        try {
            return $this->db->scalarInt(
                "SELECT COUNT(*) FROM `recruit_vacancies`
                 WHERE `engagement_id` = ?
                   AND `tenant_id` = ?
                   AND `status` NOT IN ('draft','closed','cancelled')
                   AND `deleted_at` IS NULL",
                PDOParams::positional([$engagementId, $this->tenant->id()])
            );
        } catch (\Throwable) {
            return 0;
        }
    }

    /** @return array<int, array<string,mixed>> */
    public function packages(): array
    {
        return $this->db->select(
            "SELECT * FROM `recruit_engagement_packages` WHERE `tenant_id` = ? AND `is_active` = 1 ORDER BY `name`",
            PDOParams::positional([$this->tenant->id()])
        );
    }

    /** @return array<string, mixed>|null */
    public function findPackage(int $id): ?array
    {
        return $this->db->selectOne(
            "SELECT * FROM `recruit_engagement_packages` WHERE `id` = ? AND `tenant_id` = ?",
            PDOParams::positional([$id, $this->tenant->id()])
        );
    }

    /** @return array<string, mixed>|null */
    public function findPackageBySlug(string $slug): ?array
    {
        return $this->db->selectOne(
            "SELECT * FROM `recruit_engagement_packages` WHERE `slug` = ? AND `tenant_id` = ?",
            PDOParams::positional([$slug, $this->tenant->id()])
        );
    }

    public function liveSearchConfig(): array
    {
        return [
            'table'            => 'recruit_engagements',
            'alias'            => 'e',
            'columns'          => ['e.name', 'e.code'],
            'order'            => 'e.created_at DESC',
            'permission'       => 'recruit.engagements.view',
            'baseFilters'      => ['e.`deleted_at` IS NULL', 'e.`tenant_id` = ?'],
            'baseFilterParams' => [$this->tenant->id()],
            'joins'            => [
                'INNER JOIN `recruit_client_organisations` o ON o.`id` = e.`client_organisation_id`',
            ],
            'rowView'          => 'engagements/_row',
            'extraColumns'     => ['o.name AS organisation_name', 'o.code AS organisation_code'],
            'aggregateColumns' => [],
            'filterable'       => ['status' => 'e.`status` = ?'],
        ];
    }
}

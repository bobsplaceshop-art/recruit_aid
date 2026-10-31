<?php
declare(strict_types=1);

namespace CorporateAid\Recruit\Repositories;

use CorporateAid\Core\Database;
use CorporateAid\Core\PDOParams;
use CorporateAid\Core\Row;
use CorporateAid\Core\TenantContext;
use CorporateAid\Platform\Support\CodeGenerator;
use CorporateAid\Recruit\Models\Organisation;

final class OrganisationRepository implements \CorporateAid\Platform\LiveSearch\LiveSearchable
{
    public function __construct(
        private Database $db,
        private TenantContext $tenant,
        private CodeGenerator $codes
    ) {}

    private function tenantId(): int
    {
        return $this->tenant->id();
    }

    private function assertTenantUser(?int $userId, string $field): void
    {
        if ($userId === null) {
            return;
        }

        $exists = $this->db->scalarInt(
            "SELECT COUNT(*) FROM `core_users`
             WHERE `id` = ? AND `tenant_id` = ? AND `deleted_at` IS NULL",
            PDOParams::positional([$userId, $this->tenantId()])
        );

        if ($exists < 1) {
            throw new \InvalidArgumentException(
                ucfirst($field) . ' must belong to the active tenant.'
            );
        }
    }

    public function findById(int $id): ?Organisation
    {
        $row = $this->db->selectOne(
            "SELECT * FROM `recruit_client_organisations`
             WHERE `id` = ? AND `tenant_id` = ?
             LIMIT 1",
            PDOParams::positional([$id, $this->tenantId()])
        );

        return $row ? Organisation::fromRow($row) : null;
    }

    public function findByCode(string $code, bool $includeDeleted = false): ?Organisation
    {
        $sql = "SELECT * FROM `recruit_client_organisations`
                WHERE `code` = ? AND `tenant_id` = ?";

        $params = [$code, $this->tenantId()];

        if (!$includeDeleted) {
            $sql .= " AND `deleted_at` IS NULL";
        }

        $sql .= " LIMIT 1";

        $row = $this->db->selectOne($sql, PDOParams::positional($params));

        return $row ? Organisation::fromRow($row) : null;
    }

    public function findByEmail(string $email, ?int $excludeId = null): ?Organisation
    {
        $sql = "SELECT * FROM `recruit_client_organisations`
                WHERE LOWER(`email`) = LOWER(?)
                  AND `tenant_id` = ?
                  AND `deleted_at` IS NULL";

        $params = [$email, $this->tenantId()];

        if ($excludeId !== null) {
            $sql .= " AND `id` != ?";
            $params[] = $excludeId;
        }

        $sql .= " LIMIT 1";

        $row = $this->db->selectOne($sql, PDOParams::positional($params));

        return $row === null ? null : Organisation::fromRow($row);
    }

    public function codeExists(string $code): bool
    {
        return $this->db->scalarInt(
            "SELECT COUNT(*) FROM `recruit_client_organisations`
             WHERE `code` = ? AND `tenant_id` = ?",
            PDOParams::positional([$code, $this->tenantId()])
        ) > 0;
    }

    /**
     * @param array{search?:string,status?:string,include_deleted?:bool} $filters
     * @return array{rows: Organisation[], total: int, page: int, per_page: int, total_pages: int}
     */
    public function paginate(array $filters = [], int $page = 1, int $perPage = 25): array
    {
        $where = ["`tenant_id` = ?"];
        $params = [$this->tenantId()];

        if (empty($filters['include_deleted'])) {
            $where[] = "`deleted_at` IS NULL";
        }

        if (!empty($filters['search'])) {
            $where[] = "(`name` LIKE ? OR `code` LIKE ? OR `industry` LIKE ? OR `email` LIKE ?)";
            $term = '%' . $filters['search'] . '%';
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }

        if (
            !empty($filters['status'])
            && in_array($filters['status'], ['prospect', 'active', 'inactive', 'former_client'], true)
        ) {
            $where[] = "`status` = ?";
            $params[] = $filters['status'];
        }

        $whereSql = 'WHERE ' . implode(' AND ', $where);
        $paramObj = PDOParams::positional($params);

        $total = $this->db->scalarInt(
            "SELECT COUNT(*) FROM `recruit_client_organisations` {$whereSql}",
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
            "SELECT * FROM `recruit_client_organisations` {$whereSql}
             ORDER BY `created_at` DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $paramObj
        );

        return [
            'rows'        => array_map([Organisation::class, 'fromRow'], $rows),
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => $totalPages,
        ];
    }

    /** @return array<string, int> */
    public function countsByStatus(): array
    {
        $rows = $this->db->select(
            "SELECT `status`, COUNT(*) AS n
             FROM `recruit_client_organisations`
             WHERE `deleted_at` IS NULL AND `tenant_id` = ?
             GROUP BY `status`",
            PDOParams::positional([$this->tenantId()])
        );

        $counts = [
            'all' => 0,
            'prospect' => 0,
            'active' => 0,
            'inactive' => 0,
            'former_client' => 0,
        ];

        foreach ($rows as $row) {
            $status = Row::str($row, 'status');
            $count = Row::int($row, 'n');

            if (array_key_exists($status, $counts)) {
                $counts[$status] = $count;
            }
        }

        $counts['all'] =
            $counts['prospect']
            + $counts['active']
            + $counts['inactive']
            + $counts['former_client'];

        return $counts;
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $createdBy = isset($data['created_by'])
            ? (int) $data['created_by']
            : null;

        $this->assertTenantUser($createdBy, 'creator');

        $sql = "INSERT INTO `recruit_client_organisations`
            (`tenant_id`, `code`, `name`, `legal_name`, `industry`, `website`,
             `country_code`, `countries_operation`, `address`, `phone`, `email`,
             `status`, `notes`, `created_by`)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        return $this->db->insert($sql, PDOParams::positional([
            $this->tenantId(),
            $data['code'],
            $data['name'],
            $data['legal_name'] ?? null,
            $data['industry'] ?? null,
            $data['website'] ?? null,
            $data['country_code'] ?? null,
            !empty($data['countries_operation'])
                ? json_encode($data['countries_operation'])
                : null,
            $data['address'] ?? null,
            $data['phone'] ?? null,
            $data['email'] ?? null,
            $data['status'] ?? 'prospect',
            $data['notes'] ?? null,
            $createdBy,
        ]));
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, array{old:mixed,new:mixed}> changes
     */
    public function update(int $id, array $data): array
    {
        $existing = $this->findById($id);

        if (!$existing) {
            throw new \RuntimeException('Organisation not found.');
        }

        $allowed = [
            'name',
            'legal_name',
            'industry',
            'website',
            'country_code',
            'countries_operation',
            'address',
            'phone',
            'email',
            'status',
            'notes',
        ];

        $sets = [];
        $params = [];
        $changes = [];

        foreach ($allowed as $column) {
            if (!array_key_exists($column, $data)) {
                continue;
            }

            $newValue = $data[$column];

            $property = match ($column) {
                'legal_name' => 'legalName',
                'country_code' => 'countryCode',
                'countries_operation' => 'countriesOperation',
                default => $column,
            };

            $oldValue = $existing->$property ?? null;
            $newForCompare = $newValue;

            if ($column === 'countries_operation') {
                $newForCompare = !empty($newValue)
                    ? (
                        is_array($newValue)
                            ? $newValue
                            : (
                                is_string($newValue)
                                    ? json_decode($newValue, true)
                                    : []
                            )
                    )
                    : [];

                $oldValue = $existing->countriesOperation;
            }

            if ($oldValue != $newForCompare) {
                $changes[$column] = [
                    'old' => $oldValue,
                    'new' => $newForCompare,
                ];
            }

            $sets[] = "`{$column}` = ?";

            if ($column === 'countries_operation') {
                $newValue = !empty($newValue)
                    ? json_encode($newValue)
                    : null;
            }

            $params[] = $newValue;
        }

        if (empty($sets)) {
            return [];
        }

        $params[] = $id;
        $params[] = $this->tenantId();

        $this->db->execute(
            "UPDATE `recruit_client_organisations`
             SET " . implode(', ', $sets) . "
             WHERE `id` = ? AND `tenant_id` = ?",
            PDOParams::positional($params)
        );

        return $changes;
    }

    public function softDelete(int $id): void
    {
        $this->db->execute(
            "UPDATE `recruit_client_organisations`
             SET `deleted_at` = NOW()
             WHERE `id` = ? AND `tenant_id` = ?",
            PDOParams::positional([$id, $this->tenantId()])
        );
    }

    public function restore(int $id): void
    {
        $this->db->execute(
            "UPDATE `recruit_client_organisations`
             SET `deleted_at` = NULL
             WHERE `id` = ? AND `tenant_id` = ?",
            PDOParams::positional([$id, $this->tenantId()])
        );
    }

    public function count(): int
    {
        return $this->db->scalarInt(
            "SELECT COUNT(*) FROM `recruit_client_organisations`
             WHERE `deleted_at` IS NULL AND `tenant_id` = ?",
            PDOParams::positional([$this->tenantId()])
        );
    }

    public function nextCode(): string
    {
        return $this->codes->nextForTenant(
            'rec_client_org_code',
            'ORG',
            $this->tenantId()
        );
    }

    /**
     * Minimal list for filter dropdowns: active organisations only.
     *
     * @return array<int, array{id:int,code:string,name:string}>
     */
    public function allForFilter(): array
    {
        $rows = $this->db->select(
            "SELECT `id`, `code`, `name`
             FROM `recruit_client_organisations`
             WHERE `deleted_at` IS NULL AND `tenant_id` = ?
             ORDER BY `name` ASC",
            PDOParams::positional([$this->tenantId()])
        );

        return array_map(
            static fn(array $row): array => [
                'id' => Row::int($row, 'id'),
                'code' => Row::str($row, 'code'),
                'name' => Row::str($row, 'name'),
            ],
            $rows
        );
    }

    /**
     * Live-search configuration for the reusable LiveSearch engine.
     */
    public function liveSearchConfig(): array
    {
        return [
            'table'            => 'recruit_client_organisations',
            'alias'            => 'o',
            'columns'          => ['o.name', 'o.code', 'o.industry', 'o.email'],
            'order'            => 'o.created_at DESC',
            'permission'       => 'recruit.organisations.view',
            'baseFilters'      => ['o.`deleted_at` IS NULL'],
            'joins'            => [],
            'rowView'          => 'organisations/_row',
            'extraColumns'     => [],
            'aggregateColumns' => [],
            'filterable'       => ['status' => 'o.`status` = ?'],
        ];
    }
}

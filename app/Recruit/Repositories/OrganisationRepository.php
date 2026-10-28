<?php
declare(strict_types=1);

namespace CorporateAid\Recruit\Repositories;

use CorporateAid\Core\Database;
use CorporateAid\Recruit\Models\Organisation;

final class OrganisationRepository
{

    // ---------- scalar coercion helper (PHPStan Level 9) ----------

    private static function toInt(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }
    public function __construct(private Database $db) {}

    public function findById(int $id): ?Organisation
    {
        $row = $this->db->selectOne(
            "SELECT * FROM `recruit_client_organisations` WHERE `id` = ? LIMIT 1",
            [$id]
        );
        return $row ? Organisation::fromRow($row) : null;
    }

    public function findByCode(string $code, bool $includeDeleted = false): ?Organisation
    {
        $sql = "SELECT * FROM `recruit_client_organisations` WHERE `code` = ?";
        if (!$includeDeleted) {
            $sql .= " AND `deleted_at` IS NULL";
        }
        $sql .= " LIMIT 1";

        $row = $this->db->selectOne($sql, [$code]);
        return $row ? Organisation::fromRow($row) : null;
    }

    public function codeExists(string $code): bool
    {
        $count = self::toInt($this->db->scalar(
            "SELECT COUNT(*) FROM `recruit_client_organisations` WHERE `code` = ?",
            [$code]
        ));
        return $count > 0;
    }

    /**
     * Paginated list with search + optional status filter.
     *
     * @param array{search?:string,status?:string,include_deleted?:bool} $filters
     * @return array{rows: Organisation[], total: int, page: int, per_page: int, total_pages: int}
     */
    public function paginate(array $filters = [], int $page = 1, int $perPage = 25): array
    {
        $where = [];
        $params = [];

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

        if (!empty($filters['status'])
            && in_array($filters['status'], ['prospect', 'active', 'inactive', 'former_client'], true)) {
            $where[] = "`status` = ?";
            $params[] = $filters['status'];
        }

        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        $total = self::toInt($this->db->scalar("SELECT COUNT(*) FROM `recruit_client_organisations` {$whereSql}", $params));

        $page    = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $totalPages = max(1, (int) ceil($total / $perPage));
        if ($page > $totalPages) $page = $totalPages;
        $offset = ($page - 1) * $perPage;

        $rows = $this->db->select(
            "SELECT * FROM `recruit_client_organisations` {$whereSql}
             ORDER BY `created_at` DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return [
            'rows'        => array_map([Organisation::class, 'fromRow'], $rows),
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => $totalPages,
        ];
    }

    /**
     * Count by status for the filter pills.
     * @return array<string, int>
     */
    public function countsByStatus(): array
    {
        $rows = $this->db->select(
            "SELECT `status`, COUNT(*) AS n FROM `recruit_client_organisations`
             WHERE `deleted_at` IS NULL
             GROUP BY `status`"
        );

        $counts = ['all' => 0, 'prospect' => 0, 'active' => 0, 'inactive' => 0, 'former_client' => 0];
        foreach ($rows as $row) {
            $s = (string) $row['status'];
            $counts[$s] = (int) $row['n'];
            $counts['all'] += (int) $row['n'];
        }
        return $counts;
    }

    public function create(array $data): int
    {
        $sql = "INSERT INTO `recruit_client_organisations`
            (`code`, `name`, `legal_name`, `industry`, `website`,
             `country_code`, `countries_operation`, `address`, `phone`, `email`,
             `status`, `notes`, `created_by`)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        return $this->db->insert($sql, [
            $data['code'],
            $data['name'],
            $data['legal_name'] ?? null,
            $data['industry'] ?? null,
            $data['website'] ?? null,
            $data['country_code'] ?? null,
            !empty($data['countries_operation']) ? json_encode($data['countries_operation']) : null,
            $data['address'] ?? null,
            $data['phone'] ?? null,
            $data['email'] ?? null,
            $data['status'] ?? 'prospect',
            $data['notes'] ?? null,
            $data['created_by'] ?? null,
        ]);
    }

    /**
     * Update with whitelist of columns.
     * Also computes what changed for audit trail purposes.
     *
     * @return array<string, array{old:mixed,new:mixed}> changes
     */
    public function update(int $id, array $data): array
    {
        $existing = $this->findById($id);
        if (!$existing) {
            throw new \RuntimeException('Organisation not found.');
        }

        $allowed = [
            'name', 'legal_name', 'industry', 'website', 'country_code',
            'countries_operation', 'address', 'phone', 'email', 'status', 'notes',
        ];

        $sets = [];
        $params = [];
        $changes = [];

        foreach ($allowed as $col) {
            if (!array_key_exists($col, $data)) continue;

            $newVal = $data[$col];
            $oldVal = null;

            // Map column to model property
            $prop = match ($col) {
                'legal_name'          => 'legalName',
                'country_code'        => 'countryCode',
                'countries_operation' => 'countriesOperation',
                default               => $col,
            };
            $oldVal = $existing->$prop ?? null;

            // Normalize for comparison
            $newForCompare = $newVal;
            if ($col === 'countries_operation') {
                $newForCompare = !empty($newVal) ? (is_array($newVal) ? $newVal : json_decode((string) $newVal, true)) : [];
                $oldVal = $existing->countriesOperation;
            }

            if ($oldVal != $newForCompare) {
                $changes[$col] = ['old' => $oldVal, 'new' => $newForCompare];
            }

            $sets[] = "`{$col}` = ?";
            if ($col === 'countries_operation') {
                $newVal = !empty($newVal) ? json_encode($newVal) : null;
            }
            $params[] = $newVal;
        }

        if (empty($sets)) {
            return [];
        }

        $params[] = $id;
        $this->db->execute(
            "UPDATE `recruit_client_organisations` SET " . implode(', ', $sets) . " WHERE `id` = ?",
            $params
        );

        return $changes;
    }

    public function softDelete(int $id): void
    {
        $this->db->execute(
            "UPDATE `recruit_client_organisations` SET `deleted_at` = NOW() WHERE `id` = ?",
            [$id]
        );
    }

    public function restore(int $id): void
    {
        $this->db->execute(
            "UPDATE `recruit_client_organisations` SET `deleted_at` = NULL WHERE `id` = ?",
            [$id]
        );
    }

    public function count(): int
    {
        return self::toInt($this->db->scalar("SELECT COUNT(*) FROM `recruit_client_organisations` WHERE `deleted_at` IS NULL"));
    }

    /**
     * Generate the next organisation code (ORG-000001, ORG-000002, ...).
     * Uses the sequence_counters table so codes are NEVER reused,
     * even after a hard delete.
     */
    public function nextCode(): string
    {
        $this->db->execute(
            "UPDATE `core_sequence_counters` SET `last_value` = `last_value` + 1 WHERE `name` = 'rec_client_org_code'"
        );
        $num = self::toInt($this->db->scalar(
            "SELECT `last_value` FROM `core_sequence_counters` WHERE `name` = 'rec_client_org_code'"
        ));

        return 'ORG-' . str_pad((string) $num, 6, '0', STR_PAD_LEFT);
    }
}

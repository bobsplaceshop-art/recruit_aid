<?php
declare(strict_types=1);

namespace CorporateAid\Recruit\Repositories;

use CorporateAid\Core\Database;
use CorporateAid\Core\PDOParams;
use CorporateAid\Core\Row;
use CorporateAid\Core\TenantContext;

/**
 * All SQL against recruit_tasks.
 *
 * Every method filters by tenant_id via TenantContext. No cross-tenant
 * query is possible through this repository.
 *
 * Tasks are polymorphic via entity_type/entity_id. The repository does
 * NOT validate that the referenced entity exists — that's TaskService's
 * job (because it needs the entity repositories).
 */
final class TaskRepository
{
    public function __construct(
        private Database $db,
        private TenantContext $tenant
    ) {}

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        return $this->db->selectOne(
            "SELECT * FROM `recruit_tasks`
             WHERE `id` = ? AND `tenant_id` = ? AND `deleted_at` IS NULL
             LIMIT 1",
            PDOParams::positional([$id, $this->tenant->id()])
        );
    }

    /**
     * @param array{status?:string, priority?:string, assigned_to?:int, entity_type?:string, entity_id?:int, due_filter?:string} $filters
     * @return array{rows: array<int, array<string, mixed>>, total: int, page: int, per_page: int, total_pages: int}
     */
    public function findAll(array $filters, int $page = 1, int $perPage = 50): array
    {
        $where = ["`t`.`tenant_id` = ?", "`t`.`deleted_at` IS NULL"];
        $params = [$this->tenant->id()];

        if (isset($filters['status']) && in_array($filters['status'], ['open', 'completed', 'cancelled'], true)) {
            $where[] = "`t`.`status` = ?";
            $params[] = $filters['status'];
        }
        if (isset($filters['priority']) && in_array($filters['priority'], ['low', 'normal', 'high'], true)) {
            $where[] = "`t`.`priority` = ?";
            $params[] = $filters['priority'];
        }
        if (isset($filters['assigned_to']) && $filters['assigned_to'] > 0) {
            $where[] = "`t`.`assigned_to` = ?";
            $params[] = $filters['assigned_to'];
        }
        if (isset($filters['entity_type']) && $filters['entity_type'] !== '') {
            $where[] = "`t`.`entity_type` = ?";
            $params[] = $filters['entity_type'];
        }
        if (isset($filters['entity_id']) && $filters['entity_id'] > 0) {
            $where[] = "`t`.`entity_id` = ?";
            $params[] = $filters['entity_id'];
        }
        if (isset($filters['due_filter'])) {
            $now = date('Y-m-d H:i:s');
            $today = date('Y-m-d 23:59:59');
            $week = date('Y-m-d 23:59:59', strtotime('+7 days'));
            if ($filters['due_filter'] === 'overdue') {
                $where[] = "`t`.`due_at` IS NOT NULL AND `t`.`due_at` < ? AND `t`.`status` = 'open'";
                $params[] = $now;
            } elseif ($filters['due_filter'] === 'today') {
                $where[] = "`t`.`due_at` BETWEEN ? AND ?";
                $params[] = $now;
                $params[] = $today;
            } elseif ($filters['due_filter'] === 'week') {
                $where[] = "`t`.`due_at` BETWEEN ? AND ?";
                $params[] = $now;
                $params[] = $week;
            }
        }

        $whereSql = implode(' AND ', $where);

        $total = $this->db->scalarInt(
            "SELECT COUNT(*) FROM `recruit_tasks` t WHERE {$whereSql}",
            PDOParams::positional($params)
        );

        $page = max(1, $page);
        $perPage = min(100, max(1, $perPage));
        $offset = ($page - 1) * $perPage;
        $totalPages = max(1, (int) ceil($total / $perPage));

        $rows = $this->db->select(
            "SELECT `t`.*, `u`.`name` AS assigned_name, `c`.`name` AS created_name
             FROM `recruit_tasks` t
             LEFT JOIN `core_users` u ON u.`id` = `t`.`assigned_to`
             LEFT JOIN `core_users` c ON c.`id` = `t`.`created_by`
             WHERE {$whereSql}
             ORDER BY
                CASE WHEN `t`.`status` = 'open' AND `t`.`due_at` IS NOT NULL THEN 0 ELSE 1 END,
                `t`.`due_at` ASC,
                `t`.`priority` DESC,
                `t`.`created_at` DESC
             LIMIT {$perPage} OFFSET {$offset}",
            PDOParams::positional($params)
        );

        return [
            'rows' => $rows,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => $totalPages,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findForEntity(string $entityType, int $entityId, int $limit = 50): array
    {
        return $this->db->select(
            "SELECT * FROM `recruit_tasks`
             WHERE `tenant_id` = ? AND `entity_type` = ? AND `entity_id` = ?
               AND `deleted_at` IS NULL
             ORDER BY `status` ASC, `due_at` ASC
             LIMIT {$limit}",
            PDOParams::positional([$this->tenant->id(), $entityType, $entityId])
        );
    }

    public function countOverdueForUser(int $userId): int
    {
        return $this->db->scalarInt(
            "SELECT COUNT(*) FROM `recruit_tasks`
             WHERE `tenant_id` = ? AND `assigned_to` = ?
               AND `status` = 'open' AND `due_at` IS NOT NULL AND `due_at` < NOW()
               AND `deleted_at` IS NULL",
            PDOParams::positional([$this->tenant->id(), $userId])
        );
    }

    public function countOpenForUser(int $userId): int
    {
        return $this->db->scalarInt(
            "SELECT COUNT(*) FROM `recruit_tasks`
             WHERE `tenant_id` = ? AND `assigned_to` = ? AND `status` = 'open' AND `deleted_at` IS NULL",
            PDOParams::positional([$this->tenant->id(), $userId])
        );
    }

    /**
     * @param array{title:string, description?:?string, priority:string, due_at?:?string, assigned_to?:?int, entity_type?:?string, entity_id?:?int, created_by:int} $data
     */
    public function create(array $data): int
    {
        return $this->db->insert(
            "INSERT INTO `recruit_tasks`
                (`tenant_id`, `title`, `description`, `priority`, `due_at`,
                 `assigned_to`, `entity_type`, `entity_id`, `created_by`, `status`)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'open')",
            PDOParams::positional([
                $this->tenant->id(),
                $data['title'],
                $data['description'] ?? null,
                $data['priority'] ?? 'normal',
                $data['due_at'] ?? null,
                $data['assigned_to'] ?? null,
                $data['entity_type'] ?? null,
                $data['entity_id'] ?? null,
                $data['created_by'],
            ])
        );
    }

    /**
     * @param array{title?:string, description?:?string, priority?:string, due_at?:?string, assigned_to?:?int} $data
     */
    public function update(int $id, array $data): void
    {
        $sets = [];
        $params = [];

        if (isset($data['title'])) {
            $sets[] = "`title` = ?";
            $params[] = $data['title'];
        }
        if (array_key_exists('description', $data)) {
            $sets[] = "`description` = ?";
            $params[] = $data['description'];
        }
        if (isset($data['priority'])) {
            $sets[] = "`priority` = ?";
            $params[] = $data['priority'];
        }
        if (array_key_exists('due_at', $data)) {
            $sets[] = "`due_at` = ?";
            $params[] = $data['due_at'];
        }
        if (array_key_exists('assigned_to', $data)) {
            $sets[] = "`assigned_to` = ?";
            $params[] = $data['assigned_to'];
        }

        if ($sets === []) {
            return;
        }

        $params[] = $id;
        $params[] = $this->tenant->id();

        $this->db->execute(
            "UPDATE `recruit_tasks` SET " . implode(', ', $sets) . "
             WHERE `id` = ? AND `tenant_id` = ? AND `deleted_at` IS NULL",
            PDOParams::positional($params)
        );
    }

    public function complete(int $id): void
    {
        $this->db->execute(
            "UPDATE `recruit_tasks`
             SET `status` = 'completed', `completed_at` = NOW()
             WHERE `id` = ? AND `tenant_id` = ? AND `status` = 'open' AND `deleted_at` IS NULL",
            PDOParams::positional([$id, $this->tenant->id()])
        );
    }

    public function reopen(int $id): void
    {
        $this->db->execute(
            "UPDATE `recruit_tasks`
             SET `status` = 'open', `completed_at` = NULL
             WHERE `id` = ? AND `tenant_id` = ? AND `status` IN ('completed', 'cancelled') AND `deleted_at` IS NULL",
            PDOParams::positional([$id, $this->tenant->id()])
        );
    }

    public function softDelete(int $id): void
    {
        $this->db->execute(
            "UPDATE `recruit_tasks` SET `deleted_at` = NOW()
             WHERE `id` = ? AND `tenant_id` = ? AND `deleted_at` IS NULL",
            PDOParams::positional([$id, $this->tenant->id()])
        );
    }
}

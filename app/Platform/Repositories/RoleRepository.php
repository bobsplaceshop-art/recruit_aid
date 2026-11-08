<?php
declare(strict_types=1);

namespace CorporateAid\Platform\Repositories;

use CorporateAid\Core\Database;
use CorporateAid\Core\PDOParams;
use CorporateAid\Core\Row;
use CorporateAid\Core\TenantContext;
use RuntimeException;

final class RoleRepository
{
    public function __construct(private Database $db, private TenantContext $tenant) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        return $this->db->select(
            "SELECT `id`, `name`, `slug`, `description`, `is_system`
             FROM `core_roles`
             WHERE `tenant_id` = ? AND `module_key` = 'recruit'
             ORDER BY `name`",
            PDOParams::positional([$this->tenant->id()])
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        return $this->db->selectOne(
            "SELECT * FROM `core_roles`
             WHERE `id` = ? AND `tenant_id` = ? AND `module_key` = 'recruit'
             LIMIT 1",
            PDOParams::positional([$id, $this->tenant->id()])
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findBySlug(string $slug): ?array
    {
        return $this->db->selectOne(
            "SELECT * FROM `core_roles`
             WHERE `slug` = ? AND `tenant_id` = ? AND `module_key` = 'recruit'
             LIMIT 1",
            PDOParams::positional([$slug, $this->tenant->id()])
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): int
    {
        $tenantId = $this->tenant->id();
        if ($tenantId <= 0) {
            throw new RuntimeException('An active tenant is required to create a role.');
        }

        $sql = "INSERT INTO `core_roles`
                    (`tenant_id`, `name`, `slug`, `description`, `scope`, `module_key`, `is_system`)
                VALUES (?, ?, ?, ?, 'app', 'recruit', 0)";
        return $this->db->insert($sql, PDOParams::positional([
            $tenantId,
            $data['name'],
            $data['slug'],
            $data['description'] ?? null,
        ]));
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): void
    {
        $sql = "UPDATE `core_roles`
                SET `name` = ?, `description` = ?
                WHERE `id` = ? AND `tenant_id` = ?
                  AND `module_key` = 'recruit' AND `is_system` = 0";
        $this->db->execute($sql, PDOParams::positional([
            $data['name'],
            $data['description'] ?? null,
            $id,
            $this->tenant->id(),
        ]));
    }

    public function delete(int $id): void
    {
        $this->db->execute(
            "DELETE FROM `core_roles`
             WHERE `id` = ? AND `tenant_id` = ?
               AND `module_key` = 'recruit' AND `is_system` = 0",
            PDOParams::positional([$id, $this->tenant->id()])
        );
    }

    /**
     * @param int[] $permissionIds
     */
    public function syncPermissions(int $roleId, array $permissionIds): void
    {
        $tenantId = $this->tenant->id();
        $roleExists = $this->db->scalarInt(
            "SELECT COUNT(*) FROM `core_roles`
             WHERE `id` = ? AND `tenant_id` = ? AND `module_key` = 'recruit'",
            PDOParams::positional([$roleId, $tenantId])
        );

        if ($roleExists !== 1) {
            throw new RuntimeException('Role does not belong to the active tenant and Recruit Aid.');
        }

        $permissionIds = array_values(array_unique(array_filter(
            array_map('intval', $permissionIds),
            static fn (int $id): bool => $id > 0
        )));

        if ($permissionIds !== []) {
            $placeholders = implode(',', array_fill(0, count($permissionIds), '?'));
            $validCount = $this->db->scalarInt(
                "SELECT COUNT(*) FROM `core_permissions`
                 WHERE `id` IN ({$placeholders})
                   AND `module_key` = 'recruit'
                   AND `is_active` = 1",
                PDOParams::positional($permissionIds)
            );

            if ($validCount !== count($permissionIds)) {
                throw new RuntimeException('One or more permissions are not active Recruit Aid permissions.');
            }
        }

        $this->db->execute(
            "DELETE FROM `core_role_permissions` WHERE `role_id` = ?",
            PDOParams::positional([$roleId])
        );

        if ($permissionIds === []) {
            return;
        }

        $values = [];
        $params = [];
        foreach ($permissionIds as $permissionId) {
            $values[] = "(?, ?)";
            array_push($params, $roleId, $permissionId);
        }

        $sql = "INSERT INTO `core_role_permissions` (`role_id`, `permission_id`) VALUES "
             . implode(', ', $values);
        $this->db->execute($sql, PDOParams::positional($params));
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function permissionsGroupedByModule(): array
    {
        $rows = $this->db->select(
            "SELECT `id`, `slug`, `module`, `description`
             FROM `core_permissions`
             WHERE `module_key` = 'recruit' AND `is_active` = 1
             ORDER BY `module`, `slug`"
        );
        $grouped = [];
        foreach ($rows as $r) {
            $module = Row::str($r, 'module');
            $grouped[$module][] = $r;
        }
        return $grouped;
    }

    /**
     * @return int[]
     */
    public function permissionIdsForRole(int $roleId): array
    {
        $rows = $this->db->select(
            "SELECT rp.`permission_id`
             FROM `core_role_permissions` rp
             JOIN `core_roles` r ON r.`id` = rp.`role_id`
             JOIN `core_permissions` p ON p.`id` = rp.`permission_id`
             WHERE rp.`role_id` = ?
               AND r.`tenant_id` = ?
               AND r.`module_key` = 'recruit'
               AND p.`module_key` = 'recruit'",
            PDOParams::positional([$roleId, $this->tenant->id()])
        );
        return array_map(fn(array $r) => Row::int($r, 'permission_id'), $rows);
    }

    public function userCountForRole(int $roleId): int
    {
        return $this->db->scalarInt(
            "SELECT COUNT(*)
             FROM `core_user_roles` ur
             JOIN `core_roles` r
               ON r.`id` = ur.`role_id`
              AND r.`tenant_id` = ur.`tenant_id`
             WHERE ur.`role_id` = ?
               AND ur.`tenant_id` = ?
               AND r.`module_key` = 'recruit'
               AND (ur.`expires_at` IS NULL OR ur.`expires_at` > NOW())",
            PDOParams::positional([$roleId, $this->tenant->id()])
        );
    }
}

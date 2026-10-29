<?php
declare(strict_types=1);

namespace CorporateAid\Platform\Repositories;

use CorporateAid\Core\Database;
use CorporateAid\Core\PDOParams;
use CorporateAid\Core\Row;

final class RoleRepository
{
    public function __construct(private Database $db) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        return $this->db->select("SELECT `id`, `name`, `slug`, `description`, `is_system` FROM `core_roles` ORDER BY `name`");
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        return $this->db->selectOne(
            "SELECT * FROM `core_roles` WHERE `id` = ? LIMIT 1",
            PDOParams::positional([$id])
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findBySlug(string $slug): ?array
    {
        return $this->db->selectOne(
            "SELECT * FROM `core_roles` WHERE `slug` = ? LIMIT 1",
            PDOParams::positional([$slug])
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): int
    {
        $sql = "INSERT INTO `core_roles` (`name`, `slug`, `description`, `is_system`) VALUES (?, ?, ?, 0)";
        return $this->db->insert($sql, PDOParams::positional([
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
        $sql = "UPDATE `core_roles` SET `name` = ?, `description` = ? WHERE `id` = ?";
        $this->db->execute($sql, PDOParams::positional([
            $data['name'],
            $data['description'] ?? null,
            $id,
        ]));
    }

    public function delete(int $id): void
    {
        $this->db->execute("DELETE FROM `core_roles` WHERE `id` = ?", PDOParams::positional([$id]));
    }

    /**
     * @param int[] $permissionIds
     */
    public function syncPermissions(int $roleId, array $permissionIds): void
    {
        $this->db->execute("DELETE FROM `core_role_permissions` WHERE `role_id` = ?", PDOParams::positional([$roleId]));

        if (empty($permissionIds)) {
            return;
        }

        $values = [];
        $params = [];
        foreach ($permissionIds as $pid) {
            $values[] = "(?, ?)";
            $params[] = $roleId;
            $params[] = $pid;
        }

        $sql = "INSERT INTO `core_role_permissions` (`role_id`, `permission_id`) VALUES " . implode(', ', $values);
        $this->db->execute($sql, PDOParams::positional($params));
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function permissionsGroupedByModule(): array
    {
        $rows = $this->db->select("SELECT `id`, `slug`, `module`, `description` FROM `core_permissions` ORDER BY `module`, `slug`");
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
            "SELECT `permission_id` FROM `core_role_permissions` WHERE `role_id` = ?",
            PDOParams::positional([$roleId])
        );
        return array_map(fn(array $r) => Row::int($r, 'permission_id'), $rows);
    }

    public function userCountForRole(int $roleId): int
    {
        return $this->db->scalarInt(
            "SELECT COUNT(*) FROM `core_user_roles` WHERE `role_id` = ?",
            PDOParams::positional([$roleId])
        );
    }
}

<?php
declare(strict_types=1);

namespace CorporateAid\Platform\Repositories;

use CorporateAid\Core\Database;
use CorporateAid\Core\PDOParams;
use CorporateAid\Core\Row;
use CorporateAid\Platform\Models\User;
use CorporateAid\Platform\Support\Hasher;

/**
 * All reads/writes against the `core_users` table.
 * Nothing else in the app should contain SQL for users.
 */
final class UserRepository
{
    public function __construct(private Database $db) {}

    public function findById(int $id): ?User
    {
        $row = $this->db->selectOne(
            "SELECT * FROM `core_users` WHERE `id` = ? AND `deleted_at` IS NULL LIMIT 1",
            PDOParams::positional([$id])
        );
        return $row ? User::fromRow($row) : null;
    }

    public function findByEmail(string $email): ?User
    {
        $row = $this->db->selectOne(
            "SELECT * FROM `core_users` WHERE `email` = ? AND `deleted_at` IS NULL LIMIT 1",
            PDOParams::positional([strtolower(trim($email))])
        );
        return $row ? User::fromRow($row) : null;
    }

    /**
     * @param array{name:string,email:string,password:string,status?:string,is_client_user?:bool,organisation_id?:?int,phone?:?string,timezone?:string} $data
     */
    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $sql = "INSERT INTO `core_users`
                (`name`, `email`, `password_hash`, `phone`, `timezone`, `status`, `is_client_user`, `organisation_id`)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

        $email = Row::str($data, 'email');
        $plain = Row::str($data, 'password');

        return $this->db->insert($sql, PDOParams::positional([
            Row::str($data, 'name'),
            strtolower(trim($email)),
            Hasher::hash($plain),
            $data['phone'] ?? null,
            $data['timezone'] ?? 'Africa/Nairobi',
            $data['status'] ?? 'active',
            !empty($data['is_client_user']) ? 1 : 0,
            $data['organisation_id'] ?? null,
        ]));
    }

    public function updatePassword(int $userId, string $newPassword): void
    {
        $this->db->execute(
            "UPDATE `core_users` SET `password_hash` = ? WHERE `id` = ?",
            PDOParams::positional([Hasher::hash($newPassword), $userId])
        );
    }

    public function updateLastLogin(int $userId): void
    {
        $this->db->execute(
            "UPDATE `core_users` SET `last_login_at` = NOW(), `failed_logins` = 0, `locked_until` = NULL WHERE `id` = ?",
            PDOParams::positional([$userId])
        );
    }

    public function incrementFailedLogins(int $userId, int $lockThreshold = 5, int $lockMinutes = 15): void
    {
        $this->db->execute(
            "UPDATE `core_users` SET `failed_logins` = `failed_logins` + 1 WHERE `id` = ?",
            PDOParams::positional([$userId])
        );

        $failed = $this->db->scalarInt(
            "SELECT `failed_logins` FROM `core_users` WHERE `id` = ?",
            PDOParams::positional([$userId])
        );
        if ($failed >= $lockThreshold) {
            $this->db->execute(
                "UPDATE `core_users` SET `locked_until` = DATE_ADD(NOW(), INTERVAL ? MINUTE) WHERE `id` = ?",
                PDOParams::positional([$lockMinutes, $userId])
            );
        }
    }

    public function setStatus(int $userId, string $status): void
    {
        $this->db->execute(
            "UPDATE `core_users` SET `status` = ? WHERE `id` = ?",
            PDOParams::positional([$status, $userId])
        );
    }

    /**
     * Attach a role to a user by role slug. Idempotent.
     */
    public function attachRoleBySlug(int $userId, string $roleSlug): void
    {
        $this->db->execute(
            "INSERT IGNORE INTO `core_user_roles` (`user_id`, `role_id`)
             SELECT ?, `id` FROM `core_roles` WHERE `slug` = ?",
            PDOParams::positional([$userId, $roleSlug])
        );
    }

    /**
     * Load a user's role slugs into ->roleSlugs (mutates the passed object).
     */
    public function loadRoles(User $user): User
    {
        $rows = $this->db->select(
            "SELECT r.`slug`
             FROM `core_roles` r
             JOIN `core_user_roles` ur ON ur.`role_id` = r.`id`
             WHERE ur.`user_id` = ?",
            PDOParams::positional([$user->id])
        );
        $user->roleSlugs = array_map(static fn ($r) => Row::str($r, 'slug'), $rows);
        return $user;
    }

    /**
     * Load a user's permission slugs into ->permissionSlugs (mutates the passed object).
     */
    public function loadPermissions(User $user): User
    {
        $rows = $this->db->select(
            "SELECT DISTINCT p.`slug`
             FROM `core_permissions` p
             JOIN `core_role_permissions` rp ON rp.`permission_id` = p.`id`
             JOIN `core_user_roles` ur ON ur.`role_id` = rp.`role_id`
             WHERE ur.`user_id` = ?",
            PDOParams::positional([$user->id])
        );
        $user->permissionSlugs = array_map(static fn ($r) => Row::str($r, 'slug'), $rows);
        return $user;
    }

    public function count(): int
    {
        return $this->db->scalarInt("SELECT COUNT(*) FROM `core_users` WHERE `deleted_at` IS NULL");
    }

    /**
     * Return active staff users suitable for recruiter assignment.
     * @return array<int, array{id:int, name:string, email:string, role_slugs:string}>
     */
    public function listForAssignment(): array
    {
        /** @var array<int, array{id:int, name:string, email:string, role_slugs:string}> */
        return $this->db->select(
            "SELECT u.`id`, u.`name`, u.`email`,
                    GROUP_CONCAT(r.`slug` ORDER BY r.`slug` SEPARATOR ',') AS role_slugs
             FROM `core_users` u
             LEFT JOIN `core_user_roles` ur ON ur.`user_id` = u.`id`
             LEFT JOIN `core_roles` r ON r.`id` = ur.`role_id`
             WHERE u.`deleted_at` IS NULL
               AND u.`status` = 'active'
               AND u.`is_client_user` = 0
             GROUP BY u.`id`
             ORDER BY u.`name` ASC"
        );
    }

    /**
     * Return IDs of active users who have the given permission.
     * @return int[]
     */
    public function findActiveUserIdsByPermission(string $permissionSlug): array
    {
        $rows = $this->db->select(
            "SELECT DISTINCT u.`id`
             FROM `core_users` u
             JOIN `core_user_roles` ur ON ur.`user_id` = u.`id`
             JOIN `core_role_permissions` rp ON rp.`role_id` = ur.`role_id`
             JOIN `core_permissions` p ON p.`id` = rp.`permission_id`
             WHERE u.`deleted_at` IS NULL AND u.`status` = 'active'
               AND p.`slug` = ?",
            PDOParams::positional([$permissionSlug])
        );
        return array_map(fn ($r) => Row::int($r, 'id'), $rows);
    }

    /**
     * Return IDs of active users with a given role slug.
     * @return int[]
     */
    public function findActiveUserIdsByRole(string $roleSlug): array
    {
        $rows = $this->db->select(
            "SELECT DISTINCT u.`id`
             FROM `core_users` u
             JOIN `core_user_roles` ur ON ur.`user_id` = u.`id`
             JOIN `core_roles` r ON r.`id` = ur.`role_id`
             WHERE u.`deleted_at` IS NULL AND u.`status` = 'active'
               AND r.`slug` = ?",
            PDOParams::positional([$roleSlug])
        );
        return array_map(fn ($r) => Row::int($r, 'id'), $rows);
    }

    /**
     * Return all non-deleted users with their role slugs.
     * @return array<int, array{id:int, name:string, email:string, status:string, last_login_at:?string, roles: string[]}>
     */
    public function listAll(): array
    {
        $rows = $this->db->select(
            "SELECT `id`, `name`, `email`, `status`, `last_login_at`
             FROM `core_users`
             WHERE `deleted_at` IS NULL
             ORDER BY `name` ASC"
        );

        $users = [];
        foreach ($rows as $row) {
            $userId = Row::int($row, 'id');
            $roleRows = $this->db->select(
                "SELECT r.`slug`
                 FROM `core_roles` r
                 JOIN `core_user_roles` ur ON ur.`role_id` = r.`id`
                 WHERE ur.`user_id` = ?
                 ORDER BY r.`name` ASC",
                PDOParams::positional([$userId])
            );
            $roles = array_map(fn($r) => Row::str($r, 'slug'), $roleRows);

            $users[] = [
                'id'            => $userId,
                'name'          => Row::str($row, 'name'),
                'email'         => Row::str($row, 'email'),
                'status'        => Row::str($row, 'status'),
                'last_login_at' => Row::nullableStr($row, 'last_login_at'),
                'roles'         => $roles,
            ];
        }
        return $users;
    }

    /**
     * Update a user's profile fields (name, email, status).
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): void
    {
        $sql = "UPDATE `core_users` SET `name` = ?, `email` = ?, `status` = ? WHERE `id` = ?";
        $this->db->execute($sql, PDOParams::positional([
            Row::str($data, 'name'),
            strtolower(trim(Row::str($data, 'email'))),
            Row::str($data, 'status', 'active'),
            $id,
        ]));
    }

    /**
     * Replace a user's role assignments with the given set.
     * @param int[] $roleIds
     */
    public function syncRoles(int $userId, array $roleIds): void
    {
        $this->db->execute(
            "DELETE FROM `core_user_roles` WHERE `user_id` = ?",
            PDOParams::positional([$userId])
        );

        if (empty($roleIds)) {
            return;
        }

        $values = [];
        $params = [];
        foreach ($roleIds as $rid) {
            $values[] = "(?, ?)";
            $params[] = $userId;
            $params[] = $rid;
        }

        $sql = "INSERT INTO `core_user_roles` (`user_id`, `role_id`) VALUES " . implode(', ', $values);
        $this->db->execute($sql, PDOParams::positional($params));
    }

    /**
     * Return an array of role IDs currently assigned to a user.
     * @return int[]
     */
    public function roleIdsForUser(int $userId): array
    {
        $rows = $this->db->select(
            "SELECT `role_id` FROM `core_user_roles` WHERE `user_id` = ?",
            PDOParams::positional([$userId])
        );
        return array_map(fn($r) => Row::int($r, 'role_id'), $rows);
    }
}

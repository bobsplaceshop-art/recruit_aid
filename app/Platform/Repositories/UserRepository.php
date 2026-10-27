<?php
declare(strict_types=1);

namespace CorporateAid\Platform\Repositories;

use CorporateAid\Core\Database;
use CorporateAid\Platform\Models\User;
use CorporateAid\Platform\Support\Hasher;

/**
 * All reads/writes against the `core_users` table.
 * Nothing else in the app should contain SQL for users.
 */
final class UserRepository
{

    // ---------- scalar coercion helper (PHPStan Level 9) ----------

    private static function toInt(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function findById(int $id): ?User
    {
        $row = $this->db->selectOne(
            "SELECT * FROM `core_users` WHERE `id` = ? AND `deleted_at` IS NULL LIMIT 1",
            [$id]
        );
        return $row ? User::fromRow($row) : null;
    }

    public function findByEmail(string $email): ?User
    {
        $row = $this->db->selectOne(
            "SELECT * FROM `core_users` WHERE `email` = ? AND `deleted_at` IS NULL LIMIT 1",
            [strtolower(trim($email))]
        );
        return $row ? User::fromRow($row) : null;
    }

    /**
     * Create a new user with a hashed password. Returns the new user's ID.
     *
     * @param array{name:string,email:string,password:string,status?:string,is_client_user?:bool,organisation_id?:?int,phone?:?string,timezone?:string} $data
     */
    public function create(array $data): int
    {
        $sql = "INSERT INTO `core_users`
                (`name`, `email`, `password_hash`, `phone`, `timezone`, `status`, `is_client_user`, `organisation_id`)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

        return $this->db->insert($sql, [
            $data['name'],
            strtolower(trim($data['email'])),
            Hasher::hash($data['password']),
            $data['phone'] ?? null,
            $data['timezone'] ?? 'Africa/Nairobi',
            $data['status'] ?? 'active',
            !empty($data['is_client_user']) ? 1 : 0,
            $data['organisation_id'] ?? null,
        ]);
    }

    public function updatePassword(int $userId, string $newPassword): void
    {
        $this->db->execute(
            "UPDATE `core_users` SET `password_hash` = ? WHERE `id` = ?",
            [Hasher::hash($newPassword), $userId]
        );
    }

    public function updateLastLogin(int $userId): void
    {
        $this->db->execute(
            "UPDATE `core_users` SET `last_login_at` = NOW(), `failed_logins` = 0, `locked_until` = NULL WHERE `id` = ?",
            [$userId]
        );
    }

    public function incrementFailedLogins(int $userId, int $lockThreshold = 5, int $lockMinutes = 15): void
    {
        $this->db->execute(
            "UPDATE `core_users` SET `failed_logins` = `failed_logins` + 1 WHERE `id` = ?",
            [$userId]
        );

        $failed = self::toInt($this->db->scalar("SELECT `failed_logins` FROM `core_users` WHERE `id` = ?", [$userId]));
        if ($failed >= $lockThreshold) {
            $this->db->execute(
                "UPDATE `core_users` SET `locked_until` = DATE_ADD(NOW(), INTERVAL ? MINUTE) WHERE `id` = ?",
                [$lockMinutes, $userId]
            );
        }
    }

    public function setStatus(int $userId, string $status): void
    {
        $this->db->execute("UPDATE `core_users` SET `status` = ? WHERE `id` = ?", [$status, $userId]);
    }

    /**
     * Attach a role to a user by role slug. Idempotent.
     */
    public function attachRoleBySlug(int $userId, string $roleSlug): void
    {
        $this->db->execute(
            "INSERT IGNORE INTO `core_user_roles` (`user_id`, `role_id`)
             SELECT ?, `id` FROM `core_roles` WHERE `slug` = ?",
            [$userId, $roleSlug]
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
            [$user->id]
        );
        $user->roleSlugs = array_map(static fn ($r) => (string) $r['slug'], $rows);
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
            [$user->id]
        );
        $user->permissionSlugs = array_map(static fn ($r) => (string) $r['slug'], $rows);
        return $user;
    }

    /**
     * Count all non-deleted users.
     */
    public function count(): int
    {
        return self::toInt($this->db->scalar("SELECT COUNT(*) FROM `core_users` WHERE `deleted_at` IS NULL"));
    }

    /**
     * Return active staff users suitable for recruiter assignment.
     * Excludes client users. Returns minimal fields for dropdowns.
     *
     * @return array<int, array{id:int, name:string, email:string, role_slugs:string}>
     */
    public function listForAssignment(): array
    {
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
            [$permissionSlug]
        );
        return array_map(fn ($r) => (int) $r['id'], $rows);
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
            [$roleSlug]
        );
        return array_map(fn ($r) => (int) $r['id'], $rows);
    }
}

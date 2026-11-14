<?php
declare(strict_types=1);

namespace CorporateAid\Platform\Repositories;

use CorporateAid\Core\Database;
use CorporateAid\Core\PDOParams;
use CorporateAid\Core\Row;
use CorporateAid\Core\TenantContext;
use CorporateAid\Platform\Models\User;
use CorporateAid\Platform\Support\Hasher;
use RuntimeException;

/**
 * All reads/writes against the `core_users` table.
 * Nothing else in the app should contain SQL for users.
 */
final class UserRepository
{
    public function __construct(
        private Database $db,
        private TenantContext $tenant
    ) {}

    public function findById(int $id): ?User
    {
        // Uses homeId() to avoid locking the platform admin out when
        // they have overridden the effective tenant context.
        $row = $this->db->selectOne(
            "SELECT * FROM `core_users`
             WHERE `id` = ? AND `tenant_id` = ? AND `deleted_at` IS NULL
             LIMIT 1",
            PDOParams::positional([$id, $this->tenant->homeId()])
        );
        return $row ? User::fromRow($row) : null;
    }

    public function findByEmail(string $email): ?User
    {
        $row = $this->db->selectOne(
            "SELECT * FROM `core_users`
             WHERE `email` = ? AND `tenant_id` = ? AND `deleted_at` IS NULL
             LIMIT 1",
            PDOParams::positional([strtolower(trim($email)), $this->tenant->id()])
        );
        return $row ? User::fromRow($row) : null;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): int
    {
        $sql = "INSERT INTO `core_users`
                (`tenant_id`, `name`, `email`, `password_hash`, `phone`, `timezone`, `status`, `is_client_user`, `client_organisation_id`)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $email = Row::str($data, 'email');
        $plain = Row::str($data, 'password');

        return $this->db->insert($sql, PDOParams::positional([
            $this->tenant->id(),
            Row::str($data, 'name'),
            strtolower(trim($email)),
            Hasher::hash($plain),
            $data['phone'] ?? null,
            $data['timezone'] ?? 'Africa/Nairobi',
            $data['status'] ?? 'active',
            !empty($data['is_client_user']) ? 1 : 0,
            $data['client_organisation_id'] ?? null,
        ]));
    }

    public function updatePassword(int $userId, string $newPassword): void
    {
        $this->db->execute(
            "UPDATE `core_users` SET `password_hash` = ? WHERE `id` = ? AND `tenant_id` = ?",
            PDOParams::positional([Hasher::hash($newPassword), $userId, $this->tenant->id()])
        );
    }

    public function updateLastLogin(int $userId): void
    {
        $this->db->execute(
            "UPDATE `core_users` SET `last_login_at` = NOW(), `failed_logins` = 0, `locked_until` = NULL WHERE `id` = ? AND `tenant_id` = ?",
            PDOParams::positional([$userId, $this->tenant->id()])
        );
    }

    public function incrementFailedLogins(int $userId, int $lockThreshold = 5, int $lockMinutes = 15): void
    {
        $this->db->execute(
            "UPDATE `core_users` SET `failed_logins` = `failed_logins` + 1 WHERE `id` = ? AND `tenant_id` = ?",
            PDOParams::positional([$userId, $this->tenant->id()])
        );

        $failed = $this->db->scalarInt(
            "SELECT `failed_logins` FROM `core_users` WHERE `id` = ? AND `tenant_id` = ?",
            PDOParams::positional([$userId, $this->tenant->id()])
        );
        if ($failed >= $lockThreshold) {
            $this->db->execute(
                "UPDATE `core_users` SET `locked_until` = DATE_ADD(NOW(), INTERVAL ? MINUTE) WHERE `id` = ? AND `tenant_id` = ?",
                PDOParams::positional([$lockMinutes, $userId, $this->tenant->id()])
            );
        }
    }

    public function setStatus(int $userId, string $status): void
    {
        $this->db->execute(
            "UPDATE `core_users` SET `status` = ? WHERE `id` = ? AND `tenant_id` = ?",
            PDOParams::positional([$status, $userId, $this->tenant->id()])
        );
    }

    public function attachRoleBySlug(int $userId, string $roleSlug): void
    {
        $tenantId = $this->tenant->id();
        if ($tenantId <= 0) {
            throw new RuntimeException('An active tenant is required to assign a role.');
        }

        $this->db->execute(
            "INSERT IGNORE INTO `core_user_roles` (`tenant_id`, `user_id`, `role_id`)
             SELECT ?, ?, r.`id`
             FROM `core_roles` r
             JOIN `core_users` u
               ON u.`id` = ?
              AND u.`tenant_id` = r.`tenant_id`
             WHERE r.`tenant_id` = ?
               AND r.`module_key` = 'recruit'
               AND r.`slug` = ?",
            PDOParams::positional([$tenantId, $userId, $userId, $tenantId, $roleSlug])
        );
    }

    public function loadRoles(User $user): User
    {
        $tenantId = $this->tenant->id();
        $rows = $this->db->select(
            "SELECT r.`slug`
             FROM `core_roles` r
             JOIN `core_user_roles` ur
               ON ur.`role_id` = r.`id`
              AND ur.`tenant_id` = r.`tenant_id`
             WHERE ur.`user_id` = ?
               AND ur.`tenant_id` = ?
               AND r.`tenant_id` = ?
               AND r.`module_key` = 'recruit'
               AND (ur.`expires_at` IS NULL OR ur.`expires_at` > NOW())",
            PDOParams::positional([$user->id, $tenantId, $tenantId])
        );
        $user->roleSlugs = array_map(static fn ($r) => Row::str($r, 'slug'), $rows);
        return $user;
    }

    public function loadPermissions(User $user): User
    {
        $tenantId = $this->tenant->id();
        $rows = $this->db->select(
            "SELECT DISTINCT p.`slug`
             FROM `core_permissions` p
             JOIN `core_role_permissions` rp ON rp.`permission_id` = p.`id`
             JOIN `core_user_roles` ur ON ur.`role_id` = rp.`role_id`
             JOIN `core_roles` r
               ON r.`id` = ur.`role_id`
              AND r.`tenant_id` = ur.`tenant_id`
             WHERE ur.`user_id` = ?
               AND ur.`tenant_id` = ?
               AND r.`tenant_id` = ?
               AND r.`module_key` = 'recruit'
               AND (SUBSTRING_INDEX(p.`module`, '.', 1) IN ('recruit', 'core'))
               AND p.`is_active` = 1
               AND (ur.`expires_at` IS NULL OR ur.`expires_at` > NOW())",
            PDOParams::positional([$user->id, $tenantId, $tenantId])
        );
        $user->permissionSlugs = array_map(static fn ($r) => Row::str($r, 'slug'), $rows);
        return $user;
    }

    public function count(): int
    {
        return $this->db->scalarInt(
            "SELECT COUNT(*) FROM `core_users` WHERE `tenant_id` = ? AND `deleted_at` IS NULL",
            PDOParams::positional([$this->tenant->id()])
        );
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
             LEFT JOIN `core_user_roles` ur
               ON ur.`user_id` = u.`id`
              AND ur.`tenant_id` = u.`tenant_id`
              AND (ur.`expires_at` IS NULL OR ur.`expires_at` > NOW())
             LEFT JOIN `core_roles` r
               ON r.`id` = ur.`role_id`
              AND r.`tenant_id` = u.`tenant_id`
              AND r.`module_key` = 'recruit'
             WHERE u.`deleted_at` IS NULL
               AND u.`tenant_id` = ?
               AND u.`status` = 'active'
               AND u.`is_client_user` = 0
             GROUP BY u.`id`
             ORDER BY u.`name` ASC",
            PDOParams::positional([$this->tenant->id()])
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
             JOIN `core_user_roles` ur
               ON ur.`user_id` = u.`id`
              AND ur.`tenant_id` = u.`tenant_id`
              AND (ur.`expires_at` IS NULL OR ur.`expires_at` > NOW())
             JOIN `core_roles` r
               ON r.`id` = ur.`role_id`
              AND r.`tenant_id` = u.`tenant_id`
              AND r.`module_key` = 'recruit'
             JOIN `core_role_permissions` rp ON rp.`role_id` = r.`id`
             JOIN `core_permissions` p ON p.`id` = rp.`permission_id`
                                        AND p.`module_key` = 'recruit'
                                        AND p.`is_active` = 1
             WHERE u.`deleted_at` IS NULL
               AND u.`tenant_id` = ?
               AND u.`status` = 'active'
               AND p.`slug` = ?",
            PDOParams::positional([$this->tenant->id(), $permissionSlug])
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
             JOIN `core_user_roles` ur
               ON ur.`user_id` = u.`id`
              AND ur.`tenant_id` = u.`tenant_id`
              AND (ur.`expires_at` IS NULL OR ur.`expires_at` > NOW())
             JOIN `core_roles` r
               ON r.`id` = ur.`role_id`
              AND r.`tenant_id` = u.`tenant_id`
              AND r.`module_key` = 'recruit'
             WHERE u.`deleted_at` IS NULL
               AND u.`tenant_id` = ?
               AND u.`status` = 'active'
               AND r.`slug` = ?",
            PDOParams::positional([$this->tenant->id(), $roleSlug])
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
             WHERE `tenant_id` = ? AND `deleted_at` IS NULL
             ORDER BY `name` ASC",
            PDOParams::positional([$this->tenant->id()])
        );

        $users = [];
        foreach ($rows as $row) {
            $userId = Row::int($row, 'id');
            $roleRows = $this->db->select(
                "SELECT r.`slug`
                 FROM `core_roles` r
                 JOIN `core_user_roles` ur
                   ON ur.`role_id` = r.`id`
                  AND ur.`tenant_id` = r.`tenant_id`
                 WHERE ur.`user_id` = ?
                   AND ur.`tenant_id` = ?
                   AND r.`tenant_id` = ?
                   AND r.`module_key` = 'recruit'
                   AND (ur.`expires_at` IS NULL OR ur.`expires_at` > NOW())
                 ORDER BY r.`name` ASC",
                PDOParams::positional([$userId, $this->tenant->id(), $this->tenant->id()])
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
        $sql = "UPDATE `core_users` SET `name` = ?, `email` = ?, `status` = ? WHERE `id` = ? AND `tenant_id` = ?";
        $this->db->execute($sql, PDOParams::positional([
            Row::str($data, 'name'),
            strtolower(trim(Row::str($data, 'email'))),
            Row::str($data, 'status', 'active'),
            $id,
            $this->tenant->id(),
        ]));
    }

    /**
     * Replace a user's role assignments with the given set.
     * @param int[] $roleIds
     */
    public function syncRoles(int $userId, array $roleIds): void
    {
        $tenantId = $this->tenant->id();
        if ($tenantId <= 0) {
            throw new RuntimeException('An active tenant is required to synchronize roles.');
        }

        $userExists = $this->db->scalarInt(
            "SELECT COUNT(*) FROM `core_users`
             WHERE `id` = ? AND `tenant_id` = ? AND `deleted_at` IS NULL",
            PDOParams::positional([$userId, $tenantId])
        );
        if ($userExists !== 1) {
            throw new RuntimeException('User does not belong to the active tenant.');
        }

        $roleIds = array_values(array_unique(array_filter(
            array_map('intval', $roleIds),
            static fn (int $id): bool => $id > 0
        )));

        if ($roleIds !== []) {
            $placeholders = implode(',', array_fill(0, count($roleIds), '?'));
            $validCount = $this->db->scalarInt(
                "SELECT COUNT(*)
                 FROM `core_roles`
                 WHERE `tenant_id` = ?
                   AND `module_key` = 'recruit'
                   AND `id` IN ({$placeholders})",
                PDOParams::positional(array_merge([$tenantId], $roleIds))
            );

            if ($validCount !== count($roleIds)) {
                throw new RuntimeException('One or more roles do not belong to this tenant and application.');
            }
        }

        $this->db->execute(
            "DELETE FROM `core_user_roles`
             WHERE `tenant_id` = ? AND `user_id` = ?",
            PDOParams::positional([$tenantId, $userId])
        );

        if ($roleIds === []) {
            return;
        }

        $values = [];
        $params = [];
        foreach ($roleIds as $roleId) {
            $values[] = "(?, ?, ?)";
            array_push($params, $tenantId, $userId, $roleId);
        }

        $sql = "INSERT INTO `core_user_roles` (`tenant_id`, `user_id`, `role_id`) VALUES "
             . implode(', ', $values);
        $this->db->execute($sql, PDOParams::positional($params));
    }

    /**
     * Return an array of role IDs currently assigned to a user.
     * @return int[]
     */
    public function roleIdsForUser(int $userId): array
    {
        $rows = $this->db->select(
            "SELECT `role_id`
             FROM `core_user_roles`
             WHERE `user_id` = ? AND `tenant_id` = ?",
            PDOParams::positional([$userId, $this->tenant->id()])
        );
        return array_map(fn($r) => Row::int($r, 'role_id'), $rows);
    }

    /**
     * Return all active users assigned to a role, with name/email.
     * @return array<int, array{id:int, name:string, email:string}>
     */
    public function findUsersByRoleId(int $roleId): array
    {
        $rows = $this->db->select(
            "SELECT u.`id`, u.`name`, u.`email`
             FROM `core_users` u
             JOIN `core_user_roles` ur
               ON ur.`user_id` = u.`id`
              AND ur.`tenant_id` = u.`tenant_id`
             JOIN `core_roles` r
               ON r.`id` = ur.`role_id`
              AND r.`tenant_id` = ur.`tenant_id`
              AND r.`module_key` = 'recruit'
             WHERE ur.`role_id` = ?
               AND ur.`tenant_id` = ?
               AND (ur.`expires_at` IS NULL OR ur.`expires_at` > NOW())
               AND u.`tenant_id` = ?
               AND u.`deleted_at` IS NULL
             ORDER BY u.`name` ASC",
            PDOParams::positional([$roleId, $this->tenant->id(), $this->tenant->id()])
        );

        return array_map(static fn(array $r) => [
            'id'    => Row::int($r, 'id'),
            'name'  => Row::str($r, 'name'),
            'email' => Row::str($r, 'email'),
        ], $rows);
    }
    public function getDefaultApplication(int $userId): ?string
    {
        $row = $this->db->selectOne(
            "SELECT `module_key` FROM `core_user_applications` WHERE `user_id` = ? AND `is_default` = 1 LIMIT 1",
            PDOParams::positional([$userId])
        );
        return $row ? \CorporateAid\Core\Row::str($row, 'module_key') : null;
    }

    /** @return string[] */
    public function getAssignedApplications(int $userId): array
    {
        $rows = $this->db->select(
            "SELECT `module_key` FROM `core_user_applications` WHERE `user_id` = ?",
            PDOParams::positional([$userId])
        );
        return array_map(static fn($r) => \CorporateAid\Core\Row::str($r, 'module_key'), $rows);
    }

}

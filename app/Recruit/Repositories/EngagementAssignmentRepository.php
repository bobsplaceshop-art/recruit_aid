<?php
declare(strict_types=1);

namespace CorporateAid\Recruit\Repositories;

use CorporateAid\Core\Database;
use CorporateAid\Core\PDOParams;
use CorporateAid\Core\Row;
use CorporateAid\Core\TenantContext;
use CorporateAid\Recruit\Models\EngagementAssignment;

final class EngagementAssignmentRepository
{
    public function __construct(
        private Database $db,
        private TenantContext $tenant
    ) {}

    private function tenantId(): int
    {
        return $this->tenant->id();
    }

    private function assertTenantEngagement(int $engagementId): void
    {
        if ($engagementId <= 0) {
            throw new \InvalidArgumentException('Invalid engagement.');
        }

        $row = $this->db->selectOne(
            "SELECT `id` FROM `recruit_engagements`
             WHERE `id` = ? AND `tenant_id` = ? AND `deleted_at` IS NULL
             LIMIT 1",
            PDOParams::positional([$engagementId, $this->tenantId()])
        );

        if (!$row) {
            throw new \InvalidArgumentException('Engagement not found.');
        }
    }

    private function assertTenantUser(int $userId, string $field): void
    {
        if ($userId <= 0) {
            throw new \InvalidArgumentException("Invalid {$field}.");
        }

        $row = $this->db->selectOne(
            "SELECT `id` FROM `core_users`
             WHERE `id` = ? AND `tenant_id` = ?
               AND `status` = 'active' AND `deleted_at` IS NULL
             LIMIT 1",
            PDOParams::positional([$userId, $this->tenantId()])
        );

        if (!$row) {
            throw new \InvalidArgumentException("{$field} must be an active user in the current tenant.");
        }
    }

    /** @return EngagementAssignment[] */
    public function forEngagement(int $engagementId): array
    {
        $this->assertTenantEngagement($engagementId);

        $rows = $this->db->select(
            "SELECT * FROM `recruit_engagement_assignments`
             WHERE `engagement_id` = ? AND `tenant_id` = ?
             ORDER BY `assignee_type` ASC, `assignee_value` ASC",
            PDOParams::positional([$engagementId, $this->tenantId()])
        );

        $assignments = array_map([EngagementAssignment::class, 'fromRow'], $rows);

        foreach ($assignments as $assignment) {
            $assignment->assigneeLabel = $this->resolveLabel($assignment);
        }

        return $assignments;
    }

    public function assign(
        int $engagementId,
        string $type,
        string $value,
        bool $canView,
        bool $canEdit,
        ?int $assignedBy
    ): void {
        $this->assertTenantEngagement($engagementId);

        $type = trim($type);
        $value = trim($value);

        if (!in_array($type, ['user', 'role', 'permission'], true)) {
            throw new \InvalidArgumentException('Invalid assignment type.');
        }

        if ($value === '' || strlen($value) > 120) {
            throw new \InvalidArgumentException('Invalid assignment value.');
        }

        if ($type === 'user') {
            if (!ctype_digit($value)) {
                throw new \InvalidArgumentException('User assignment must contain a valid user ID.');
            }
            $this->assertTenantUser((int) $value, 'Assigned user');
            $value = (string) (int) $value;
        } elseif ($type === 'role') {
            $exists = $this->db->scalarInt(
                "SELECT COUNT(*) FROM `core_roles` WHERE `slug` = ?",
                PDOParams::positional([$value])
            );
            if ($exists < 1) {
                throw new \InvalidArgumentException('Role not found.');
            }
        } else {
            $exists = $this->db->scalarInt(
                "SELECT COUNT(*) FROM `core_permissions` WHERE `slug` = ?",
                PDOParams::positional([$value])
            );
            if ($exists < 1) {
                throw new \InvalidArgumentException('Permission not found.');
            }
        }

        if ($assignedBy !== null) {
            $this->assertTenantUser($assignedBy, 'Assigning user');
        }

        $this->db->execute(
            "INSERT INTO `recruit_engagement_assignments`
                (`tenant_id`, `engagement_id`, `assignee_type`, `assignee_value`,
                 `can_view`, `can_edit`, `assigned_by`)
             VALUES (?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                `can_view` = VALUES(`can_view`),
                `can_edit` = VALUES(`can_edit`),
                `assigned_by` = VALUES(`assigned_by`)",
            PDOParams::positional([
                $this->tenantId(),
                $engagementId,
                $type,
                $value,
                (int) $canView,
                (int) $canEdit,
                $assignedBy,
            ])
        );
    }

    public function remove(int $id, int $engagementId): void
    {
        $this->assertTenantEngagement($engagementId);

        if ($id <= 0) {
            throw new \InvalidArgumentException('Invalid assignment.');
        }

        $this->db->execute(
            "DELETE FROM `recruit_engagement_assignments`
             WHERE `id` = ? AND `engagement_id` = ? AND `tenant_id` = ?",
            PDOParams::positional([$id, $engagementId, $this->tenantId()])
        );
    }

    /** @return int[] */
    public function resolveUserIds(int $engagementId): array
    {
        $assignments = $this->forEngagement($engagementId);
        $userIds = [];
        $tenantId = $this->tenantId();

        foreach ($assignments as $assignment) {
            if ($assignment->assigneeType === 'user') {
                $userId = (int) $assignment->assigneeValue;

                $row = $this->db->selectOne(
                    "SELECT `id` FROM `core_users`
                     WHERE `id` = ? AND `tenant_id` = ?
                       AND `status` = 'active' AND `deleted_at` IS NULL
                     LIMIT 1",
                    PDOParams::positional([$userId, $tenantId])
                );

                if ($row) {
                    $userIds[] = Row::int($row, 'id');
                }
            } elseif ($assignment->assigneeType === 'role') {
                $rows = $this->db->select(
                    "SELECT DISTINCT u.`id`
                     FROM `core_users` u
                     JOIN `core_user_roles` ur ON ur.`user_id` = u.`id`
                     JOIN `core_roles` r ON r.`id` = ur.`role_id`
                     WHERE u.`tenant_id` = ?
                       AND u.`deleted_at` IS NULL
                       AND u.`status` = 'active'
                       AND (ur.`expires_at` IS NULL OR ur.`expires_at` > NOW())
                       AND r.`slug` = ?",
                    PDOParams::positional([$tenantId, $assignment->assigneeValue])
                );

                foreach ($rows as $row) {
                    $userIds[] = Row::int($row, 'id');
                }
            } elseif ($assignment->assigneeType === 'permission') {
                $rows = $this->db->select(
                    "SELECT DISTINCT u.`id`
                     FROM `core_users` u
                     JOIN `core_user_roles` ur ON ur.`user_id` = u.`id`
                     JOIN `core_role_permissions` rp ON rp.`role_id` = ur.`role_id`
                     JOIN `core_permissions` p ON p.`id` = rp.`permission_id`
                     WHERE u.`tenant_id` = ?
                       AND u.`deleted_at` IS NULL
                       AND u.`status` = 'active'
                       AND (ur.`expires_at` IS NULL OR ur.`expires_at` > NOW())
                       AND p.`slug` = ?",
                    PDOParams::positional([$tenantId, $assignment->assigneeValue])
                );

                foreach ($rows as $row) {
                    $userIds[] = Row::int($row, 'id');
                }
            }
        }

        return array_values(array_unique($userIds));
    }

    private function resolveLabel(EngagementAssignment $assignment): string
    {
        if ($assignment->assigneeType === 'user') {
            $name = $this->db->scalarString(
                "SELECT `name` FROM `core_users`
                 WHERE `id` = ? AND `tenant_id` = ?
                   AND `deleted_at` IS NULL",
                PDOParams::positional([(int) $assignment->assigneeValue, $this->tenantId()])
            );

            return $name !== '' ? $name : "User #{$assignment->assigneeValue}";
        }

        if ($assignment->assigneeType === 'role') {
            $name = $this->db->scalarString(
                "SELECT `name` FROM `core_roles` WHERE `slug` = ?",
                PDOParams::positional([$assignment->assigneeValue])
            );

            return $name !== '' ? $name : $assignment->assigneeValue;
        }

        if ($assignment->assigneeType === 'permission') {
            $slug = $this->db->scalarString(
                "SELECT `slug` FROM `core_permissions` WHERE `slug` = ?",
                PDOParams::positional([$assignment->assigneeValue])
            );

            return $slug !== '' ? $slug : $assignment->assigneeValue;
        }

        return $assignment->assigneeValue;
    }
}

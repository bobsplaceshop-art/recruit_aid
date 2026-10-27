<?php
declare(strict_types=1);

namespace CorporateAid\Recruit\Repositories;

use CorporateAid\Core\Database;
use CorporateAid\Recruit\Models\EngagementAssignment;

final class EngagementAssignmentRepository
{
    public function __construct(private Database $db) {}

    /**
     * Return assignments with human labels resolved (role names, user names, permission labels).
     * @return EngagementAssignment[]
     */
    public function forEngagement(int $engagementId): array
    {
        $rows = $this->db->select(
            "SELECT * FROM `recruit_engagement_assignments`
             WHERE `engagement_id` = ?
             ORDER BY `assignee_type` ASC, `assignee_value` ASC",
            [$engagementId]
        );

        $assignments = array_map([EngagementAssignment::class, 'fromRow'], $rows);

        foreach ($assignments as $a) {
            $a->assigneeLabel = $this->resolveLabel($a);
        }

        return $assignments;
    }

    public function assign(int $engagementId, string $type, string $value, bool $canView, bool $canEdit, ?int $assignedBy): void
    {
        $this->db->execute(
            "INSERT INTO `recruit_engagement_assignments`
                (`engagement_id`, `assignee_type`, `assignee_value`, `can_view`, `can_edit`, `assigned_by`)
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                `can_view` = VALUES(`can_view`),
                `can_edit` = VALUES(`can_edit`),
                `assigned_by` = VALUES(`assigned_by`)",
            [$engagementId, $type, $value, (int) $canView, (int) $canEdit, $assignedBy]
        );
    }

    public function remove(int $id, int $engagementId): void
    {
        $this->db->execute(
            "DELETE FROM `recruit_engagement_assignments` WHERE `id` = ? AND `engagement_id` = ?",
            [$id, $engagementId]
        );
    }

    /**
     * Return the user IDs who should have access to an engagement (and children).
     * Resolves role, permission, and user assignees into a flat list.
     * @return int[]
     */
    public function resolveUserIds(int $engagementId): array
    {
        $assignments = $this->forEngagement($engagementId);
        $userIds = [];

        foreach ($assignments as $a) {
            if ($a->assigneeType === 'user') {
                $userIds[] = (int) $a->assigneeValue;
            } elseif ($a->assigneeType === 'role') {
                $rows = $this->db->select(
                    "SELECT DISTINCT u.`id`
                     FROM `core_users` u
                     JOIN `core_user_roles` ur ON ur.`user_id` = u.`id`
                     JOIN `core_roles` r ON r.`id` = ur.`role_id`
                     WHERE u.`deleted_at` IS NULL AND u.`status` = 'active'
                       AND r.`slug` = ?",
                    [$a->assigneeValue]
                );
                foreach ($rows as $r) $userIds[] = (int) $r['id'];
            } elseif ($a->assigneeType === 'permission') {
                $rows = $this->db->select(
                    "SELECT DISTINCT u.`id`
                     FROM `core_users` u
                     JOIN `core_user_roles` ur ON ur.`user_id` = u.`id`
                     JOIN `core_role_permissions` rp ON rp.`role_id` = ur.`role_id`
                     JOIN `core_permissions` p ON p.`id` = rp.`permission_id`
                     WHERE u.`deleted_at` IS NULL AND u.`status` = 'active'
                       AND p.`slug` = ?",
                    [$a->assigneeValue]
                );
                foreach ($rows as $r) $userIds[] = (int) $r['id'];
            }
        }

        return array_values(array_unique($userIds));
    }

    // ---------- helpers ----------

    private function resolveLabel(EngagementAssignment $a): string
    {
        if ($a->assigneeType === 'user') {
            $name = $this->db->scalar("SELECT `name` FROM `core_users` WHERE `id` = ?", [(int) $a->assigneeValue]);
            return $name !== null && $name !== false ? (string) $name : "User #{$a->assigneeValue}";
        }
        if ($a->assigneeType === 'role') {
            $name = $this->db->scalar("SELECT `name` FROM `core_roles` WHERE `slug` = ?", [$a->assigneeValue]);
            return $name !== null && $name !== false ? (string) $name : $a->assigneeValue;
        }
        if ($a->assigneeType === 'permission') {
            $slug = $this->db->scalar("SELECT `slug` FROM `core_permissions` WHERE `slug` = ?", [$a->assigneeValue]);
            return $slug !== null && $slug !== false ? (string) $slug : $a->assigneeValue;
        }
        return $a->assigneeValue;
    }
}

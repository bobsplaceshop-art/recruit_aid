<?php
declare(strict_types=1);

namespace CorporateAid\Recruit\Repositories;

use CorporateAid\Core\Database;
use CorporateAid\Core\PDOParams;
use CorporateAid\Recruit\Models\EngagementAssignment;
use CorporateAid\Core\Row;

final class EngagementAssignmentRepository
{
    public function __construct(private Database $db) {}

    /** @return EngagementAssignment[] */
    public function forEngagement(int $engagementId): array
    {
        $rows = $this->db->select(
            "SELECT * FROM `recruit_engagement_assignments`
             WHERE `engagement_id` = ?
             ORDER BY `assignee_type` ASC, `assignee_value` ASC",
            PDOParams::positional([$engagementId])
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
            PDOParams::positional([$engagementId, $type, $value, (int) $canView, (int) $canEdit, $assignedBy])
        );
    }

    public function remove(int $id, int $engagementId): void
    {
        $this->db->execute(
            "DELETE FROM `recruit_engagement_assignments` WHERE `id` = ? AND `engagement_id` = ?",
            PDOParams::positional([$id, $engagementId])
        );
    }

    /** @return int[] */
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
                    PDOParams::positional([$a->assigneeValue])
                );
                foreach ($rows as $r) $userIds[] = Row::int($r, 'id');
            } elseif ($a->assigneeType === 'permission') {
                $rows = $this->db->select(
                    "SELECT DISTINCT u.`id`
                     FROM `core_users` u
                     JOIN `core_user_roles` ur ON ur.`user_id` = u.`id`
                     JOIN `core_role_permissions` rp ON rp.`role_id` = ur.`role_id`
                     JOIN `core_permissions` p ON p.`id` = rp.`permission_id`
                     WHERE u.`deleted_at` IS NULL AND u.`status` = 'active'
                       AND p.`slug` = ?",
                    PDOParams::positional([$a->assigneeValue])
                );
                foreach ($rows as $r) $userIds[] = Row::int($r, 'id');
            }
        }

        return array_values(array_unique($userIds));
    }

    private function resolveLabel(EngagementAssignment $a): string
    {
        if ($a->assigneeType === 'user') {
            $name = $this->db->scalarString(
                "SELECT `name` FROM `core_users` WHERE `id` = ?",
                PDOParams::positional([(int) $a->assigneeValue])
            );
            return $name !== '' ? $name : "User #{$a->assigneeValue}";
        }
        if ($a->assigneeType === 'role') {
            $name = $this->db->scalarString(
                "SELECT `name` FROM `core_roles` WHERE `slug` = ?",
                PDOParams::positional([$a->assigneeValue])
            );
            return $name !== '' ? $name : $a->assigneeValue;
        }
        if ($a->assigneeType === 'permission') {
            $slug = $this->db->scalarString(
                "SELECT `slug` FROM `core_permissions` WHERE `slug` = ?",
                PDOParams::positional([$a->assigneeValue])
            );
            return $slug !== '' ? $slug : $a->assigneeValue;
        }
        return $a->assigneeValue;
    }
}

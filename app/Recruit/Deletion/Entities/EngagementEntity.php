<?php
declare(strict_types=1);

namespace CorporateAid\Recruit\Deletion\Entities;

use CorporateAid\Core\Database;
use CorporateAid\Recruit\Models\Engagement;
use CorporateAid\Platform\Deletion\DeletableEntity;

final class EngagementEntity implements DeletableEntity
{
    public function __construct(
        private Engagement $engagement,
        private Database $db
    ) {
    }

    public function entityType(): string
    {
        return 'engagement';
    }

    public function entityId(): int
    {
        return $this->engagement->id;
    }

    public function entityLabel(): string
    {
        return $this->engagement->name . ' (' . $this->engagement->code . ')';
    }

    public function getDependents(): array
    {
        $dependents = [];

        // Assigned recruiters
        $recruiterCount = (int) ($this->db->scalar(
            "SELECT COUNT(*) FROM `recruit_engagement_recruiters` WHERE `engagement_id` = ?",
            [$this->engagement->id]
        ) ?? 0);
        if ($recruiterCount > 0) {
            $dependents[] = [
                'label' => 'Assigned recruiter' . ($recruiterCount === 1 ? '' : 's'),
                'count' => $recruiterCount,
                'route' => '#',
            ];
        }

        // Custom field values
        $cfCount = (int) ($this->db->scalar(
            "SELECT COUNT(*) FROM `core_custom_field_values`
             WHERE `entity_type` = 'engagement' AND `entity_id` = ?",
            [$this->engagement->id]
        ) ?? 0);
        if ($cfCount > 0) {
            $dependents[] = [
                'label' => 'Custom field value' . ($cfCount === 1 ? '' : 's'),
                'count' => $cfCount,
                'route' => '#',
            ];
        }

        // Vacancies (future)
        $vacancyCount = 0;
        try {
            $vacancyCount = (int) ($this->db->scalar(
                "SELECT COUNT(*) FROM `recruit_vacancies` WHERE `engagement_id` = ? AND `deleted_at` IS NULL",
                [$this->engagement->id]
            ) ?? 0);
        } catch (\Throwable) {
            // vacancies table doesn't exist yet
        }
        if ($vacancyCount > 0) {
            $dependents[] = [
                'label' => 'Vacanc' . ($vacancyCount === 1 ? 'y' : 'ies'),
                'count' => $vacancyCount,
                'route' => '#',
            ];
        }

        return $dependents;
    }

    public function cascadeDelete(): int
    {
        $deleted = 0;

        // engagement_recruiters (cascade via FK, but explicit for count)
        $deleted += $this->db->execute(
            "DELETE FROM `recruit_engagement_recruiters` WHERE `engagement_id` = ?",
            [$this->engagement->id]
        );

        // custom field values
        $deleted += $this->db->execute(
            "DELETE FROM `core_custom_field_values` WHERE `entity_type` = 'engagement' AND `entity_id` = ?",
            [$this->engagement->id]
        );

        // The engagement itself
        $deleted += $this->db->execute(
            "DELETE FROM `recruit_engagements` WHERE `id` = ?",
            [$this->engagement->id]
        );

        return $deleted;
    }

    public function archive(): void
    {
        $this->db->execute(
            "UPDATE `recruit_engagements` SET `deleted_at` = NOW() WHERE `id` = ?",
            [$this->engagement->id]
        );
    }

    public function restore(): void
    {
        $this->db->execute(
            "UPDATE `recruit_engagements` SET `deleted_at` = NULL WHERE `id` = ?",
            [$this->engagement->id]
        );
    }
}

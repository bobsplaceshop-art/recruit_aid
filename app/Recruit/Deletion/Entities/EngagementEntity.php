<?php
declare(strict_types=1);

namespace CorporateAid\Recruit\Deletion\Entities;

use CorporateAid\Core\Database;
use CorporateAid\Core\PDOParams;
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

    /** @return array<int, array{label:string, count:int, route:string}> */
    public function getDependents(): array
    {
        $dependents = [];

        $recruiterCount = $this->db->scalarInt(
            "SELECT COUNT(*) FROM `recruit_engagement_recruiters` WHERE `engagement_id` = ?",
            PDOParams::positional([$this->engagement->id])
        );
        if ($recruiterCount > 0) {
            $dependents[] = [
                'label' => 'Assigned recruiter' . ($recruiterCount === 1 ? '' : 's'),
                'count' => $recruiterCount,
                'route' => '#',
            ];
        }

        $cfCount = $this->db->scalarInt(
            "SELECT COUNT(*) FROM `core_custom_field_values`
             WHERE `entity_type` = 'engagement' AND `entity_id` = ?",
            PDOParams::positional([$this->engagement->id])
        );
        if ($cfCount > 0) {
            $dependents[] = [
                'label' => 'Custom field value' . ($cfCount === 1 ? '' : 's'),
                'count' => $cfCount,
                'route' => '#',
            ];
        }

        $vacancyCount = 0;
        try {
            $vacancyCount = $this->db->scalarInt(
                "SELECT COUNT(*) FROM `recruit_vacancies` WHERE `engagement_id` = ? AND `deleted_at` IS NULL",
                PDOParams::positional([$this->engagement->id])
            );
        } catch (\Throwable) {
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

        $deleted += $this->db->execute(
            "DELETE FROM `recruit_engagement_recruiters` WHERE `engagement_id` = ?",
            PDOParams::positional([$this->engagement->id])
        );

        $deleted += $this->db->execute(
            "DELETE FROM `core_custom_field_values` WHERE `entity_type` = 'engagement' AND `entity_id` = ?",
            PDOParams::positional([$this->engagement->id])
        );

        $deleted += $this->db->execute(
            "DELETE FROM `recruit_engagements` WHERE `id` = ?",
            PDOParams::positional([$this->engagement->id])
        );

        return $deleted;
    }

    public function archive(): void
    {
        $this->db->execute(
            "UPDATE `recruit_engagements` SET `deleted_at` = NOW() WHERE `id` = ?",
            PDOParams::positional([$this->engagement->id])
        );
    }

    public function restore(): void
    {
        $this->db->execute(
            "UPDATE `recruit_engagements` SET `deleted_at` = NULL WHERE `id` = ?",
            PDOParams::positional([$this->engagement->id])
        );
    }
}

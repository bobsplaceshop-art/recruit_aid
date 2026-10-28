<?php
declare(strict_types=1);

namespace CorporateAid\Recruit\Deletion\Entities;

use CorporateAid\Core\Database;
use CorporateAid\Core\PDOParams;
use CorporateAid\Recruit\Models\Vacancy;
use CorporateAid\Platform\Deletion\DeletableEntity;

final class VacancyEntity implements DeletableEntity
{
    public function __construct(
        private Vacancy $vacancy,
        private Database $db
    ) {
    }

    public function entityType(): string { return 'vacancy'; }
    public function entityId(): int { return $this->vacancy->id; }
    public function entityLabel(): string
    {
        return $this->vacancy->title . ' (' . $this->vacancy->code . ')';
    }

    /** @return array<int, array{label:string, count:int, route:string}> */
    public function getDependents(): array
    {
        $dependents = [];

        $stageCount = $this->db->scalarInt(
            "SELECT COUNT(*) FROM `recruit_vacancy_stages` WHERE `vacancy_id` = ?",
            PDOParams::positional([$this->vacancy->id])
        );
        if ($stageCount > 0) {
            $dependents[] = [
                'label' => 'Pipeline stage' . ($stageCount === 1 ? '' : 's'),
                'count' => $stageCount,
                'route' => '#',
            ];
        }

        $reqCount = $this->db->scalarInt(
            "SELECT COUNT(*) FROM `recruit_vacancy_requirements` WHERE `vacancy_id` = ?",
            PDOParams::positional([$this->vacancy->id])
        );
        if ($reqCount > 0) {
            $dependents[] = [
                'label' => 'Requirement item' . ($reqCount === 1 ? '' : 's'),
                'count' => $reqCount,
                'route' => '#',
            ];
        }

        $cfCount = $this->db->scalarInt(
            "SELECT COUNT(*) FROM `core_custom_field_values`
             WHERE `entity_type` = 'vacancy' AND `entity_id` = ?",
            PDOParams::positional([$this->vacancy->id])
        );
        if ($cfCount > 0) {
            $dependents[] = [
                'label' => 'Custom field value' . ($cfCount === 1 ? '' : 's'),
                'count' => $cfCount,
                'route' => '#',
            ];
        }

        $appCount = 0;
        try {
            $appCount = $this->db->scalarInt(
                "SELECT COUNT(*) FROM `applications` WHERE `vacancy_id` = ? AND `deleted_at` IS NULL",
                PDOParams::positional([$this->vacancy->id])
            );
        } catch (\Throwable) {
        }
        if ($appCount > 0) {
            $dependents[] = [
                'label' => 'Application' . ($appCount === 1 ? '' : 's'),
                'count' => $appCount,
                'route' => '#',
            ];
        }

        return $dependents;
    }

    public function cascadeDelete(): int
    {
        $deleted = 0;

        $deleted += $this->db->execute(
            "DELETE FROM `recruit_vacancy_requirements` WHERE `vacancy_id` = ?",
            PDOParams::positional([$this->vacancy->id])
        );

        $deleted += $this->db->execute(
            "DELETE FROM `recruit_vacancy_stages` WHERE `vacancy_id` = ?",
            PDOParams::positional([$this->vacancy->id])
        );

        $deleted += $this->db->execute(
            "DELETE FROM `core_custom_field_values` WHERE `entity_type` = 'vacancy' AND `entity_id` = ?",
            PDOParams::positional([$this->vacancy->id])
        );

        $deleted += $this->db->execute(
            "DELETE FROM `recruit_vacancies` WHERE `id` = ?",
            PDOParams::positional([$this->vacancy->id])
        );

        return $deleted;
    }

    public function archive(): void
    {
        $this->db->execute(
            "UPDATE `recruit_vacancies` SET `deleted_at` = NOW() WHERE `id` = ?",
            PDOParams::positional([$this->vacancy->id])
        );
    }

    public function restore(): void
    {
        $this->db->execute(
            "UPDATE `recruit_vacancies` SET `deleted_at` = NULL WHERE `id` = ?",
            PDOParams::positional([$this->vacancy->id])
        );
    }
}

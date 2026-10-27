<?php
declare(strict_types=1);

namespace CorporateAid\Recruit\Repositories;

use CorporateAid\Core\Database;
use CorporateAid\Recruit\Models\VacancyStage;

final class VacancyStageRepository
{
    public function __construct(private Database $db) {}

    /** @return VacancyStage[] */
    public function forVacancy(int $vacancyId): array
    {
        $rows = $this->db->select(
            "SELECT * FROM `recruit_vacancy_stages`
             WHERE `vacancy_id` = ?
             ORDER BY `sort_order` ASC, `id` ASC",
            [$vacancyId]
        );
        return array_map([VacancyStage::class, 'fromRow'], $rows);
    }

    public function findById(int $id): ?VacancyStage
    {
        $row = $this->db->selectOne("SELECT * FROM `recruit_vacancy_stages` WHERE `id` = ? LIMIT 1", [$id]);
        return $row ? VacancyStage::fromRow($row) : null;
    }

    /**
     * Clone the default pipeline_stages into vacancy_stages for a new vacancy.
     */
    public function cloneDefaultsFor(int $vacancyId): void
    {
        $defaults = $this->db->select(
            "SELECT `key_slug`, `label`, `sort_order`, `is_terminal`, `is_default`, `color`
             FROM `recruit_pipeline_stages`
             WHERE `organisation_id` IS NULL AND `deleted_at` IS NULL
             ORDER BY `sort_order` ASC"
        );

        foreach ($defaults as $d) {
            $this->db->execute(
                "INSERT INTO `recruit_vacancy_stages`
                    (`vacancy_id`, `key_slug`, `label`, `sort_order`, `is_terminal`, `is_default`, `color`)
                 VALUES (?, ?, ?, ?, ?, ?, ?)",
                [
                    $vacancyId,
                    $d['key_slug'],
                    $d['label'],
                    (int) $d['sort_order'],
                    (int) $d['is_terminal'],
                    (int) $d['is_default'],
                    $d['color'],
                ]
            );
        }
    }

    /**
     * Clone defaults for one organisation (using org-specific overrides if they exist).
     */
    public function cloneForOrganisation(int $vacancyId, ?int $organisationId): void
    {
        // Get org-specific overrides if any, else defaults
        $stages = $this->db->select(
            "SELECT `key_slug`, `label`, `sort_order`, `is_terminal`, `is_default`, `color`
             FROM `recruit_pipeline_stages`
             WHERE `deleted_at` IS NULL
               AND (`organisation_id` IS NULL OR `organisation_id` = ?)
             ORDER BY `sort_order` ASC",
            [$organisationId]
        );

        // Merge (org-specific wins)
        $byKey = [];
        foreach ($stages as $s) {
            if (!isset($byKey[$s['key_slug']]) || $s['organisation_id'] !== null) {
                $byKey[$s['key_slug']] = $s;
            }
        }

        usort($byKey, fn ($a, $b) => ($a['sort_order'] <=> $b['sort_order']));

        foreach ($byKey as $d) {
            $this->db->execute(
                "INSERT INTO `recruit_vacancy_stages`
                    (`vacancy_id`, `key_slug`, `label`, `sort_order`, `is_terminal`, `is_default`, `color`)
                 VALUES (?, ?, ?, ?, ?, ?, ?)",
                [
                    $vacancyId,
                    $d['key_slug'],
                    $d['label'],
                    (int) $d['sort_order'],
                    (int) $d['is_terminal'],
                    (int) $d['is_default'],
                    $d['color'] ?? null,
                ]
            );
        }
    }
}

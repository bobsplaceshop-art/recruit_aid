<?php
declare(strict_types=1);

namespace CorporateAid\Recruit\Repositories;

use CorporateAid\Core\Database;
use CorporateAid\Core\PDOParams;
use CorporateAid\Core\Row;
use CorporateAid\Core\TenantContext;
use CorporateAid\Recruit\Models\VacancyStage;

final class VacancyStageRepository
{
    public function __construct(
        private Database $db,
        private TenantContext $tenant
    ) {}

    /** @return VacancyStage[] */
    public function forVacancy(int $vacancyId): array
    {
        $rows = $this->db->select(
            "SELECT * FROM `recruit_vacancy_stages`
             WHERE `vacancy_id` = ? AND `tenant_id` = ?
             ORDER BY `sort_order` ASC, `id` ASC",
            PDOParams::positional([$vacancyId, $this->tenant->id()])
        );
        return array_map([VacancyStage::class, 'fromRow'], $rows);
    }

    public function findById(int $id): ?VacancyStage
    {
        $row = $this->db->selectOne(
            "SELECT * FROM `recruit_vacancy_stages` WHERE `id` = ? AND `tenant_id` = ? LIMIT 1",
            PDOParams::positional([$id, $this->tenant->id()])
        );
        return $row ? VacancyStage::fromRow($row) : null;
    }

    public function cloneDefaultsFor(int $vacancyId): void
    {
        $defaults = $this->db->select(
            "SELECT `key_slug`, `label`, `sort_order`, `is_terminal`, `is_default`, `color`
             FROM `recruit_pipeline_stages`
             WHERE `tenant_id` = ? AND `organisation_id` IS NULL AND `deleted_at` IS NULL
             ORDER BY `sort_order` ASC",
            PDOParams::positional([$this->tenant->id()])
        );

        foreach ($defaults as $d) {
            $this->db->execute(
                "INSERT INTO `recruit_vacancy_stages`
                    (`tenant_id`, `vacancy_id`, `key_slug`, `label`, `sort_order`, `is_terminal`, `is_default`, `color`)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                PDOParams::positional([
                    $this->tenant->id(),
                    $vacancyId,
                    Row::str($d, 'key_slug'),
                    Row::str($d, 'label'),
                    Row::int($d, 'sort_order'),
                    Row::int($d, 'is_terminal'),
                    Row::int($d, 'is_default'),
                    Row::nullableStr($d, 'color'),
                ])
            );
        }
    }

    public function cloneForOrganisation(int $vacancyId, ?int $organisationId): void
    {
        $stages = $this->db->select(
            "SELECT `key_slug`, `label`, `sort_order`, `is_terminal`, `is_default`, `color`
             FROM `recruit_pipeline_stages`
             WHERE `tenant_id` = ?
               AND `deleted_at` IS NULL
               AND (`organisation_id` IS NULL OR `organisation_id` = ?)
             ORDER BY `sort_order` ASC",
            PDOParams::positional([$this->tenant->id(), $organisationId])
        );

        $byKey = [];
        foreach ($stages as $s) {
            $key = Row::str($s, 'key_slug');
            $orgId = Row::nullableInt($s, 'organisation_id');
            if (!isset($byKey[$key]) || $orgId !== null) {
                $byKey[$key] = $s;
            }
        }

        usort($byKey, fn ($a, $b) => Row::int($a, 'sort_order') <=> Row::int($b, 'sort_order'));

        foreach ($byKey as $d) {
            $this->db->execute(
                "INSERT INTO `recruit_vacancy_stages`
                    (`tenant_id`, `vacancy_id`, `key_slug`, `label`, `sort_order`, `is_terminal`, `is_default`, `color`)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                PDOParams::positional([
                    $this->tenant->id(),
                    $vacancyId,
                    Row::str($d, 'key_slug'),
                    Row::str($d, 'label'),
                    Row::int($d, 'sort_order'),
                    Row::int($d, 'is_terminal'),
                    Row::int($d, 'is_default'),
                    Row::nullableStr($d, 'color'),
                ])
            );
        }
    }
}

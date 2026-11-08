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

    private function tenantId(): int
    {
        return $this->tenant->id();
    }

    /**
     * Verify vacancy ownership and return its client organisation ID.
     */
    private function assertTenantVacancy(int $vacancyId): int
    {
        if ($vacancyId <= 0) {
            throw new \InvalidArgumentException('Vacancy ID must be positive.');
        }

        $row = $this->db->selectOne(
            "SELECT `client_organisation_id`
             FROM `recruit_vacancies`
             WHERE `id` = ?
               AND `tenant_id` = ?
               AND `deleted_at` IS NULL
             LIMIT 1",
            PDOParams::positional([$vacancyId, $this->tenantId()])
        );

        if ($row === null) {
            throw new \InvalidArgumentException(
                'The selected vacancy does not belong to the active tenant or is unavailable.'
            );
        }

        return Row::int($row, 'client_organisation_id');
    }

    private function assertTenantOrganisation(int $organisationId): void
    {
        if ($organisationId <= 0) {
            throw new \InvalidArgumentException('Organisation ID must be positive.');
        }

        $exists = $this->db->scalarInt(
            "SELECT COUNT(*)
             FROM `recruit_client_organisations`
             WHERE `id` = ?
               AND `tenant_id` = ?
               AND `deleted_at` IS NULL",
            PDOParams::positional([$organisationId, $this->tenantId()])
        );

        if ($exists !== 1) {
            throw new \InvalidArgumentException(
                'The selected organisation does not belong to the active tenant or is unavailable.'
            );
        }
    }

    /** @return VacancyStage[] */
    public function forVacancy(int $vacancyId): array
    {
        $this->assertTenantVacancy($vacancyId);

        $rows = $this->db->select(
            "SELECT vs.*
             FROM `recruit_vacancy_stages` vs
             INNER JOIN `recruit_vacancies` v
                ON v.`id` = vs.`vacancy_id`
               AND v.`tenant_id` = vs.`tenant_id`
             WHERE vs.`vacancy_id` = ?
               AND vs.`tenant_id` = ?
               AND v.`deleted_at` IS NULL
             ORDER BY vs.`sort_order` ASC, vs.`id` ASC",
            PDOParams::positional([$vacancyId, $this->tenantId()])
        );

        return array_map([VacancyStage::class, 'fromRow'], $rows);
    }

    public function findById(int $id): ?VacancyStage
    {
        $row = $this->db->selectOne(
            "SELECT vs.*
             FROM `recruit_vacancy_stages` vs
             INNER JOIN `recruit_vacancies` v
                ON v.`id` = vs.`vacancy_id`
               AND v.`tenant_id` = vs.`tenant_id`
             WHERE vs.`id` = ?
               AND vs.`tenant_id` = ?
               AND v.`deleted_at` IS NULL
             LIMIT 1",
            PDOParams::positional([$id, $this->tenantId()])
        );

        return $row ? VacancyStage::fromRow($row) : null;
    }

    public function cloneDefaultsFor(int $vacancyId): void
    {
        $this->assertTenantVacancy($vacancyId);

        $defaults = $this->db->select(
            "SELECT `key_slug`, `label`, `sort_order`, `is_terminal`, `is_default`, `color`
             FROM `recruit_pipeline_stages`
             WHERE `tenant_id` = ?
               AND `client_organisation_id` IS NULL
               AND `deleted_at` IS NULL
             ORDER BY `sort_order` ASC, `id` ASC",
            PDOParams::positional([$this->tenantId()])
        );

        $this->insertStages($vacancyId, $defaults);
    }

    public function cloneForOrganisation(int $vacancyId, ?int $organisationId): void
    {
        $vacancyOrganisationId = $this->assertTenantVacancy($vacancyId);

        if ($organisationId === null) {
            throw new \InvalidArgumentException(
                'An organisation is required when cloning organisation pipeline stages.'
            );
        }

        $this->assertTenantOrganisation($organisationId);

        if ($vacancyOrganisationId !== $organisationId) {
            throw new \InvalidArgumentException(
                'The selected organisation does not match the vacancy organisation.'
            );
        }

        $stages = $this->db->select(
            "SELECT `key_slug`, `label`, `sort_order`, `is_terminal`, `is_default`, `color`,
                    `client_organisation_id`
             FROM `recruit_pipeline_stages`
             WHERE `tenant_id` = ?
               AND `deleted_at` IS NULL
               AND (
                    `client_organisation_id` IS NULL
                    OR `client_organisation_id` = ?
               )
             ORDER BY `sort_order` ASC, `id` ASC",
            PDOParams::positional([$this->tenantId(), $organisationId])
        );

        $byKey = [];

        foreach ($stages as $stage) {
            $key = Row::str($stage, 'key_slug');
            $stageOrganisationId = Row::nullableInt($stage, 'client_organisation_id');

            if (
                !isset($byKey[$key])
                || $stageOrganisationId !== null
            ) {
                $byKey[$key] = $stage;
            }
        }

        $merged = array_values($byKey);

        usort(
            $merged,
            fn ($a, $b) => Row::int($a, 'sort_order') <=> Row::int($b, 'sort_order')
        );

        $this->insertStages($vacancyId, $merged);
    }

    /**
     * @param array<int, array<string, mixed>> $stages
     */
    private function insertStages(int $vacancyId, array $stages): void
    {
        foreach ($stages as $stage) {
            $this->db->execute(
                "INSERT INTO `recruit_vacancy_stages`
                    (`tenant_id`, `vacancy_id`, `key_slug`, `label`, `sort_order`,
                     `is_terminal`, `is_default`, `color`)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                PDOParams::positional([
                    $this->tenantId(),
                    $vacancyId,
                    Row::str($stage, 'key_slug'),
                    Row::str($stage, 'label'),
                    Row::int($stage, 'sort_order'),
                    Row::int($stage, 'is_terminal'),
                    Row::int($stage, 'is_default'),
                    Row::nullableStr($stage, 'color'),
                ])
            );
        }
    }
}

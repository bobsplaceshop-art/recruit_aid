<?php
declare(strict_types=1);

namespace CorporateAid\Recruit\Repositories;

use CorporateAid\Core\Database;
use CorporateAid\Core\PDOParams;
use CorporateAid\Core\Row;
use CorporateAid\Core\TenantContext;
use CorporateAid\Recruit\Models\PipelineStage;

final class PipelineStageRepository
{
    public function __construct(
        private Database $db,
        private TenantContext $tenant
    ) {}

    private function tenantId(): int
    {
        return $this->tenant->id();
    }

    private function assertTenantOrganisation(int $organisationId): void
    {
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

    /** @return PipelineStage[] */
    public function forOrganisation(?int $organisationId): array
    {
        if ($organisationId !== null) {
            if ($organisationId <= 0) {
                throw new \InvalidArgumentException('Organisation ID must be positive.');
            }

            $this->assertTenantOrganisation($organisationId);
        }

        $rows = $this->db->select(
            "SELECT *
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

        foreach ($rows as $row) {
            $key = Row::str($row, 'key_slug');
            $stageOrganisationId = Row::nullableInt($row, 'client_organisation_id');

            if (
                !isset($byKey[$key])
                || $stageOrganisationId !== null
            ) {
                $byKey[$key] = $row;
            }
        }

        $merged = array_values($byKey);

        usort(
            $merged,
            fn ($a, $b) => Row::int($a, 'sort_order') <=> Row::int($b, 'sort_order')
        );

        return array_map([PipelineStage::class, 'fromRow'], $merged);
    }

    public function findById(int $id): ?PipelineStage
    {
        $row = $this->db->selectOne(
            "SELECT *
             FROM `recruit_pipeline_stages`
             WHERE `id` = ?
               AND `tenant_id` = ?
               AND `deleted_at` IS NULL
             LIMIT 1",
            PDOParams::positional([$id, $this->tenantId()])
        );

        return $row ? PipelineStage::fromRow($row) : null;
    }
}

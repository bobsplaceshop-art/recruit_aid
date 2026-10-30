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

    /** @return PipelineStage[] */
    public function forOrganisation(?int $organisationId): array
    {
        $rows = $this->db->select(
            "SELECT * FROM `recruit_pipeline_stages`
             WHERE `tenant_id` = ?
               AND `deleted_at` IS NULL
               AND (`organisation_id` IS NULL OR `organisation_id` = ?)
             ORDER BY `sort_order` ASC, `id` ASC",
            PDOParams::positional([$this->tenant->id(), $organisationId])
        );

        $byKey = [];
        foreach ($rows as $r) {
            $key = Row::str($r, 'key_slug');
            if (!isset($byKey[$key]) || $r['organisation_id'] !== null) {
                $byKey[$key] = $r;
            }
        }

        $merged = array_values($byKey);
        usort($merged, fn ($a, $b) => Row::int($a, 'sort_order') <=> Row::int($b, 'sort_order'));

        return array_map([PipelineStage::class, 'fromRow'], $merged);
    }

    public function findById(int $id): ?PipelineStage
    {
        $row = $this->db->selectOne(
            "SELECT * FROM `recruit_pipeline_stages`
             WHERE `id` = ? AND `tenant_id` = ? AND `deleted_at` IS NULL
             LIMIT 1",
            PDOParams::positional([$id, $this->tenant->id()])
        );
        return $row ? PipelineStage::fromRow($row) : null;
    }
}

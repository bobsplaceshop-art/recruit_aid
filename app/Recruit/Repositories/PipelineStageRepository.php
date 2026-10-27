<?php
declare(strict_types=1);

namespace CorporateAid\Recruit\Repositories;

use CorporateAid\Core\Database;
use CorporateAid\Recruit\Models\PipelineStage;

final class PipelineStageRepository
{
    public function __construct(private Database $db) {}

    /**
     * Returns stages for an organisation merged with system defaults.
     * Organisation-specific stages override defaults with the same key_slug.
     * Soft-deleted stages are always excluded.
     *
     * @return PipelineStage[]
     */
    public function forOrganisation(?int $organisationId): array
    {
        $rows = $this->db->select(
            "SELECT * FROM `recruit_pipeline_stages`
             WHERE `deleted_at` IS NULL
               AND (`organisation_id` IS NULL OR `organisation_id` = ?)
             ORDER BY `sort_order` ASC, `id` ASC",
            [$organisationId]
        );

        // Org-specific overrides win over defaults (same key_slug)
        $byKey = [];
        foreach ($rows as $r) {
            $key = $r['key_slug'];
            // If org-specific exists, prefer it
            if (!isset($byKey[$key]) || $r['organisation_id'] !== null) {
                $byKey[$key] = $r;
            }
        }

        // Sort merged result by sort_order
        $merged = array_values($byKey);
        usort($merged, fn ($a, $b) => ($a['sort_order'] <=> $b['sort_order']));

        return array_map([PipelineStage::class, 'fromRow'], $merged);
    }

    /**
     * Find a pipeline stage by ID. Soft-deleted stages are excluded —
     * a "deleted" stage is by definition not available for use.
     */
    public function findById(int $id): ?PipelineStage
    {
        $row = $this->db->selectOne(
            "SELECT * FROM `recruit_pipeline_stages`
             WHERE `id` = ? AND `deleted_at` IS NULL
             LIMIT 1",
            [$id]
        );
        return $row ? PipelineStage::fromRow($row) : null;
    }
}

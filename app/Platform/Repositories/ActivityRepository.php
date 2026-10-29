<?php
declare(strict_types=1);

namespace CorporateAid\Platform\Repositories;

use CorporateAid\Core\Database;
use CorporateAid\Core\PDOParams;
use CorporateAid\Core\Row;

/**
 * Reads against `core_activities`. Writes are handled by ActivityLogger.
 */
final class ActivityRepository
{
    public function __construct(private Database $db) {}

    /**
     * Timeline for a specific entity, newest first.
     *
     * @return array<int, array{
     *   id: int,
     *   user_id: ?int,
     *   user_name: string,
     *   action: string,
     *   description: string,
     *   created_at: string
     * }>
     */
    public function forEntity(string $entityType, int $entityId, int $limit = 20): array
    {
        $limit = max(1, min(100, $limit));

        $rows = $this->db->select(
            "SELECT a.`id`, a.`user_id`, a.`action`, a.`description`, a.`created_at`,
                    u.`name` AS `user_name`
             FROM `core_activities` a
             LEFT JOIN `core_users` u ON u.`id` = a.`user_id`
             WHERE a.`subject_type` = ?
               AND a.`subject_id` = ?
             ORDER BY a.`created_at` DESC, a.`id` DESC
             LIMIT " . $limit,
            PDOParams::positional([$entityType, $entityId])
        );

        return array_map(
            static fn(array $r) => [
                'id'          => Row::int($r, 'id'),
                'user_id'     => Row::nullableInt($r, 'user_id'),
                'user_name'   => Row::str($r, 'user_name', 'System'),
                'action'      => Row::str($r, 'action'),
                'description' => Row::str($r, 'description'),
                'created_at'  => Row::str($r, 'created_at'),
            ],
            $rows
        );
    }

    public function countForEntity(string $entityType, int $entityId): int
    {
        return $this->db->scalarInt(
            "SELECT COUNT(*) FROM `core_activities`
             WHERE `subject_type` = ? AND `subject_id` = ?",
            PDOParams::positional([$entityType, $entityId])
        );
    }
}

<?php
declare(strict_types=1);

namespace CorporateAid\Platform\Repositories;

use CorporateAid\Core\Database;
use CorporateAid\Core\PDOParams;
use CorporateAid\Core\Row;

final class CustomFieldValueRepository
{
    public function __construct(private Database $db) {}

    /**
     * @return array<int, string|null>  [custom_field_id => value]
     */
    public function forEntity(string $entityType, int $entityId): array
    {
        $rows = $this->db->select(
            "SELECT `custom_field_id`, `value` FROM `core_custom_field_values`
             WHERE `entity_type` = ? AND `entity_id` = ?",
            PDOParams::positional([$entityType, $entityId])
        );
        $out = [];
        foreach ($rows as $r) {
            $out[Row::int($r, 'custom_field_id')] = Row::nullableStr($r, 'value');
        }
        return $out;
    }

    public function setValue(int $customFieldId, string $entityType, int $entityId, ?string $value): void
    {
        $this->db->execute(
            "INSERT INTO `core_custom_field_values`
                (`custom_field_id`, `entity_type`, `entity_id`, `value`)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)",
            PDOParams::positional([$customFieldId, $entityType, $entityId, $value])
        );
    }

    public function deleteForEntity(string $entityType, int $entityId): void
    {
        $this->db->execute(
            "DELETE FROM `core_custom_field_values` WHERE `entity_type` = ? AND `entity_id` = ?",
            PDOParams::positional([$entityType, $entityId])
        );
    }
}

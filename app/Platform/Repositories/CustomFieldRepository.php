<?php
declare(strict_types=1);

namespace CorporateAid\Platform\Repositories;

use CorporateAid\Core\Database;
use CorporateAid\Core\PDOParams;
use CorporateAid\Platform\Models\CustomField;

final class CustomFieldRepository
{
    public function __construct(private Database $db) {}

    public function findById(int $id): ?CustomField
    {
        $row = $this->db->selectOne(
            "SELECT * FROM `core_custom_fields` WHERE `id` = ? LIMIT 1",
            PDOParams::positional([$id])
        );
        return $row ? CustomField::fromRow($row) : null;
    }

    public function findByKey(string $entityType, string $fieldKey): ?CustomField
    {
        $row = $this->db->selectOne(
            "SELECT * FROM `core_custom_fields` WHERE `entity_type` = ? AND `field_key` = ? LIMIT 1",
            PDOParams::positional([$entityType, $fieldKey])
        );
        return $row ? CustomField::fromRow($row) : null;
    }

    /** @return CustomField[] */
    public function activeFor(string $entityType, bool $includeArchived = false): array
    {
        $sql = "SELECT * FROM `core_custom_fields`
                WHERE `entity_type` = ? AND `deleted_at` IS NULL";
        $params = [$entityType];

        if (!$includeArchived) {
            $sql .= " AND `is_active` = 1";
        }
        $sql .= " ORDER BY `sort_order` ASC, `id` ASC";

        $rows = $this->db->select($sql, PDOParams::positional($params));
        return array_map([CustomField::class, 'fromRow'], $rows);
    }

    /** @return CustomField[] */
    public function allFor(string $entityType): array
    {
        $rows = $this->db->select(
            "SELECT * FROM `core_custom_fields`
             WHERE `entity_type` = ? AND `deleted_at` IS NULL
             ORDER BY `sort_order` ASC, `id` ASC",
            PDOParams::positional([$entityType])
        );
        return array_map([CustomField::class, 'fromRow'], $rows);
    }

    /**
     * @param array{
     *   entity_type: string,
     *   field_key: string,
     *   label: string,
     *   input_type: string,
     *   is_required?: bool,
     *   options?: array<int|string, mixed>,
     *   constraints?: array<string, mixed>,
     *   default_value?: ?string,
     *   help_text?: ?string,
     *   sort_order?: int,
     *   is_active?: bool,
     *   is_system?: bool,
     *   created_by?: ?int
     * } $data
     */
    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        return $this->db->insert(
            "INSERT INTO `core_custom_fields`
                (`entity_type`, `field_key`, `label`, `input_type`, `is_required`,
                 `options`, `constraints`, `default_value`, `help_text`, `sort_order`,
                 `is_active`, `is_system`, `created_by`)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            PDOParams::positional([
                $data['entity_type'],
                $data['field_key'],
                $data['label'],
                $data['input_type'],
                !empty($data['is_required']) ? 1 : 0,
                !empty($data['options']) ? json_encode($data['options']) : null,
                !empty($data['constraints']) ? json_encode($data['constraints']) : null,
                $data['default_value'] ?? null,
                $data['help_text'] ?? null,
                $data['sort_order'] ?? 0,
                array_key_exists('is_active', $data) ? (int) (bool) $data['is_active'] : 1,
                !empty($data['is_system']) ? 1 : 0,
                $data['created_by'] ?? null,
            ])
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): void
    {
        $allowed = ['label', 'is_required', 'options', 'constraints', 'default_value',
                    'help_text', 'sort_order', 'is_active'];
        $sets = [];
        $params = [];
        foreach ($allowed as $col) {
            if (array_key_exists($col, $data)) {
                $sets[] = "`{$col}` = ?";
                $v = $data[$col];
                if ($col === 'options' || $col === 'constraints') {
                    $v = !empty($v) ? json_encode($v) : null;
                } elseif ($col === 'is_required' || $col === 'is_active') {
                    $v = (int) (bool) $v;
                }
                $params[] = $v;
            }
        }
        if (empty($sets)) return;
        $params[] = $id;
        $this->db->execute(
            "UPDATE `core_custom_fields` SET " . implode(', ', $sets) . " WHERE `id` = ?",
            PDOParams::positional($params)
        );
    }

    public function setActive(int $id, bool $active): void
    {
        $this->db->execute(
            "UPDATE `core_custom_fields` SET `is_active` = ? WHERE `id` = ?",
            PDOParams::positional([(int) $active, $id])
        );
    }

    public function softDelete(int $id): void
    {
        $this->db->execute(
            "UPDATE `core_custom_fields` SET `deleted_at` = NOW(), `is_active` = 0 WHERE `id` = ?",
            PDOParams::positional([$id])
        );
    }

    public function hardDelete(int $id): void
    {
        $this->db->execute(
            "DELETE FROM `core_custom_fields` WHERE `id` = ?",
            PDOParams::positional([$id])
        );
    }

    public function valueCount(int $fieldId): int
    {
        return $this->db->scalarInt(
            "SELECT COUNT(*) FROM `core_custom_field_values` WHERE `custom_field_id` = ?",
            PDOParams::positional([$fieldId])
        );
    }

    public function nextSortOrder(string $entityType): int
    {
        $max = $this->db->scalarInt(
            "SELECT COALESCE(MAX(`sort_order`), 0) FROM `core_custom_fields` WHERE `entity_type` = ?",
            PDOParams::positional([$entityType])
        );
        return $max + 10;
    }

    public function setRequired(int $id, bool $required): void
    {
        $this->db->execute(
            "UPDATE `core_custom_fields` SET `is_required` = ? WHERE `id` = ?",
            PDOParams::positional([(int) $required, $id])
        );
    }

    public function setSortOrder(int $id, int $order): void
    {
        $this->db->execute(
            "UPDATE `core_custom_fields` SET `sort_order` = ? WHERE `id` = ?",
            PDOParams::positional([$order, $id])
        );
    }

    /** @return CustomField[] */
    public function orderedFor(string $entityType): array
    {
        $rows = $this->db->select(
            "SELECT * FROM `core_custom_fields`
             WHERE `entity_type` = ? AND `deleted_at` IS NULL
             ORDER BY `sort_order` ASC, `id` ASC",
            PDOParams::positional([$entityType])
        );
        return array_map([CustomField::class, 'fromRow'], $rows);
    }
}

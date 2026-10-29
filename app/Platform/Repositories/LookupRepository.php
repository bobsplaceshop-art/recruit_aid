<?php
declare(strict_types=1);

namespace CorporateAid\Platform\Repositories;

use CorporateAid\Core\Database;
use CorporateAid\Core\PDOParams;
use CorporateAid\Core\Row;

/**
 * Reads/writes for core_lookups — the configurable enum table.
 * Every read filters deleted_at IS NULL.
 */
final class LookupRepository
{
    public function __construct(private Database $db) {}

    /**
     * All active items in a group, ordered by sort_order.
     *
     * @return array<int, array{id:int, group_slug:string, slug:string, label:string, description:?string, sort_order:int, is_active:bool, is_system:bool}>
     */
    public function activeFor(string $groupSlug): array
    {
        $rows = $this->db->select(
            "SELECT `id`, `group_slug`, `slug`, `label`, `description`, `sort_order`, `is_active`, `is_system`
             FROM `core_lookups`
             WHERE `group_slug` = ?
               AND `is_active` = 1
               AND `deleted_at` IS NULL
             ORDER BY `sort_order` ASC, `label` ASC",
            PDOParams::positional([$groupSlug])
        );
        return array_map([self::class, 'hydrate'], $rows);
    }

    /**
     * All groups with their active item counts (for /admin/lookups).
     *
     * @return array<int, array{group_slug:string, item_count:int}>
     */
    public function allActiveGroups(): array
    {
        $rows = $this->db->select(
            "SELECT `group_slug`, COUNT(*) AS `item_count`
             FROM `core_lookups`
             WHERE `is_active` = 1 AND `deleted_at` IS NULL
             GROUP BY `group_slug`
             ORDER BY `group_slug` ASC"
        );
        return array_map(
            fn(array $r) => [
                'group_slug' => Row::str($r, 'group_slug'),
                'item_count' => Row::int($r, 'item_count'),
            ],
            $rows
        );
    }

    /**
     * Full row (including inactive) for the admin edit form.
     *
     * @return array{id:int, group_slug:string, slug:string, label:string, description:?string, sort_order:int, is_active:bool, is_system:bool}|null
     */
    public function findById(int $id): ?array
    {
        $row = $this->db->selectOne(
            "SELECT `id`, `group_slug`, `slug`, `label`, `description`, `sort_order`, `is_active`, `is_system`
             FROM `core_lookups`
             WHERE `id` = ? AND `deleted_at` IS NULL LIMIT 1",
            PDOParams::positional([$id])
        );
        return $row === null ? null : self::hydrate($row);
    }

    /**
     * @param array{group_slug:string, slug:string, label:string, description:?string, sort_order:int, is_active:bool, is_system:bool} $data
     */
    public function create(array $data): int
    {
        return $this->db->insert(
            "INSERT INTO `core_lookups`
                (`group_slug`, `slug`, `label`, `description`, `sort_order`, `is_active`, `is_system`)
             VALUES (?, ?, ?, ?, ?, ?, ?)",
            PDOParams::positional([
                $data['group_slug'],
                $data['slug'],
                $data['label'],
                $data['description'] ?? null,
                $data['sort_order'],
                $data['is_active'] ? 1 : 0,
                $data['is_system'] ? 1 : 0,
            ])
        );
    }

    /**
     * @param array{label:string, description:?string, sort_order:int, is_active:bool} $data
     */
    public function update(int $id, array $data): void
    {
        $this->db->execute(
            "UPDATE `core_lookups`
             SET `label` = ?, `description` = ?, `sort_order` = ?, `is_active` = ?
             WHERE `id` = ?",
            PDOParams::positional([
                $data['label'],
                $data['description'] ?? null,
                $data['sort_order'],
                $data['is_active'] ? 1 : 0,
                $id,
            ])
        );
    }

    public function softDelete(int $id): void
    {
        $this->db->execute(
            "UPDATE `core_lookups` SET `deleted_at` = NOW() WHERE `id` = ?",
            PDOParams::positional([$id])
        );
    }

    /** @return string[] */
    public function activeSlugsFor(string $groupSlug): array
    {
        $rows = $this->db->select(
            "SELECT `slug` FROM `core_lookups`
             WHERE `group_slug` = ? AND `is_active` = 1 AND `deleted_at` IS NULL
             ORDER BY `sort_order`",
            PDOParams::positional([$groupSlug])
        );
        return array_map(fn(array $r) => Row::str($r, 'slug'), $rows);
    }

    public function slugExistsInGroup(string $groupSlug, string $slug, ?int $excludingId = null): bool
    {
        $sql = "SELECT COUNT(*) FROM `core_lookups`
                WHERE `group_slug` = ? AND `slug` = ? AND `deleted_at` IS NULL";
        $params = [$groupSlug, $slug];
        if ($excludingId !== null) {
            $sql .= " AND `id` != ?";
            $params[] = $excludingId;
        }
        return $this->db->scalarInt($sql, PDOParams::positional($params)) > 0;
    }

    /**
     * @param array<string, mixed> $row
     * @return array{id:int, group_slug:string, slug:string, label:string, description:?string, sort_order:int, is_active:bool, is_system:bool}
     */
    private static function hydrate(array $row): array
    {
        return [
            'id'          => Row::int($row, 'id'),
            'group_slug'  => Row::str($row, 'group_slug'),
            'slug'        => Row::str($row, 'slug'),
            'label'       => Row::str($row, 'label'),
            'description' => Row::nullableStr($row, 'description'),
            'sort_order'  => Row::int($row, 'sort_order'),
            'is_active'   => Row::bool($row, 'is_active'),
            'is_system'   => Row::bool($row, 'is_system'),
        ];
    }
}

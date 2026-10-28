<?php
declare(strict_types=1);

namespace CorporateAid\Platform\Repositories;

use CorporateAid\Core\Database;
use CorporateAid\Core\PDOParams;
use CorporateAid\Platform\Models\EntityFile;

final class EntityFileRepository
{
    public function __construct(private Database $db) {}

    public function findById(int $id): ?EntityFile
    {
        $row = $this->db->selectOne(
            "SELECT * FROM `core_entity_files` WHERE `id` = ? LIMIT 1",
            PDOParams::positional([$id])
        );
        return $row ? EntityFile::fromRow($row) : null;
    }

    public function link(
        int $fileId,
        string $entityType,
        int $entityId,
        string $category,
        ?int $linkedBy
    ): void {
        $this->db->execute(
            "INSERT INTO `core_entity_files`
                (`file_id`, `entity_type`, `entity_id`, `file_category`, `linked_by`)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE `linked_by` = VALUES(`linked_by`)",
            PDOParams::positional([$fileId, $entityType, $entityId, $category, $linkedBy])
        );
    }

    public function unlink(int $fileId, string $entityType, int $entityId, string $category): void
    {
        $this->db->execute(
            "DELETE FROM `core_entity_files`
             WHERE `file_id` = ? AND `entity_type` = ? AND `entity_id` = ? AND `file_category` = ?",
            PDOParams::positional([$fileId, $entityType, $entityId, $category])
        );
    }

    public function unlinkAll(int $fileId): void
    {
        $this->db->execute(
            "DELETE FROM `core_entity_files` WHERE `file_id` = ?",
            PDOParams::positional([$fileId])
        );
    }

    public function linkCountForFile(int $fileId): int
    {
        return $this->db->scalarInt(
            "SELECT COUNT(*) FROM `core_entity_files` WHERE `file_id` = ?",
            PDOParams::positional([$fileId])
        );
    }

    /** @return EntityFile[] */
    public function forEntity(string $entityType, int $entityId): array
    {
        $rows = $this->db->select(
            "SELECT * FROM `core_entity_files`
             WHERE `entity_type` = ? AND `entity_id` = ?
             ORDER BY `created_at` DESC",
            PDOParams::positional([$entityType, $entityId])
        );
        return array_map([EntityFile::class, 'fromRow'], $rows);
    }

    /** @return EntityFile[] */
    public function linksForFile(int $fileId): array
    {
        $rows = $this->db->select(
            "SELECT * FROM `core_entity_files`
             WHERE `file_id` = ?
             ORDER BY `created_at` DESC",
            PDOParams::positional([$fileId])
        );
        return array_map([EntityFile::class, 'fromRow'], $rows);
    }
}

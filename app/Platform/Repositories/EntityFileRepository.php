<?php
declare(strict_types=1);

namespace CorporateAid\Platform\Repositories;

use CorporateAid\Core\Database;
use CorporateAid\Platform\Models\EntityFile;

final class EntityFileRepository
{
    public function __construct(private Database $db) {}

    public function findById(int $id): ?EntityFile
    {
        $row = $this->db->selectOne(
            "SELECT * FROM `core_entity_files` WHERE `id` = ? LIMIT 1",
            [$id]
        );
        return $row ? EntityFile::fromRow($row) : null;
    }

    /**
     * Link a file to an entity. Idempotent: re-linking the same
     * (file, entity, category) triple is a no-op.
     */
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
            [$fileId, $entityType, $entityId, $category, $linkedBy]
        );
    }

    /**
     * Remove a specific link. Does NOT delete the underlying file.
     */
    public function unlink(int $fileId, string $entityType, int $entityId, string $category): void
    {
        $this->db->execute(
            "DELETE FROM `core_entity_files`
             WHERE `file_id` = ? AND `entity_type` = ? AND `entity_id` = ? AND `file_category` = ?",
            [$fileId, $entityType, $entityId, $category]
        );
    }

    /**
     * Unlink all links for a given file (used when file is being deleted).
     */
    public function unlinkAll(int $fileId): void
    {
        $this->db->execute("DELETE FROM `core_entity_files` WHERE `file_id` = ?", [$fileId]);
    }

    /**
     * How many entities reference this file? If zero, the file is orphan
     * and eligible for purge.
     */
    public function linkCountForFile(int $fileId): int
    {
        return (int) $this->db->scalar(
            "SELECT COUNT(*) FROM `core_entity_files` WHERE `file_id` = ?",
            [$fileId]
        );
    }

    /**
     * Return links for an entity.
     * @return EntityFile[]
     */
    public function forEntity(string $entityType, int $entityId): array
    {
        $rows = $this->db->select(
            "SELECT * FROM `core_entity_files`
             WHERE `entity_type` = ? AND `entity_id` = ?
             ORDER BY `created_at` DESC",
            [$entityType, $entityId]
        );
        return array_map([EntityFile::class, 'fromRow'], $rows);
    }

    /**
     * Return links that point AT a file (the inverse of forEntity).
     *
     * Used by FileController to derive download permission from the
     * file's linked entities. Zero links => orphan => deny.
     *
     * @return EntityFile[]
     */
    public function linksForFile(int $fileId): array
    {
        $rows = $this->db->select(
            "SELECT * FROM `core_entity_files`
             WHERE `file_id` = ?
             ORDER BY `created_at` DESC",
            [$fileId]
        );
        return array_map([EntityFile::class, 'fromRow'], $rows);
    }
}

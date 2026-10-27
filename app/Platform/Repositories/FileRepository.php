<?php
declare(strict_types=1);

namespace CorporateAid\Platform\Repositories;

use CorporateAid\Core\Database;
use CorporateAid\Platform\Models\File;

final class FileRepository
{
    public function __construct(private Database $db) {}

    public function findById(int $id): ?File
    {
        $row = $this->db->selectOne(
            "SELECT * FROM `core_files` WHERE `id` = ? AND `deleted_at` IS NULL LIMIT 1",
            [$id]
        );
        return $row ? File::fromRow($row) : null;
    }

    public function findByUuid(string $uuid): ?File
    {
        $row = $this->db->selectOne(
            "SELECT * FROM `core_files` WHERE `uuid` = ? AND `deleted_at` IS NULL LIMIT 1",
            [$uuid]
        );
        return $row ? File::fromRow($row) : null;
    }

    public function findByHash(string $sha256): ?File
    {
        $row = $this->db->selectOne(
            "SELECT * FROM `core_files` WHERE `sha256_hash` = ? AND `deleted_at` IS NULL LIMIT 1",
            [$sha256]
        );
        return $row ? File::fromRow($row) : null;
    }

    /**
     * Insert a new files row.
     * Caller is responsible for having written the file to disk.
     */
    public function create(
        string $uuid,
        string $originalName,
        string $storedName,
        string $storagePath,
        string $mimeType,
        string $extension,
        int $sizeBytes,
        string $sha256,
        ?int $uploadedBy
    ): int {
        return $this->db->insert(
            "INSERT INTO `core_files`
                (`uuid`, `original_name`, `stored_name`, `storage_path`,
                 `mime_type`, `extension`, `size_bytes`, `sha256_hash`, `uploaded_by`)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $uuid, $originalName, $storedName, $storagePath,
                $mimeType, $extension, $sizeBytes, $sha256, $uploadedBy,
            ]
        );
    }

    /**
     * Soft-delete: mark the row so it disappears from findById/findByUuid.
     * Physical deletion is deferred to a purge cron.
     */
    public function softDelete(int $id): void
    {
        $this->db->execute(
            "UPDATE `core_files` SET `deleted_at` = NOW() WHERE `id` = ? AND `deleted_at` IS NULL",
            [$id]
        );
    }

    /** Hard delete — only used by the purge cron. */
    public function hardDelete(int $id): void
    {
        $this->db->execute("DELETE FROM `core_files` WHERE `id` = ?", [$id]);
    }

    /**
     * Return files linked to an entity (via entity_files).
     * Optional $category filters by file_category.
     *
     * @return File[]
     */
    public function forEntity(string $entityType, int $entityId, ?string $category = null): array
    {
        $sql = "SELECT f.* FROM `core_files` f
                INNER JOIN `core_entity_files` ef ON ef.`file_id` = f.`id`
                WHERE ef.`entity_type` = ? AND ef.`entity_id` = ?
                  AND f.`deleted_at` IS NULL";
        $params = [$entityType, $entityId];

        if ($category !== null) {
            $sql .= " AND ef.`file_category` = ?";
            $params[] = $category;
        }

        $sql .= " ORDER BY f.`created_at` DESC, f.`id` DESC";

        $rows = $this->db->select($sql, $params);
        return array_map([File::class, 'fromRow'], $rows);
    }
}

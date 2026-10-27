<?php
declare(strict_types=1);

namespace CorporateAid\Platform\Models;

/**
 * A link between a File and an entity (candidate, user, etc.).
 * Multiple links can exist for the same file (shared across entities).
 */
final class EntityFile
{
    public int $id;
    public int $fileId;
    public string $entityType;
    public int $entityId;
    public string $fileCategory;
    public ?int $linkedBy;
    public ?string $createdAt;

    public static function fromRow(array $row): self
    {
        $e = new self();
        $e->id           = (int) $row['id'];
        $e->fileId       = (int) $row['file_id'];
        $e->entityType   = (string) $row['entity_type'];
        $e->entityId     = (int) $row['entity_id'];
        $e->fileCategory = (string) $row['file_category'];
        $e->linkedBy     = $row['linked_by'] !== null ? (int) $row['linked_by'] : null;
        $e->createdAt    = $row['created_at'] !== null ? (string) $row['created_at'] : null;
        return $e;
    }
}

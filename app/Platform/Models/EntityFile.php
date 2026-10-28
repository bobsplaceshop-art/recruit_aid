<?php
declare(strict_types=1);

namespace CorporateAid\Platform\Models;

use CorporateAid\Core\Row;

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

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        $e = new self();
        $e->id           = Row::int($row, 'id');
        $e->fileId       = Row::int($row, 'file_id');
        $e->entityType   = Row::str($row, 'entity_type');
        $e->entityId     = Row::int($row, 'entity_id');
        $e->fileCategory = Row::str($row, 'file_category');
        $e->linkedBy     = Row::nullableInt($row, 'linked_by');
        $e->createdAt    = Row::nullableStr($row, 'created_at');
        return $e;
    }
}

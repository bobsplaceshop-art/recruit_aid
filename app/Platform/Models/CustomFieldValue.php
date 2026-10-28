<?php
declare(strict_types=1);

namespace CorporateAid\Platform\Models;

use CorporateAid\Core\Row;

final class CustomFieldValue
{
    public int $id;
    public int $customFieldId;
    public string $entityType;
    public int $entityId;
    public ?string $value;
    public ?string $createdAt;
    public ?string $updatedAt;

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        $v = new self();
        $v->id = Row::int($row, 'id');
        $v->customFieldId = Row::int($row, 'custom_field_id');
        $v->entityType = Row::str($row, 'entity_type');
        $v->entityId = Row::int($row, 'entity_id');
        $v->value = Row::nullableStr($row, 'value');
        $v->createdAt = Row::nullableStr($row, 'created_at');
        $v->updatedAt = Row::nullableStr($row, 'updated_at');
        return $v;
    }
}

<?php
declare(strict_types=1);

namespace CorporateAid\Platform\Models;

final class CustomFieldValue
{
    public int $id;
    public int $customFieldId;
    public string $entityType;
    public int $entityId;
    public ?string $value;
    public ?string $createdAt;
    public ?string $updatedAt;

    public static function fromRow(array $row): self
    {
        $v = new self();
        $v->id = (int) $row['id'];
        $v->customFieldId = (int) $row['custom_field_id'];
        $v->entityType = (string) $row['entity_type'];
        $v->entityId = (int) $row['entity_id'];
        $v->value = $row['value'] !== null ? (string) $row['value'] : null;
        $v->createdAt = $row['created_at'] !== null ? (string) $row['created_at'] : null;
        $v->updatedAt = $row['updated_at'] !== null ? (string) $row['updated_at'] : null;
        return $v;
    }
}

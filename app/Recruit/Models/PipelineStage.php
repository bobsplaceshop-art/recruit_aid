<?php
declare(strict_types=1);

namespace CorporateAid\Recruit\Models;

final class PipelineStage
{
    public int $id;
    public ?int $organisationId;
    public string $keySlug;
    public string $label;
    public int $sortOrder;
    public bool $isTerminal;
    public bool $isDefault;
    public ?string $color;
    public ?string $description;
    public ?string $createdAt;
    public ?string $updatedAt;
    public ?string $deletedAt;

    public static function fromRow(array $row): self
    {
        $s = new self();
        $s->id = (int) $row['id'];
        $s->organisationId = $row['organisation_id'] !== null ? (int) $row['organisation_id'] : null;
        $s->keySlug = (string) $row['key_slug'];
        $s->label = (string) $row['label'];
        $s->sortOrder = (int) $row['sort_order'];
        $s->isTerminal = (bool) $row['is_terminal'];
        $s->isDefault = (bool) $row['is_default'];
        $s->color = $row['color'] !== null ? (string) $row['color'] : null;
        $s->description = $row['description'] !== null ? (string) $row['description'] : null;
        $s->createdAt = $row['created_at'] !== null ? (string) $row['created_at'] : null;
        $s->updatedAt = $row['updated_at'] !== null ? (string) $row['updated_at'] : null;
        $s->deletedAt = $row['deleted_at'] !== null ? (string) $row['deleted_at'] : null;
        return $s;
    }
}

<?php
declare(strict_types=1);

namespace CorporateAid\Recruit\Models;

use CorporateAid\Core\Row;

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

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        $s = new self();
        $s->id = Row::int($row, 'id');
        $s->organisationId = Row::nullableInt($row, 'organisation_id');
        $s->keySlug = Row::str($row, 'key_slug');
        $s->label = Row::str($row, 'label');
        $s->sortOrder = Row::int($row, 'sort_order');
        $s->isTerminal = Row::bool($row, 'is_terminal');
        $s->isDefault = Row::bool($row, 'is_default');
        $s->color = Row::nullableStr($row, 'color');
        $s->description = Row::nullableStr($row, 'description');
        $s->createdAt = Row::nullableStr($row, 'created_at');
        $s->updatedAt = Row::nullableStr($row, 'updated_at');
        $s->deletedAt = Row::nullableStr($row, 'deleted_at');
        return $s;
    }
}

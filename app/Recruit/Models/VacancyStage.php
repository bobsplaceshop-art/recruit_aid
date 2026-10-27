<?php
declare(strict_types=1);

namespace CorporateAid\Recruit\Models;

final class VacancyStage
{
    public int $id;
    public int $vacancyId;
    public string $keySlug;
    public string $label;
    public int $sortOrder;
    public bool $isTerminal;
    public bool $isDefault;
    public ?string $color;
    public ?string $createdAt;
    public ?string $updatedAt;

    public static function fromRow(array $row): self
    {
        $s = new self();
        $s->id = (int) $row['id'];
        $s->vacancyId = (int) $row['vacancy_id'];
        $s->keySlug = (string) $row['key_slug'];
        $s->label = (string) $row['label'];
        $s->sortOrder = (int) $row['sort_order'];
        $s->isTerminal = (bool) $row['is_terminal'];
        $s->isDefault = (bool) $row['is_default'];
        $s->color = $row['color'] !== null ? (string) $row['color'] : null;
        $s->createdAt = $row['created_at'] !== null ? (string) $row['created_at'] : null;
        $s->updatedAt = $row['updated_at'] !== null ? (string) $row['updated_at'] : null;
        return $s;
    }
}

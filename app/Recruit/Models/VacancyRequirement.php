<?php
declare(strict_types=1);

namespace CorporateAid\Recruit\Models;

final class VacancyRequirement
{
    public int $id;
    public int $vacancyId;
    public string $type;
    public string $value;
    public int $sortOrder;
    public ?string $createdAt;

    public static function fromRow(array $row): self
    {
        $r = new self();
        $r->id = (int) $row['id'];
        $r->vacancyId = (int) $row['vacancy_id'];
        $r->type = (string) $row['type'];
        $r->value = (string) $row['value'];
        $r->sortOrder = (int) $row['sort_order'];
        $r->createdAt = $row['created_at'] !== null ? (string) $row['created_at'] : null;
        return $r;
    }
}

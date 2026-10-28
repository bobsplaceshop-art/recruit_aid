<?php
declare(strict_types=1);

namespace CorporateAid\Recruit\Models;

use CorporateAid\Core\Row;

final class VacancyRequirement
{
    public int $id;
    public int $vacancyId;
    public string $type;
    public string $value;
    public int $sortOrder;
    public ?string $createdAt;

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        $r = new self();
        $r->id = Row::int($row, 'id');
        $r->vacancyId = Row::int($row, 'vacancy_id');
        $r->type = Row::str($row, 'type');
        $r->value = Row::str($row, 'value');
        $r->sortOrder = Row::int($row, 'sort_order');
        $r->createdAt = Row::nullableStr($row, 'created_at');
        return $r;
    }
}

<?php
declare(strict_types=1);

namespace CorporateAid\Recruit\Models;

use CorporateAid\Core\Row;

final class ApplicationStageHistory
{
    public int $id;
    public int $applicationId;
    public ?int $fromStageId;
    public ?int $toStageId;
    public ?int $movedBy;
    public string $movedAt;
    public ?string $notes;

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        $h = new self();
        $h->id = Row::int($row, 'id');
        $h->applicationId = Row::int($row, 'application_id');
        $h->fromStageId = Row::nullableInt($row, 'from_stage_id');
        $h->toStageId = Row::nullableInt($row, 'to_stage_id');
        $h->movedBy = Row::nullableInt($row, 'moved_by');
        $h->movedAt = Row::str($row, 'moved_at');
        $h->notes = Row::nullableStr($row, 'notes');
        return $h;
    }
}

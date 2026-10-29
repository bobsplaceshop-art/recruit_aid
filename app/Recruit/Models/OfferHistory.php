<?php
declare(strict_types=1);

namespace CorporateAid\Recruit\Models;

use CorporateAid\Core\Row;

final class OfferHistory
{
    public int $id;
    public int $offerId;
    public ?string $fromStatus;
    public string $toStatus;
    public ?int $changedBy;
    public string $changedAt;
    public ?string $notes;

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        $h = new self();
        $h->id = Row::int($row, 'id');
        $h->offerId = Row::int($row, 'offer_id');
        $h->fromStatus = Row::nullableStr($row, 'from_status');
        $h->toStatus = Row::str($row, 'to_status');
        $h->changedBy = Row::nullableInt($row, 'changed_by');
        $h->changedAt = Row::str($row, 'changed_at');
        $h->notes = Row::nullableStr($row, 'notes');
        return $h;
    }
}

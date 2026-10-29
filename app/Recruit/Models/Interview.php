<?php
declare(strict_types=1);

namespace CorporateAid\Recruit\Models;

use CorporateAid\Core\Row;

final class Interview
{
    public int $id;
    public string $code;
    public int $applicationId;
    public string $scheduledAt;
    public int $durationMinutes;
    public string $type;
    public ?string $location;
    public string $status;
    public ?string $notes;
    public ?int $createdBy;
    public ?string $createdAt;
    public ?string $updatedAt;
    public ?string $deletedAt;

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        $i = new self();
        $i->id = Row::int($row, 'id');
        $i->code = Row::str($row, 'code');
        $i->applicationId = Row::int($row, 'application_id');
        $i->scheduledAt = Row::str($row, 'scheduled_at');
        $i->durationMinutes = Row::int($row, 'duration_minutes', 60);
        $i->type = Row::str($row, 'type', 'video');
        $i->location = Row::nullableStr($row, 'location');
        $i->status = Row::str($row, 'status', 'scheduled');
        $i->notes = Row::nullableStr($row, 'notes');
        $i->createdBy = Row::nullableInt($row, 'created_by');
        $i->createdAt = Row::nullableStr($row, 'created_at');
        $i->updatedAt = Row::nullableStr($row, 'updated_at');
        $i->deletedAt = Row::nullableStr($row, 'deleted_at');
        return $i;
    }

    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }

    public function isScheduled(): bool
    {
        return $this->status === 'scheduled' && $this->deletedAt === null;
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'scheduled' => 'Scheduled',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
            'no_show'   => 'No Show',
            default     => ucfirst($this->status),
        };
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            'scheduled' => 'ra-badge ra-badge-success',
            'completed' => 'ra-badge',
            'cancelled' => 'ra-badge ra-badge-danger',
            'no_show'   => 'ra-badge ra-badge-warn',
            default     => 'ra-badge',
        };
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            'phone'     => 'Phone',
            'video'     => 'Video',
            'onsite'    => 'On-site',
            'technical' => 'Technical',
            'panel'     => 'Panel',
            'final'     => 'Final',
            default     => ucfirst($this->type),
        };
    }
}

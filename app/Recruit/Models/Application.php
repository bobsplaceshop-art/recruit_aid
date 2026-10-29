<?php
declare(strict_types=1);

namespace CorporateAid\Recruit\Models;

use CorporateAid\Core\Row;

final class Application
{
    public int $id;
    public string $code;
    public int $candidateId;
    public int $vacancyId;
    public ?int $currentStageId;
    public string $status;
    public ?string $source;
    public ?string $notes;
    public string $appliedAt;
    public ?int $createdBy;
    public ?string $createdAt;
    public ?string $updatedAt;
    public ?string $deletedAt;

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        $a = new self();
        $a->id = Row::int($row, 'id');
        $a->code = Row::str($row, 'code');
        $a->candidateId = Row::int($row, 'candidate_id');
        $a->vacancyId = Row::int($row, 'vacancy_id');
        $a->currentStageId = Row::nullableInt($row, 'current_stage_id');
        $a->status = Row::str($row, 'status');
        $a->source = Row::nullableStr($row, 'source');
        $a->notes = Row::nullableStr($row, 'notes');
        $a->appliedAt = Row::str($row, 'applied_at');
        $a->createdBy = Row::nullableInt($row, 'created_by');
        $a->createdAt = Row::nullableStr($row, 'created_at');
        $a->updatedAt = Row::nullableStr($row, 'updated_at');
        $a->deletedAt = Row::nullableStr($row, 'deleted_at');
        return $a;
    }

    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }

    public function isActive(): bool
    {
        return $this->status === 'active' && $this->deletedAt === null;
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'active'    => 'Active',
            'withdrawn' => 'Withdrawn',
            'rejected'  => 'Rejected',
            'hired'     => 'Hired',
            'archived'  => 'Archived',
            default     => ucfirst($this->status),
        };
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            'active'    => 'ra-badge ra-badge-success',
            'withdrawn' => 'ra-badge',
            'rejected'  => 'ra-badge ra-badge-danger',
            'hired'     => 'ra-badge ra-badge-success',
            'archived'  => 'ra-badge ra-badge-warn',
            default     => 'ra-badge',
        };
    }
}

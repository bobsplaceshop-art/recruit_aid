<?php
declare(strict_types=1);

namespace CorporateAid\Recruit\Models;

use CorporateAid\Core\Row;

final class Offer
{
    public int $id;
    public string $code;
    public int $applicationId;
    public float $offeredSalary;
    public string $currency;
    public string $startDate;
    public string $expiresAt;
    public string $status;
    public ?string $notes;
    public ?string $responseNotes;
    public ?string $sentAt;
    public ?string $respondedAt;
    public ?int $createdBy;
    public ?string $createdAt;
    public ?string $updatedAt;
    public ?string $deletedAt;

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        $o = new self();
        $o->id = Row::int($row, 'id');
        $o->code = Row::str($row, 'code');
        $o->applicationId = Row::int($row, 'application_id');
        $o->offeredSalary = Row::float($row, 'offered_salary');
        $o->currency = Row::str($row, 'currency');
        $o->startDate = Row::str($row, 'start_date');
        $o->expiresAt = Row::str($row, 'expires_at');
        $o->status = Row::str($row, 'status');
        $o->notes = Row::nullableStr($row, 'notes');
        $o->responseNotes = Row::nullableStr($row, 'response_notes');
        $o->sentAt = Row::nullableStr($row, 'sent_at');
        $o->respondedAt = Row::nullableStr($row, 'responded_at');
        $o->createdBy = Row::nullableInt($row, 'created_by');
        $o->createdAt = Row::nullableStr($row, 'created_at');
        $o->updatedAt = Row::nullableStr($row, 'updated_at');
        $o->deletedAt = Row::nullableStr($row, 'deleted_at');
        return $o;
    }

    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isActive(): bool
    {
        return in_array($this->status, ['draft', 'sent'], true) && !$this->isDeleted();
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, ['accepted', 'declined', 'expired', 'withdrawn'], true);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'draft'     => 'Draft',
            'sent'      => 'Sent',
            'accepted'  => 'Accepted',
            'declined'  => 'Declined',
            'expired'   => 'Expired',
            'withdrawn' => 'Withdrawn',
            default     => ucfirst($this->status),
        };
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            'draft'     => 'ra-badge',
            'sent'      => 'ra-badge ra-badge-warn',
            'accepted'  => 'ra-badge ra-badge-success',
            'declined'  => 'ra-badge ra-badge-danger',
            'expired'   => 'ra-badge ra-badge-warn',
            'withdrawn' => 'ra-badge ra-badge-danger',
            default     => 'ra-badge',
        };
    }

    public function salaryFormatted(): string
    {
        return $this->currency . ' ' . number_format($this->offeredSalary, 0);
    }
}

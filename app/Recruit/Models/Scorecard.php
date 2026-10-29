<?php
declare(strict_types=1);

namespace CorporateAid\Recruit\Models;

use CorporateAid\Core\Row;

final class Scorecard
{
    public int $id;
    public int $interviewId;
    public int $interviewerId;
    public ?string $recommendation;
    public ?string $notes;
    public ?string $submittedAt;
    public ?string $createdAt;
    public ?string $updatedAt;

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        $s = new self();
        $s->id = Row::int($row, 'id');
        $s->interviewId = Row::int($row, 'interview_id');
        $s->interviewerId = Row::int($row, 'interviewer_id');
        $s->recommendation = Row::nullableStr($row, 'recommendation');
        $s->notes = Row::nullableStr($row, 'notes');
        $s->submittedAt = Row::nullableStr($row, 'submitted_at');
        $s->createdAt = Row::nullableStr($row, 'created_at');
        $s->updatedAt = Row::nullableStr($row, 'updated_at');
        return $s;
    }

    public function isSubmitted(): bool
    {
        return $this->submittedAt !== null;
    }

    public function recommendationLabel(): string
    {
        return match ($this->recommendation) {
            'strong_yes' => 'Strong Yes',
            'yes'        => 'Yes',
            'neutral'    => 'Neutral',
            'no'         => 'No',
            'strong_no'  => 'Strong No',
            default      => '—',
        };
    }

    public function recommendationBadgeClass(): string
    {
        return match ($this->recommendation) {
            'strong_yes', 'yes' => 'ra-badge ra-badge-success',
            'neutral'           => 'ra-badge',
            'no', 'strong_no'   => 'ra-badge ra-badge-danger',
            default             => 'ra-badge',
        };
    }
}

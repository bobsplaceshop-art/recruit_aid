<?php
declare(strict_types=1);

namespace CorporateAid\Recruit\Models;

use CorporateAid\Core\Row;

final class CandidateExperience
{
    public int $id;
    public int $candidateId;
    public string $jobTitle;
    public string $company;
    public ?string $location;
    public ?string $startDate;
    public ?string $endDate;
    public bool $isCurrent;
    public ?string $description;
    public int $sortOrder;

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        $e = new self();
        $e->id = Row::int($row, 'id');
        $e->candidateId = Row::int($row, 'candidate_id');
        $e->jobTitle = Row::str($row, 'job_title');
        $e->company = Row::str($row, 'company');
        $e->location = Row::nullableStr($row, 'location');
        $e->startDate = Row::nullableStr($row, 'start_date');
        $e->endDate = Row::nullableStr($row, 'end_date');
        $e->isCurrent = Row::bool($row, 'is_current');
        $e->description = Row::nullableStr($row, 'description');
        $e->sortOrder = Row::int($row, 'sort_order');
        return $e;
    }
}

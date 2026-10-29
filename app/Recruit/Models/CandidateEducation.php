<?php
declare(strict_types=1);

namespace CorporateAid\Recruit\Models;

use CorporateAid\Core\Row;

final class CandidateEducation
{
    public int $id;
    public int $candidateId;
    public string $institution;
    public ?string $degree;
    public ?string $fieldOfStudy;
    public ?int $startYear;
    public ?int $endYear;
    public ?string $grade;
    public ?string $description;

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        $e = new self();
        $e->id = Row::int($row, 'id');
        $e->candidateId = Row::int($row, 'candidate_id');
        $e->institution = Row::str($row, 'institution');
        $e->degree = Row::nullableStr($row, 'degree');
        $e->fieldOfStudy = Row::nullableStr($row, 'field_of_study');
        $e->startYear = Row::nullableInt($row, 'start_year');
        $e->endYear = Row::nullableInt($row, 'end_year');
        $e->grade = Row::nullableStr($row, 'grade');
        $e->description = Row::nullableStr($row, 'description');
        return $e;
    }
}

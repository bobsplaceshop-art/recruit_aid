<?php
declare(strict_types=1);

namespace CorporateAid\Recruit\Models;

use CorporateAid\Core\Row;

final class CandidateSkill
{
    public int $id;
    public int $candidateId;
    public string $skillName;
    public ?string $proficiency;
    public ?int $yearsUsed;

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        $s = new self();
        $s->id = Row::int($row, 'id');
        $s->candidateId = Row::int($row, 'candidate_id');
        $s->skillName = Row::str($row, 'skill_name');
        $s->proficiency = Row::nullableStr($row, 'proficiency');
        $s->yearsUsed = Row::nullableInt($row, 'years_used');
        return $s;
    }
}

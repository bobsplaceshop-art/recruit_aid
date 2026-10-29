<?php
declare(strict_types=1);

namespace CorporateAid\Recruit\Models;

use CorporateAid\Core\Row;

final class ScorecardAttribute
{
    public int $id;
    public int $scorecardId;
    public string $competencyName;
    public int $score;
    public ?string $evidence;
    public int $sortOrder;

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        $a = new self();
        $a->id = Row::int($row, 'id');
        $a->scorecardId = Row::int($row, 'scorecard_id');
        $a->competencyName = Row::str($row, 'competency_name');
        $a->score = Row::int($row, 'score');
        $a->evidence = Row::nullableStr($row, 'evidence');
        $a->sortOrder = Row::int($row, 'sort_order');
        return $a;
    }
}

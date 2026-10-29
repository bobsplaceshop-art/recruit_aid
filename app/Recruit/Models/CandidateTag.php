<?php
declare(strict_types=1);

namespace CorporateAid\Recruit\Models;

use CorporateAid\Core\Row;

final class CandidateTag
{
    public int $id;
    public int $candidateId;
    public string $tag;

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        $t = new self();
        $t->id = Row::int($row, 'id');
        $t->candidateId = Row::int($row, 'candidate_id');
        $t->tag = Row::str($row, 'tag');
        return $t;
    }
}

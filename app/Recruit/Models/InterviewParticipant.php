<?php
declare(strict_types=1);

namespace CorporateAid\Recruit\Models;

use CorporateAid\Core\Row;

final class InterviewParticipant
{
    public int $id;
    public int $interviewId;
    public int $userId;
    public string $role;

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        $p = new self();
        $p->id = Row::int($row, 'id');
        $p->interviewId = Row::int($row, 'interview_id');
        $p->userId = Row::int($row, 'user_id');
        $p->role = Row::str($row, 'role', 'interviewer');
        return $p;
    }
}

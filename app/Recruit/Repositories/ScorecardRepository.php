<?php
declare(strict_types=1);

namespace CorporateAid\Recruit\Repositories;

use CorporateAid\Core\Database;
use CorporateAid\Core\PDOParams;
use CorporateAid\Core\Row;
use CorporateAid\Recruit\Models\Scorecard;
use CorporateAid\Recruit\Models\ScorecardAttribute;

final class ScorecardRepository
{
    public function __construct(private Database $db) {}

    public function findById(int $id): ?Scorecard
    {
        $row = $this->db->selectOne(
            "SELECT * FROM `recruit_scorecards` WHERE `id` = ? LIMIT 1",
            PDOParams::positional([$id])
        );
        return $row ? Scorecard::fromRow($row) : null;
    }

    public function findByInterviewAndUser(int $interviewId, int $userId): ?Scorecard
    {
        $row = $this->db->selectOne(
            "SELECT * FROM `recruit_scorecards`
             WHERE `interview_id` = ? AND `interviewer_id` = ? LIMIT 1",
            PDOParams::positional([$interviewId, $userId])
        );
        return $row ? Scorecard::fromRow($row) : null;
    }

    /** @return Scorecard[] */
    public function forInterview(int $interviewId): array
    {
        $rows = $this->db->select(
            "SELECT * FROM `recruit_scorecards` WHERE `interview_id` = ?",
            PDOParams::positional([$interviewId])
        );
        return array_map([Scorecard::class, 'fromRow'], $rows);
    }

    public function createOrUpdate(int $interviewId, int $interviewerId, ?string $recommendation, ?string $notes, bool $submit = false): int
    {
        $existing = $this->findByInterviewAndUser($interviewId, $interviewerId);
        $submittedAt = $submit ? date('Y-m-d H:i:s') : null;

        if ($existing !== null) {
            $this->db->execute(
                "UPDATE `recruit_scorecards`
                 SET `recommendation` = ?, `notes` = ?, `submitted_at` = ?
                 WHERE `id` = ?",
                PDOParams::positional([$recommendation, $notes, $submittedAt, $existing->id])
            );
            return $existing->id;
        }

        return $this->db->insert(
            "INSERT INTO `recruit_scorecards`
                (`interview_id`, `interviewer_id`, `recommendation`, `notes`, `submitted_at`)
             VALUES (?, ?, ?, ?, ?)",
            PDOParams::positional([$interviewId, $interviewerId, $recommendation, $notes, $submittedAt])
        );
    }

    /** @param array<int, array{competency_name:string, score:int, evidence:?string}> $attributes */
    public function syncAttributes(int $scorecardId, array $attributes): void
    {
        $this->db->execute(
            "DELETE FROM `recruit_scorecard_attributes` WHERE `scorecard_id` = ?",
            PDOParams::positional([$scorecardId])
        );
        $order = 0;
        foreach ($attributes as $attr) {
            $name = trim($attr['competency_name']);
            if ($name === '') continue;
            $this->db->execute(
                "INSERT INTO `recruit_scorecard_attributes`
                    (`scorecard_id`, `competency_name`, `score`, `evidence`, `sort_order`)
                 VALUES (?, ?, ?, ?, ?)",
                PDOParams::positional([$scorecardId, $name, $attr['score'], $attr['evidence'], $order++])
            );
        }
    }

    /** @return ScorecardAttribute[] */
    public function attributesFor(int $scorecardId): array
    {
        $rows = $this->db->select(
            "SELECT * FROM `recruit_scorecard_attributes`
             WHERE `scorecard_id` = ?
             ORDER BY `sort_order` ASC, `id` ASC",
            PDOParams::positional([$scorecardId])
        );
        return array_map([ScorecardAttribute::class, 'fromRow'], $rows);
    }

    public function countSubmittedForInterview(int $interviewId): int
    {
        return $this->db->scalarInt(
            "SELECT COUNT(*) FROM `recruit_scorecards`
             WHERE `interview_id` = ? AND `submitted_at` IS NOT NULL",
            PDOParams::positional([$interviewId])
        );
    }
}

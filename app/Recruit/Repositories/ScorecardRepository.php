<?php
declare(strict_types=1);

namespace CorporateAid\Recruit\Repositories;

use CorporateAid\Core\Database;
use CorporateAid\Core\TenantContext;
use CorporateAid\Core\PDOParams;
use CorporateAid\Recruit\Models\Scorecard;
use CorporateAid\Recruit\Models\ScorecardAttribute;

final class ScorecardRepository
{
    public function __construct(
        private Database $db,
        private TenantContext $tenant
    ) {}

    private function tenantId(): int
    {
        $tenantId = $this->tenant->id();

        if ($tenantId < 1) {
            throw new \RuntimeException('A valid tenant context is required.');
        }

        return $tenantId;
    }

    private function assertInterview(int $interviewId): void
    {
        $exists = $this->db->scalarInt(
            "SELECT COUNT(*) FROM `recruit_interviews`
             WHERE `id` = ? AND `tenant_id` = ? AND `deleted_at` IS NULL",
            PDOParams::positional([$interviewId, $this->tenantId()])
        );

        if ($exists !== 1) {
            throw new \InvalidArgumentException('Interview not found in the active tenant.');
        }
    }

    private function assertInterviewer(int $interviewId, int $interviewerId): void
    {
        $exists = $this->db->scalarInt(
            "SELECT COUNT(*) FROM `core_users` u
             INNER JOIN `recruit_interview_participants` p
                ON p.`user_id` = u.`id`
               AND p.`tenant_id` = u.`tenant_id`
             INNER JOIN `recruit_interviews` i
                ON i.`id` = p.`interview_id`
               AND i.`tenant_id` = p.`tenant_id`
             WHERE i.`id` = ?
               AND i.`tenant_id` = ?
               AND i.`deleted_at` IS NULL
               AND u.`id` = ?
               AND u.`tenant_id` = ?
               AND u.`status` = 'active'
               AND u.`deleted_at` IS NULL",
            PDOParams::positional([
                $interviewId,
                $this->tenantId(),
                $interviewerId,
                $this->tenantId(),
            ])
        );

        if ($exists !== 1) {
            throw new \InvalidArgumentException(
                'The interviewer must be an active participant in this tenant’s interview.'
            );
        }
    }

    private function assertScorecard(int $scorecardId): void
    {
        $exists = $this->db->scalarInt(
            "SELECT COUNT(*) FROM `recruit_scorecards`
             WHERE `id` = ? AND `tenant_id` = ?",
            PDOParams::positional([$scorecardId, $this->tenantId()])
        );

        if ($exists !== 1) {
            throw new \InvalidArgumentException('Scorecard not found in the active tenant.');
        }
    }

    public function findById(int $id): ?Scorecard
    {
        $row = $this->db->selectOne(
            "SELECT * FROM `recruit_scorecards`
             WHERE `id` = ? AND `tenant_id` = ?
             LIMIT 1",
            PDOParams::positional([$id, $this->tenantId()])
        );

        return $row ? Scorecard::fromRow($row) : null;
    }

    public function findByInterviewAndUser(int $interviewId, int $userId): ?Scorecard
    {
        $row = $this->db->selectOne(
            "SELECT s.* FROM `recruit_scorecards` s
             INNER JOIN `recruit_interviews` i
                ON i.`id` = s.`interview_id`
               AND i.`tenant_id` = s.`tenant_id`
             WHERE s.`interview_id` = ?
               AND s.`interviewer_id` = ?
               AND s.`tenant_id` = ?
               AND i.`tenant_id` = ?
               AND i.`deleted_at` IS NULL
             LIMIT 1",
            PDOParams::positional([
                $interviewId,
                $userId,
                $this->tenantId(),
                $this->tenantId(),
            ])
        );

        return $row ? Scorecard::fromRow($row) : null;
    }

    /** @return Scorecard[] */
    public function forInterview(int $interviewId): array
    {
        $rows = $this->db->select(
            "SELECT s.* FROM `recruit_scorecards` s
             INNER JOIN `recruit_interviews` i
                ON i.`id` = s.`interview_id`
               AND i.`tenant_id` = s.`tenant_id`
             WHERE s.`interview_id` = ?
               AND s.`tenant_id` = ?
               AND i.`tenant_id` = ?
               AND i.`deleted_at` IS NULL
             ORDER BY s.`created_at` ASC, s.`id` ASC",
            PDOParams::positional([
                $interviewId,
                $this->tenantId(),
                $this->tenantId(),
            ])
        );

        return array_map([Scorecard::class, 'fromRow'], $rows);
    }

    public function createOrUpdate(
        int $interviewId,
        int $interviewerId,
        ?string $recommendation,
        ?string $notes,
        bool $submit = false
    ): int {
        $tenantId = $this->tenantId();

        $this->assertInterview($interviewId);
        $this->assertInterviewer($interviewId, $interviewerId);

        // Lock an existing scorecard when the caller has opened a transaction.
        $existingRow = $this->db->selectOne(
            "SELECT * FROM `recruit_scorecards`
             WHERE `interview_id` = ?
               AND `interviewer_id` = ?
               AND `tenant_id` = ?
             LIMIT 1
             FOR UPDATE",
            PDOParams::positional([$interviewId, $interviewerId, $tenantId])
        );

        $existing = $existingRow ? Scorecard::fromRow($existingRow) : null;

        if ($existing !== null) {
            if ($existing->isSubmitted()) {
                throw new \DomainException(
                    'Your scorecard has already been submitted and cannot be edited.'
                );
            }

            $submittedAt = $submit ? date('Y-m-d H:i:s') : null;

            $this->db->execute(
                "UPDATE `recruit_scorecards`
                 SET `recommendation` = ?,
                     `notes` = ?,
                     `submitted_at` = ?
                 WHERE `id` = ?
                   AND `tenant_id` = ?
                   AND `submitted_at` IS NULL",
                PDOParams::positional([
                    $recommendation,
                    $notes,
                    $submittedAt,
                    $existing->id,
                    $tenantId,
                ])
            );

            return $existing->id;
        }

        $submittedAt = $submit ? date('Y-m-d H:i:s') : null;

        return $this->db->insert(
            "INSERT INTO `recruit_scorecards`
                (`tenant_id`, `interview_id`, `interviewer_id`,
                 `recommendation`, `notes`, `submitted_at`)
             VALUES (?, ?, ?, ?, ?, ?)",
            PDOParams::positional([
                $tenantId,
                $interviewId,
                $interviewerId,
                $recommendation,
                $notes,
                $submittedAt,
            ])
        );
    }

    /**
     * @param array<int, array{competency_name:string, score:int, evidence:?string}> $attributes
     */
    public function syncAttributes(int $scorecardId, array $attributes): void
    {
        $tenantId = $this->tenantId();
        $this->assertScorecard($scorecardId);

        // Validate and normalise the complete replacement before deleting existing data.
        $normalisedAttributes = [];

        foreach ($attributes as $attr) {
            $name = trim((string) ($attr['competency_name'] ?? ''));

            if ($name === '') {
                continue;
            }

            $rawScore = $attr['score'] ?? null;

            if (
                (!is_int($rawScore) && !(is_string($rawScore) && ctype_digit($rawScore)))
                || (int) $rawScore < 1
                || (int) $rawScore > 5
            ) {
                throw new \InvalidArgumentException(
                    'Competency scores must be between 1 and 5.'
                );
            }

            $normalisedAttributes[] = [
                'competency_name' => $name,
                'score' => (int) $rawScore,
                'evidence' => $attr['evidence'] ?? null,
            ];
        }

        $this->db->execute(
            "DELETE FROM `recruit_scorecard_attributes`
             WHERE `scorecard_id` = ? AND `tenant_id` = ?",
            PDOParams::positional([$scorecardId, $tenantId])
        );

        foreach ($normalisedAttributes as $order => $attr) {
            $this->db->execute(
                "INSERT INTO `recruit_scorecard_attributes`
                    (`tenant_id`, `scorecard_id`, `competency_name`,
                     `score`, `evidence`, `sort_order`)
                 VALUES (?, ?, ?, ?, ?, ?)",
                PDOParams::positional([
                    $tenantId,
                    $scorecardId,
                    $attr['competency_name'],
                    $attr['score'],
                    $attr['evidence'],
                    $order,
                ])
            );
        }
    }

    /** @return ScorecardAttribute[] */
    public function attributesFor(int $scorecardId): array
    {
        $this->assertScorecard($scorecardId);

        $rows = $this->db->select(
            "SELECT * FROM `recruit_scorecard_attributes`
             WHERE `scorecard_id` = ? AND `tenant_id` = ?
             ORDER BY `sort_order` ASC, `id` ASC",
            PDOParams::positional([$scorecardId, $this->tenantId()])
        );

        return array_map([ScorecardAttribute::class, 'fromRow'], $rows);
    }

    public function countSubmittedForInterview(int $interviewId): int
    {
        return $this->db->scalarInt(
            "SELECT COUNT(*) FROM `recruit_scorecards` s
             INNER JOIN `recruit_interviews` i
                ON i.`id` = s.`interview_id`
               AND i.`tenant_id` = s.`tenant_id`
             WHERE s.`interview_id` = ?
               AND s.`tenant_id` = ?
               AND i.`tenant_id` = ?
               AND i.`deleted_at` IS NULL
               AND s.`submitted_at` IS NOT NULL",
            PDOParams::positional([
                $interviewId,
                $this->tenantId(),
                $this->tenantId(),
            ])
        );
    }
}

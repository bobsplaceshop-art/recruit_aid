<?php
declare(strict_types=1);

namespace CorporateAid\Recruit\Repositories;

use CorporateAid\Core\Database;
use CorporateAid\Core\TenantContext;
use CorporateAid\Core\PDOParams;
use CorporateAid\Core\Row;
use CorporateAid\Recruit\Models\Interview;
use CorporateAid\Recruit\Models\InterviewParticipant;

final class InterviewRepository
{
    public function __construct(
        private Database $db,
        private TenantContext $tenant
    ) {}

    public function findById(int $id): ?Interview
    {
        $row = $this->db->selectOne(
            "SELECT * FROM `recruit_interviews` WHERE `id` = ? AND `deleted_at` IS NULL LIMIT 1",
            PDOParams::positional([$this->tenant->id(),
            $id])
        );
        return $row ? Interview::fromRow($row) : null;
    }

    public function findByCode(string $code): ?Interview
    {
        $row = $this->db->selectOne(
            "SELECT * FROM `recruit_interviews` WHERE `code` = ? AND `deleted_at` IS NULL LIMIT 1",
            PDOParams::positional([$code])
        );
        return $row ? Interview::fromRow($row) : null;
    }

    /** @return Interview[] */
    public function forApplication(int $applicationId): array
    {
        $rows = $this->db->select(
            "SELECT * FROM `recruit_interviews`
             WHERE `application_id` = ? AND `deleted_at` IS NULL
             ORDER BY `scheduled_at` DESC",
            PDOParams::positional([$applicationId])
        );
        return array_map([Interview::class, 'fromRow'], $rows);
    }

    /** @return Interview[] */
    public function upcomingForUser(int $userId, int $limit = 10): array
    {
        $rows = $this->db->select(
            "SELECT i.* FROM `recruit_interviews` i
             INNER JOIN `recruit_interview_participants` p ON p.`interview_id` = i.`id`
             WHERE p.`user_id` = ?
               AND i.`status` = 'scheduled'
               AND i.`scheduled_at` >= NOW()
               AND i.`deleted_at` IS NULL
             ORDER BY i.`scheduled_at` ASC
             LIMIT " . max(1, min(50, $limit)),
            PDOParams::positional([$userId])
        );
        return array_map([Interview::class, 'fromRow'], $rows);
    }

    /** @param array<string, mixed> $data */
    /**
     * All interviews, optionally filtered by status. Used by the
     * /interviews index page. Super admins see all; the controller
     * filters by participant for regular users.
     *
     * @return Interview[]
     */
    public function allWithFilters(?string $status, int $limit = 100): array
    {
        $where = ["i.`deleted_at` IS NULL"];
        $params = [];

        if ($status !== null && $status !== '' && $status !== 'all') {
            $where[] = "i.`status` = ?";
            $params[] = $status;
        }

        $whereSql = 'WHERE ' . implode(' AND ', $where);

        $rows = $this->db->select(
            "SELECT i.* FROM `recruit_interviews` i
             $whereSql
             ORDER BY
                CASE WHEN i.`status` = 'scheduled' AND i.`scheduled_at` >= NOW() THEN 0 ELSE 1 END ASC,
                i.`scheduled_at` ASC,
                i.`id` DESC
             LIMIT " . max(1, min(500, $limit)),
            PDOParams::positional($params)
        );
        return array_map([Interview::class, 'fromRow'], $rows);
    }

    /**
     * Interviews the user is a participant in, optionally filtered by status.
     *
     * @return Interview[]
     */
    public function forUserWithFilters(int $userId, ?string $status, int $limit = 100): array
    {
        $where = ["i.`deleted_at` IS NULL", "p.`user_id` = ?"];
        $params = [$userId];

        if ($status !== null && $status !== '' && $status !== 'all') {
            $where[] = "i.`status` = ?";
            $params[] = $status;
        }

        $whereSql = 'WHERE ' . implode(' AND ', $where);

        $rows = $this->db->select(
            "SELECT i.* FROM `recruit_interviews` i
             INNER JOIN `recruit_interview_participants` p ON p.`interview_id` = i.`id`
             $whereSql
             ORDER BY
                CASE WHEN i.`status` = 'scheduled' AND i.`scheduled_at` >= NOW() THEN 0 ELSE 1 END ASC,
                i.`scheduled_at` ASC,
                i.`id` DESC
             LIMIT " . max(1, min(500, $limit)),
            PDOParams::positional($params)
        );
        return array_map([Interview::class, 'fromRow'], $rows);
    }

        /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        return $this->db->insert(
            "INSERT INTO `recruit_interviews`
                (`tenant_id`, `code`, `application_id`, `scheduled_at`, `duration_minutes`, `type`, `location`, `status`, `notes`, `created_by`)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            PDOParams::positional([
                $data['code'],
                $data['application_id'],
                $data['scheduled_at'],
                $data['duration_minutes'] ?? 60,
                $data['type'] ?? 'video',
                $data['location'] ?? null,
                $data['status'] ?? 'scheduled',
                $data['notes'] ?? null,
                $data['created_by'] ?? null,
            ])
        );
    }

    public function updateStatus(int $id, string $status): void
    {
        $this->db->execute(
            "UPDATE `recruit_interviews` SET `status` = ? WHERE `id` = ?",
            PDOParams::positional([$status, $id])
        );
    }

    public function softDelete(int $id): void
    {
        $this->db->execute(
            "UPDATE `recruit_interviews` SET `deleted_at` = NOW() WHERE `id` = ?",
            PDOParams::positional([$id])
        );
    }

    public function nextCode(): string
    {
        $this->db->execute(
            "UPDATE `core_sequence_counters` SET `last_value` = `last_value` + 1 WHERE `name` = 'rec_interview_code'"
        );
        $num = $this->db->scalarInt(
            "SELECT `last_value` FROM `core_sequence_counters` WHERE `name` = 'rec_interview_code'"
        );
        return 'INT-' . str_pad((string) $num, 6, '0', STR_PAD_LEFT);
    }

    /** @return InterviewParticipant[] */
    public function participantsFor(int $interviewId): array
    {
        $rows = $this->db->select(
            "SELECT * FROM `recruit_interview_participants` WHERE `interview_id` = ?",
            PDOParams::positional([$interviewId])
        );
        return array_map([InterviewParticipant::class, 'fromRow'], $rows);
    }

    /** @param int[] $userIds */
    public function setParticipants(int $interviewId, array $userIds, int $leadUserId): void
    {
        $this->db->execute(
            "DELETE FROM `recruit_interview_participants` WHERE `interview_id` = ?",
            PDOParams::positional([$interviewId])
        );
        foreach ($userIds as $uid) {
            $role = $uid === $leadUserId ? 'lead' : 'interviewer';
            $this->db->execute(
                "INSERT INTO `recruit_interview_participants` (`interview_id`, `user_id`, `role`) VALUES (?, ?, ?)",
                PDOParams::positional([$interviewId, $uid, $role])
            );
        }
    }

    public function isParticipant(int $interviewId, int $userId): bool
    {
        return $this->db->scalarInt(
            "SELECT COUNT(*) FROM `recruit_interview_participants`
             WHERE `interview_id` = ? AND `user_id` = ?",
            PDOParams::positional([$interviewId, $userId])
        ) > 0;
    }
}

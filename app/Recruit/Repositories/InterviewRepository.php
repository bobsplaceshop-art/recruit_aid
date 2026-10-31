<?php
declare(strict_types=1);

namespace CorporateAid\Recruit\Repositories;

use CorporateAid\Core\Database;
use CorporateAid\Core\TenantContext;
use CorporateAid\Platform\Support\CodeGenerator;
use CorporateAid\Core\PDOParams;
use CorporateAid\Recruit\Models\Interview;
use CorporateAid\Recruit\Models\InterviewParticipant;

final class InterviewRepository
{
    public function __construct(
        private Database $db,
        private TenantContext $tenant,
        private CodeGenerator $codes
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

    private function assertTenantUser(int $userId, string $field): void
    {
        if ($userId < 1) {
            throw new \InvalidArgumentException("Invalid {$field} user ID.");
        }

        $exists = $this->db->scalarInt(
            "SELECT COUNT(*) FROM `core_users`
             WHERE `id` = ? AND `tenant_id` = ?
               AND `status` = 'active' AND `deleted_at` IS NULL",
            PDOParams::positional([$userId, $this->tenantId()])
        );

        if ($exists !== 1) {
            throw new \InvalidArgumentException(
                ucfirst($field) . ' must be an active user in the current tenant.'
            );
        }
    }

    public function findById(int $id): ?Interview
    {
        $row = $this->db->selectOne(
            "SELECT * FROM `recruit_interviews`
             WHERE `id` = ? AND `tenant_id` = ? AND `deleted_at` IS NULL
             LIMIT 1",
            PDOParams::positional([$id, $this->tenantId()])
        );

        return $row ? Interview::fromRow($row) : null;
    }

    public function findByCode(string $code): ?Interview
    {
        $row = $this->db->selectOne(
            "SELECT * FROM `recruit_interviews`
             WHERE `code` = ? AND `tenant_id` = ? AND `deleted_at` IS NULL
             LIMIT 1",
            PDOParams::positional([$code, $this->tenantId()])
        );

        return $row ? Interview::fromRow($row) : null;
    }

    /** @return Interview[] */
    public function forApplication(int $applicationId): array
    {
        $rows = $this->db->select(
            "SELECT * FROM `recruit_interviews`
             WHERE `application_id` = ? AND `tenant_id` = ?
               AND `deleted_at` IS NULL
             ORDER BY `scheduled_at` DESC",
            PDOParams::positional([$applicationId, $this->tenantId()])
        );

        return array_map([Interview::class, 'fromRow'], $rows);
    }

    /** @return Interview[] */
    public function upcomingForUser(int $userId, int $limit = 10): array
    {
        $rows = $this->db->select(
            "SELECT i.* FROM `recruit_interviews` i
             INNER JOIN `recruit_interview_participants` p
                ON p.`interview_id` = i.`id`
               AND p.`tenant_id` = i.`tenant_id`
             WHERE i.`tenant_id` = ?
               AND p.`tenant_id` = ?
               AND p.`user_id` = ?
               AND i.`status` = 'scheduled'
               AND i.`scheduled_at` >= NOW()
               AND i.`deleted_at` IS NULL
             ORDER BY i.`scheduled_at` ASC
             LIMIT " . max(1, min(50, $limit)),
            PDOParams::positional([
                $this->tenantId(),
                $this->tenantId(),
                $userId,
            ])
        );

        return array_map([Interview::class, 'fromRow'], $rows);
    }

    /**
     * All interviews within the active tenant, optionally filtered by status.
     *
     * @return Interview[]
     */
    public function allWithFilters(?string $status, int $limit = 100): array
    {
        $where = [
            'i.`tenant_id` = ?',
            'i.`deleted_at` IS NULL',
        ];
        $params = [$this->tenantId()];

        if ($status !== null && $status !== '' && $status !== 'all') {
            $where[] = 'i.`status` = ?';
            $params[] = $status;
        }

        $rows = $this->db->select(
            "SELECT i.* FROM `recruit_interviews` i
             WHERE " . implode(' AND ', $where) . "
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
     * Interviews the user participates in, within the active tenant.
     *
     * @return Interview[]
     */
    public function forUserWithFilters(int $userId, ?string $status, int $limit = 100): array
    {
        $where = [
            'i.`tenant_id` = ?',
            'p.`tenant_id` = ?',
            'p.`user_id` = ?',
            'i.`deleted_at` IS NULL',
        ];
        $params = [$this->tenantId(), $this->tenantId(), $userId];

        if ($status !== null && $status !== '' && $status !== 'all') {
            $where[] = 'i.`status` = ?';
            $params[] = $status;
        }

        $rows = $this->db->select(
            "SELECT DISTINCT i.* FROM `recruit_interviews` i
             INNER JOIN `recruit_interview_participants` p
                ON p.`interview_id` = i.`id`
               AND p.`tenant_id` = i.`tenant_id`
             WHERE " . implode(' AND ', $where) . "
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
        $tenantId = $this->tenantId();
        $applicationId = (int) ($data['application_id'] ?? 0);
        $createdBy = isset($data['created_by']) && $data['created_by'] !== null
            ? (int) $data['created_by']
            : null;

        if ($applicationId < 1) {
            throw new \InvalidArgumentException('A valid application ID is required.');
        }

        $applicationExists = $this->db->scalarInt(
            "SELECT COUNT(*) FROM `recruit_applications`
             WHERE `id` = ? AND `tenant_id` = ? AND `deleted_at` IS NULL",
            PDOParams::positional([$applicationId, $tenantId])
        );

        if ($applicationExists !== 1) {
            throw new \InvalidArgumentException('Application not found in the active tenant.');
        }

        if ($createdBy !== null) {
            $this->assertTenantUser($createdBy, 'creator');
        }

        $code = trim((string) ($data['code'] ?? ''));
        $scheduledAt = trim((string) ($data['scheduled_at'] ?? ''));

        if ($code === '' || $scheduledAt === '') {
            throw new \InvalidArgumentException('Interview code and scheduled date are required.');
        }

        return $this->db->insert(
            "INSERT INTO `recruit_interviews`
                (`tenant_id`, `code`, `application_id`, `scheduled_at`,
                 `duration_minutes`, `type`, `location`, `status`, `notes`, `created_by`)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            PDOParams::positional([
                $tenantId,
                $code,
                $applicationId,
                $scheduledAt,
                $data['duration_minutes'] ?? 60,
                $data['type'] ?? 'video',
                $data['location'] ?? null,
                $data['status'] ?? 'scheduled',
                $data['notes'] ?? null,
                $createdBy,
            ])
        );
    }

    public function updateStatus(int $id, string $status): void
    {
        $this->db->execute(
            "UPDATE `recruit_interviews`
             SET `status` = ?
             WHERE `id` = ? AND `tenant_id` = ? AND `deleted_at` IS NULL",
            PDOParams::positional([$status, $id, $this->tenantId()])
        );
    }

    public function softDelete(int $id): void
    {
        $this->db->execute(
            "UPDATE `recruit_interviews`
             SET `deleted_at` = NOW()
             WHERE `id` = ? AND `tenant_id` = ? AND `deleted_at` IS NULL",
            PDOParams::positional([$id, $this->tenantId()])
        );
    }

    public function nextCode(): string
    {
        return $this->codes->nextForTenant(
            'rec_interview_code',
            'INT',
            $this->tenantId()
        );
    }

    /** @return InterviewParticipant[] */
    public function participantsFor(int $interviewId): array
    {
        $rows = $this->db->select(
            "SELECT p.* FROM `recruit_interview_participants` p
             INNER JOIN `recruit_interviews` i
                ON i.`id` = p.`interview_id`
               AND i.`tenant_id` = p.`tenant_id`
             WHERE p.`interview_id` = ?
               AND p.`tenant_id` = ?
               AND i.`tenant_id` = ?
               AND i.`deleted_at` IS NULL
             ORDER BY p.`role` ASC, p.`id` ASC",
            PDOParams::positional([
                $interviewId,
                $this->tenantId(),
                $this->tenantId(),
            ])
        );

        return array_map([InterviewParticipant::class, 'fromRow'], $rows);
    }

    /** @param int[] $userIds */
    public function setParticipants(int $interviewId, array $userIds, int $leadUserId): void
    {
        $tenantId = $this->tenantId();
        $this->assertInterview($interviewId);

        $normalisedIds = [];
        foreach ($userIds as $userId) {
            if (!is_int($userId) && !(is_string($userId) && ctype_digit($userId))) {
                throw new \InvalidArgumentException('Participant IDs must be positive integers.');
            }

            $userId = (int) $userId;
            if ($userId < 1) {
                throw new \InvalidArgumentException('Participant IDs must be positive integers.');
            }

            $normalisedIds[$userId] = $userId;
        }

        $normalisedIds = array_values($normalisedIds);

        if ($normalisedIds === []) {
            throw new \InvalidArgumentException('At least one participant is required.');
        }

        if (!in_array($leadUserId, $normalisedIds, true)) {
            throw new \InvalidArgumentException('The lead interviewer must be one of the assigned participants.');
        }

        foreach ($normalisedIds as $userId) {
            $this->assertTenantUser($userId, 'participant');
        }

        $this->db->execute(
            "DELETE FROM `recruit_interview_participants`
             WHERE `interview_id` = ? AND `tenant_id` = ?",
            PDOParams::positional([$interviewId, $tenantId])
        );

        foreach ($normalisedIds as $userId) {
            $role = $userId === $leadUserId ? 'lead' : 'interviewer';

            $this->db->execute(
                "INSERT INTO `recruit_interview_participants`
                    (`tenant_id`, `interview_id`, `user_id`, `role`)
                 VALUES (?, ?, ?, ?)",
                PDOParams::positional([$tenantId, $interviewId, $userId, $role])
            );
        }
    }

    public function isParticipant(int $interviewId, int $userId): bool
    {
        return $this->db->scalarInt(
            "SELECT COUNT(*) FROM `recruit_interview_participants` p
             INNER JOIN `recruit_interviews` i
                ON i.`id` = p.`interview_id`
               AND i.`tenant_id` = p.`tenant_id`
             WHERE p.`interview_id` = ?
               AND p.`user_id` = ?
               AND p.`tenant_id` = ?
               AND i.`tenant_id` = ?
               AND i.`deleted_at` IS NULL",
            PDOParams::positional([
                $interviewId,
                $userId,
                $this->tenantId(),
                $this->tenantId(),
            ])
        ) > 0;
    }
}

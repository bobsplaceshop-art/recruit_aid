<?php
declare(strict_types=1);

namespace CorporateAid\Recruit\Repositories;

use CorporateAid\Core\Database;
use CorporateAid\Core\PDOParams;
use CorporateAid\Core\Row;
use CorporateAid\Recruit\Models\Application;
use CorporateAid\Recruit\Models\ApplicationStageHistory;

final class ApplicationRepository
{
    public function __construct(private Database $db) {}

    public function findById(int $id): ?Application
    {
        $row = $this->db->selectOne(
            "SELECT * FROM `recruit_applications` WHERE `id` = ? LIMIT 1",
            PDOParams::positional([$id])
        );
        return $row ? Application::fromRow($row) : null;
    }

    public function findByCode(string $code, bool $includeDeleted = false): ?Application
    {
        $sql = "SELECT * FROM `recruit_applications` WHERE `code` = ?";
        if (!$includeDeleted) $sql .= " AND `deleted_at` IS NULL";
        $sql .= " LIMIT 1";
        $row = $this->db->selectOne($sql, PDOParams::positional([$code]));
        return $row ? Application::fromRow($row) : null;
    }

    public function findByCandidateAndVacancy(int $candidateId, int $vacancyId): ?Application
    {
        $row = $this->db->selectOne(
            "SELECT * FROM `recruit_applications`
             WHERE `candidate_id` = ? AND `vacancy_id` = ? AND `deleted_at` IS NULL
             LIMIT 1",
            PDOParams::positional([$candidateId, $vacancyId])
        );
        return $row ? Application::fromRow($row) : null;
    }

    /** @return Application[] */
    public function forVacancy(int $vacancyId): array
    {
        $rows = $this->db->select(
            "SELECT * FROM `recruit_applications`
             WHERE `vacancy_id` = ? AND `deleted_at` IS NULL
             ORDER BY `applied_at` DESC",
            PDOParams::positional([$vacancyId])
        );
        return array_map([Application::class, 'fromRow'], $rows);
    }

    /** @return Application[] */
    public function forCandidate(int $candidateId): array
    {
        $rows = $this->db->select(
            "SELECT * FROM `recruit_applications`
             WHERE `candidate_id` = ? AND `deleted_at` IS NULL
             ORDER BY `applied_at` DESC",
            PDOParams::positional([$candidateId])
        );
        return array_map([Application::class, 'fromRow'], $rows);
    }

    /** @return array<int, int> stage_id => count */
    public function countsByStageForVacancy(int $vacancyId): array
    {
        $rows = $this->db->select(
            "SELECT `current_stage_id`, COUNT(*) AS n
             FROM `recruit_applications`
             WHERE `vacancy_id` = ? AND `deleted_at` IS NULL AND `status` = 'active'
             GROUP BY `current_stage_id`",
            PDOParams::positional([$vacancyId])
        );
        $out = [];
        foreach ($rows as $r) {
            $sid = Row::nullableInt($r, 'current_stage_id');
            if ($sid !== null) $out[$sid] = Row::int($r, 'n');
        }
        return $out;
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        return $this->db->insert(
            "INSERT INTO `recruit_applications`
                (`code`, `candidate_id`, `vacancy_id`, `current_stage_id`, `status`, `source`, `notes`, `created_by`)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            PDOParams::positional([
                $data['code'],
                $data['candidate_id'],
                $data['vacancy_id'],
                $data['current_stage_id'] ?? null,
                $data['status'] ?? 'active',
                $data['source'] ?? null,
                $data['notes'] ?? null,
                $data['created_by'] ?? null,
            ])
        );
    }

    public function updateStage(int $applicationId, ?int $newStageId): void
    {
        $this->db->execute(
            "UPDATE `recruit_applications` SET `current_stage_id` = ? WHERE `id` = ?",
            PDOParams::positional([$newStageId, $applicationId])
        );
    }

    public function updateStatus(int $applicationId, string $status): void
    {
        $this->db->execute(
            "UPDATE `recruit_applications` SET `status` = ? WHERE `id` = ?",
            PDOParams::positional([$status, $applicationId])
        );
    }

    public function softDelete(int $id): void
    {
        $this->db->execute(
            "UPDATE `recruit_applications` SET `deleted_at` = NOW() WHERE `id` = ?",
            PDOParams::positional([$id])
        );
    }

    public function restore(int $id): void
    {
        $this->db->execute(
            "UPDATE `recruit_applications` SET `deleted_at` = NULL WHERE `id` = ?",
            PDOParams::positional([$id])
        );
    }

    public function recordStageMove(int $applicationId, ?int $fromStageId, ?int $toStageId, ?int $movedBy, ?string $notes = null): void
    {
        $this->db->execute(
            "INSERT INTO `recruit_application_stage_history`
                (`application_id`, `from_stage_id`, `to_stage_id`, `moved_by`, `notes`)
             VALUES (?, ?, ?, ?, ?)",
            PDOParams::positional([$applicationId, $fromStageId, $toStageId, $movedBy, $notes])
        );
    }

    /** @return ApplicationStageHistory[] */
    public function stageHistoryFor(int $applicationId): array
    {
        $rows = $this->db->select(
            "SELECT * FROM `recruit_application_stage_history`
             WHERE `application_id` = ?
             ORDER BY `moved_at` ASC, `id` ASC",
            PDOParams::positional([$applicationId])
        );
        return array_map([ApplicationStageHistory::class, 'fromRow'], $rows);
    }

    public function nextCode(): string
    {
        $this->db->execute(
            "UPDATE `core_sequence_counters` SET `last_value` = `last_value` + 1 WHERE `name` = 'rec_application_code'"
        );
        $num = $this->db->scalarInt(
            "SELECT `last_value` FROM `core_sequence_counters` WHERE `name` = 'rec_application_code'"
        );
        return 'APP-' . str_pad((string) $num, 6, '0', STR_PAD_LEFT);
    }
}

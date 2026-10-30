<?php
declare(strict_types=1);

namespace CorporateAid\Recruit\Repositories;

use CorporateAid\Core\Database;
use CorporateAid\Core\PDOParams;
use CorporateAid\Core\Row;
use CorporateAid\Core\TenantContext;

final class VacancyRecruiterRepository
{
    public function __construct(
        private Database $db,
        private TenantContext $tenant
    ) {}

    /** @return array<int, array{user_id:int, role:string}> */
    public function forVacancy(int $vacancyId): array
    {
        $rows = $this->db->select(
            "SELECT `user_id`, `role` FROM `recruit_vacancy_recruiters`
             WHERE `vacancy_id` = ? AND `tenant_id` = ?",
            PDOParams::positional([$vacancyId, $this->tenant->id()])
        );
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'user_id' => Row::int($r, 'user_id'),
                'role'    => Row::str($r, 'role'),
            ];
        }
        return $out;
    }

    /** @return int[] */
    public function userIdsFor(int $vacancyId): array
    {
        $rows = $this->db->select(
            "SELECT `user_id` FROM `recruit_vacancy_recruiters`
             WHERE `vacancy_id` = ? AND `tenant_id` = ?",
            PDOParams::positional([$vacancyId, $this->tenant->id()])
        );
        $out = [];
        foreach ($rows as $r) {
            $out[] = Row::int($r, 'user_id');
        }
        return $out;
    }

    /**
     * Replace the full recruiter set for a vacancy.
     *
     * @param int[] $userIds      All recruiters (including the lead)
     * @param int   $leadUserId   Must be present in $userIds
     */
    public function sync(int $vacancyId, array $userIds, int $leadUserId): void
    {
        $this->db->execute(
            "DELETE FROM `recruit_vacancy_recruiters` WHERE `vacancy_id` = ? AND `tenant_id` = ?",
            PDOParams::positional([$vacancyId, $this->tenant->id()])
        );

        if (empty($userIds)) return;

        if ($leadUserId > 0 && !in_array($leadUserId, $userIds, true)) {
            $userIds[] = $leadUserId;
        }

        $userIds = array_values(array_unique(array_map(static fn($v) => (int) $v, $userIds)));
        $assignedBy = null;

        foreach ($userIds as $uid) {
            if ($uid <= 0) continue;
            $role = ($uid === $leadUserId) ? 'lead' : 'recruiter';
            $this->db->execute(
                "INSERT IGNORE INTO `recruit_vacancy_recruiters`
                    (`tenant_id`, `vacancy_id`, `user_id`, `role`, `assigned_by`)
                 VALUES (?, ?, ?, ?, ?)",
                PDOParams::positional([$this->tenant->id(), $vacancyId, $uid, $role, $assignedBy])
            );
        }
    }

    public function userIsAssigned(int $vacancyId, int $userId): bool
    {
        return $this->db->scalarInt(
            "SELECT COUNT(*) FROM `recruit_vacancy_recruiters`
             WHERE `vacancy_id` = ? AND `user_id` = ? AND `tenant_id` = ?",
            PDOParams::positional([$vacancyId, $userId, $this->tenant->id()])
        ) > 0;
    }
}

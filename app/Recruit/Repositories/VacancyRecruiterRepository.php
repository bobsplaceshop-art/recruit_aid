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

    private function tenantId(): int
    {
        $tenantId = $this->tenant->id();

        if ($tenantId <= 0) {
            throw new \RuntimeException('An active tenant is required.');
        }

        return $tenantId;
    }

    private function assertTenantVacancy(int $vacancyId): void
    {
        if ($vacancyId <= 0) {
            throw new \InvalidArgumentException('A valid vacancy is required.');
        }

        $exists = $this->db->scalarInt(
            "SELECT COUNT(*)
             FROM `recruit_vacancies`
             WHERE `id` = ?
               AND `tenant_id` = ?
               AND `deleted_at` IS NULL",
            PDOParams::positional([$vacancyId, $this->tenantId()])
        );

        if ($exists !== 1) {
            throw new \InvalidArgumentException(
                'Vacancy not found in the active tenant.'
            );
        }
    }

    private function assertTenantUser(int $userId): void
    {
        if ($userId <= 0) {
            throw new \InvalidArgumentException('A valid recruiter is required.');
        }

        $exists = $this->db->scalarInt(
            "SELECT COUNT(*)
             FROM `core_users`
             WHERE `id` = ?
               AND `tenant_id` = ?
               AND `status` = 'active'
               AND `deleted_at` IS NULL",
            PDOParams::positional([$userId, $this->tenantId()])
        );

        if ($exists !== 1) {
            throw new \InvalidArgumentException(
                'Recruiter must be an active user in the active tenant.'
            );
        }
    }

    /** @return array<int, array{user_id:int, role:string}> */
    public function forVacancy(int $vacancyId): array
    {
        $rows = $this->db->select(
            "SELECT `user_id`, `role`
             FROM `recruit_vacancy_recruiters`
             WHERE `vacancy_id` = ?
               AND `tenant_id` = ?",
            PDOParams::positional([$vacancyId, $this->tenantId()])
        );

        $out = [];

        foreach ($rows as $row) {
            $out[] = [
                'user_id' => Row::int($row, 'user_id'),
                'role' => Row::str($row, 'role'),
            ];
        }

        return $out;
    }

    /** @return int[] */
    public function userIdsFor(int $vacancyId): array
    {
        $rows = $this->db->select(
            "SELECT `user_id`
             FROM `recruit_vacancy_recruiters`
             WHERE `vacancy_id` = ?
               AND `tenant_id` = ?",
            PDOParams::positional([$vacancyId, $this->tenantId()])
        );

        $out = [];

        foreach ($rows as $row) {
            $out[] = Row::int($row, 'user_id');
        }

        return $out;
    }

    /**
     * Replace the full recruiter set for a vacancy.
     *
     * @param int[] $userIds All recruiters, including the lead
     * @param int $leadUserId Lead recruiter
     */
    public function sync(int $vacancyId, array $userIds, int $leadUserId): void
    {
        $tenantId = $this->tenantId();

        // Validate the vacancy before touching its assignments.
        $this->assertTenantVacancy($vacancyId);

        $normalizedIds = [];

        foreach ($userIds as $value) {
            if (
					!(is_int($value) || (is_string($value) && ctype_digit($value)))
					|| (int) $value <= 0
				) {
					throw new \InvalidArgumentException('Invalid recruiter ID supplied.');
				}

				$userId = (int) $value;

            if ($userId <= 0) {
                throw new \InvalidArgumentException('Recruiter IDs must be positive integers.');
            }

            $normalizedIds[] = $userId;
        }

        if ($leadUserId < 0) {
            throw new \InvalidArgumentException('Invalid lead recruiter ID.');
        }

        if ($leadUserId > 0) {
            $normalizedIds[] = $leadUserId;
        }

        $normalizedIds = array_values(array_unique($normalizedIds));

        // Validate every recruiter before deleting any existing assignment.
        foreach ($normalizedIds as $userId) {
            $this->assertTenantUser($userId);
        }

        $this->db->execute(
            "DELETE FROM `recruit_vacancy_recruiters`
             WHERE `vacancy_id` = ?
               AND `tenant_id` = ?",
            PDOParams::positional([$vacancyId, $tenantId])
        );

        foreach ($normalizedIds as $userId) {
            $role = ($userId === $leadUserId) ? 'lead' : 'recruiter';

            $this->db->execute(
                "INSERT INTO `recruit_vacancy_recruiters`
                    (`tenant_id`, `vacancy_id`, `user_id`, `role`, `assigned_by`)
                 VALUES (?, ?, ?, ?, NULL)",
                PDOParams::positional([
                    $tenantId,
                    $vacancyId,
                    $userId,
                    $role,
                ])
            );
        }
    }

    public function userIsAssigned(int $vacancyId, int $userId): bool
    {
        return $this->db->scalarInt(
            "SELECT COUNT(*)
             FROM `recruit_vacancy_recruiters`
             WHERE `vacancy_id` = ?
               AND `user_id` = ?
               AND `tenant_id` = ?",
            PDOParams::positional([
                $vacancyId,
                $userId,
                $this->tenantId(),
            ])
        ) > 0;
    }
}

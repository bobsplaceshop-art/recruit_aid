<?php
declare(strict_types=1);

namespace CorporateAid\Recruit\Repositories;

use CorporateAid\Core\Database;
use CorporateAid\Core\PDOParams;
use CorporateAid\Core\Row;
use CorporateAid\Core\TenantContext;

final class VacancyHiringManagerRepository
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

    private function organisationIdForVacancy(int $vacancyId): int
    {
        if ($vacancyId <= 0) {
            throw new \InvalidArgumentException('A valid vacancy is required.');
        }

        $organisationId = $this->db->scalarInt(
            "SELECT `client_organisation_id`
             FROM `recruit_vacancies`
             WHERE `id` = ?
               AND `tenant_id` = ?
               AND `deleted_at` IS NULL",
            PDOParams::positional([$vacancyId, $this->tenantId()])
        );

        if ($organisationId <= 0) {
            throw new \InvalidArgumentException(
                'Vacancy not found in the active tenant.'
            );
        }

        return $organisationId;
    }

    private function assertTenantContact(int $contactId, int $organisationId): void
    {
        if ($contactId <= 0) {
            throw new \InvalidArgumentException('A valid hiring-manager contact is required.');
        }

        $exists = $this->db->scalarInt(
            "SELECT COUNT(*)
             FROM `recruit_client_contacts`
             WHERE `id` = ?
               AND `client_organisation_id` = ?
               AND `tenant_id` = ?
               AND `deleted_at` IS NULL",
            PDOParams::positional([
                $contactId,
                $organisationId,
                $this->tenantId(),
            ])
        );

        if ($exists !== 1) {
            throw new \InvalidArgumentException(
                'Hiring-manager contact must belong to the vacancy organisation in the active tenant.'
            );
        }
    }

    private function assertTenantUser(int $userId): void
    {
        if ($userId <= 0) {
            throw new \InvalidArgumentException('A valid assigning user is required.');
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
                'Assigning user must be active in the active tenant.'
            );
        }
    }

    /** @return int[] */
    public function contactIdsFor(int $vacancyId): array
    {
        $this->organisationIdForVacancy($vacancyId);

        $rows = $this->db->select(
            "SELECT `contact_id`
             FROM `recruit_vacancy_hiring_managers`
             WHERE `vacancy_id` = ?
               AND `tenant_id` = ?",
            PDOParams::positional([$vacancyId, $this->tenantId()])
        );

        $out = [];

        foreach ($rows as $row) {
            $out[] = Row::int($row, 'contact_id');
        }

        return $out;
    }

    /** @param int[] $contactIds */
    public function sync(int $vacancyId, array $contactIds, ?int $assignedBy = null): void
    {
        $tenantId = $this->tenantId();
        $organisationId = $this->organisationIdForVacancy($vacancyId);

        $clean = [];

        foreach ($contactIds as $value) {
            if (
                !(is_int($value) || (is_string($value) && ctype_digit($value)))
                || (int) $value <= 0
            ) {
                throw new \InvalidArgumentException('Invalid hiring-manager contact ID supplied.');
            }

            $clean[] = (int) $value;
        }

        $clean = array_values(array_unique($clean));

        if ($assignedBy !== null) {
            $this->assertTenantUser($assignedBy);
        }

        // Validate every contact before deleting existing assignments.
        foreach ($clean as $contactId) {
            $this->assertTenantContact($contactId, $organisationId);
        }

        $this->db->execute(
            "DELETE FROM `recruit_vacancy_hiring_managers`
             WHERE `vacancy_id` = ?
               AND `tenant_id` = ?",
            PDOParams::positional([$vacancyId, $tenantId])
        );

        foreach ($clean as $contactId) {
            $this->db->execute(
                "INSERT INTO `recruit_vacancy_hiring_managers`
                    (`tenant_id`, `vacancy_id`, `contact_id`, `assigned_by`)
                 VALUES (?, ?, ?, ?)",
                PDOParams::positional([
                    $tenantId,
                    $vacancyId,
                    $contactId,
                    $assignedBy,
                ])
            );
        }
    }
}

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

    /** @return int[] */
    public function contactIdsFor(int $vacancyId): array
    {
        $rows = $this->db->select(
            "SELECT `contact_id` FROM `recruit_vacancy_hiring_managers`
             WHERE `vacancy_id` = ? AND `tenant_id` = ?",
            PDOParams::positional([$vacancyId, $this->tenant->id()])
        );
        $out = [];
        foreach ($rows as $r) {
            $out[] = Row::int($r, 'contact_id');
        }
        return $out;
    }

    /** @param int[] $contactIds */
    public function sync(int $vacancyId, array $contactIds, ?int $assignedBy = null): void
    {
        $this->db->execute(
            "DELETE FROM `recruit_vacancy_hiring_managers` WHERE `vacancy_id` = ? AND `tenant_id` = ?",
            PDOParams::positional([$vacancyId, $this->tenant->id()])
        );

        if (empty($contactIds)) return;

        $clean = [];
        foreach ($contactIds as $v) {
            if ($v > 0) $clean[] = $v;
        }
        $clean = array_values(array_unique($clean));

        foreach ($clean as $cid) {
            $this->db->execute(
                "INSERT IGNORE INTO `recruit_vacancy_hiring_managers`
                    (`tenant_id`, `vacancy_id`, `contact_id`, `assigned_by`)
                 VALUES (?, ?, ?, ?)",
                PDOParams::positional([$this->tenant->id(), $vacancyId, $cid, $assignedBy])
            );
        }
    }
}

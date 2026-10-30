<?php
declare(strict_types=1);

namespace CorporateAid\Recruit\Repositories;

use CorporateAid\Core\Database;
use CorporateAid\Core\PDOParams;
use CorporateAid\Core\Row;

final class VacancyHiringManagerRepository
{
    public function __construct(private Database $db) {}

    /** @return int[] */
    public function contactIdsFor(int $vacancyId): array
    {
        $rows = $this->db->select(
            "SELECT `contact_id` FROM `recruit_vacancy_hiring_managers` WHERE `vacancy_id` = ?",
            PDOParams::positional([$vacancyId])
        );
        $out = [];
        foreach ($rows as $r) {
            $out[] = Row::int($r, 'contact_id');
        }
        return $out;
    }

    /**
     * Replace the full hiring-manager set for a vacancy.
     *
     * @param int[] $contactIds
     */
    public function sync(int $vacancyId, array $contactIds, ?int $assignedBy = null): void
    {
        $this->db->execute(
            "DELETE FROM `recruit_vacancy_hiring_managers` WHERE `vacancy_id` = ?",
            PDOParams::positional([$vacancyId])
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
                    (`vacancy_id`, `contact_id`, `assigned_by`)
                 VALUES (?, ?, ?)",
                PDOParams::positional([$vacancyId, $cid, $assignedBy])
            );
        }
    }
}

<?php
declare(strict_types=1);

namespace CorporateAid\Modules\HR\Handlers;

use CorporateAid\Core\Database;
use CorporateAid\Core\PDOParams;
use CorporateAid\Core\Row;

final class PersonHiredHandler
{
    public function __construct(private Database $db) {}

    /** @param array<string, mixed> $payload */
    public function __invoke(array $payload): void
    {
        $tenantId = Row::int($payload, 'tenant_id');
        $personId = Row::int($payload, 'person_id');
        $candidateId = Row::int($payload, 'candidate_id');

        // Check if employee already exists to make it idempotent
        $existing = $this->db->scalarInt(
            "SELECT COUNT(*) FROM `hr_employees` WHERE `person_id` = ?",
            PDOParams::positional([$personId])
        );

        if ($existing > 0) {
            return;
        }

        $this->db->insert(
            "INSERT INTO `hr_employees` (`tenant_id`, `person_id`, `candidate_id`, `status`) VALUES (?, ?, ?, 'draft')",
            PDOParams::positional([$tenantId, $personId, $candidateId])
        );
    }
}

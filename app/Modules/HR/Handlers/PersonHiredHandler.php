<?php
declare(strict_types=1);

namespace CorporateAid\Modules\HR\Handlers;

use CorporateAid\Core\Database;
use CorporateAid\Core\PDOParams;
use CorporateAid\Core\Row;
use RuntimeException;

final class PersonHiredHandler
{
    public function __construct(private Database $db) {}

    /** @param array<string, mixed> $payload */
    public function __invoke(array $payload): void
    {
        $tenantId = Row::int($payload, 'tenant_id');
        $personId = Row::int($payload, 'person_id');
        $candidateId = Row::int($payload, 'candidate_id');

        if ($tenantId < 1 || $personId < 1 || $candidateId < 1) {
            throw new RuntimeException('PersonHired event contains invalid ownership identifiers.');
        }

        // The candidate is tenant-scoped. Its person_id must match the
        // person supplied by the event before an employee can be created.
        $candidateMatches = $this->db->scalarInt(
            "SELECT COUNT(*)
             FROM `recruit_candidates`
             WHERE `id` = ?
               AND `tenant_id` = ?
               AND `person_id` = ?
               AND `deleted_at` IS NULL",
            PDOParams::positional([$candidateId, $tenantId, $personId])
        );

        if ($candidateMatches !== 1) {
            throw new RuntimeException('PersonHired event does not match a candidate owned by the supplied tenant.');
        }

        $personMatches = $this->db->scalarInt(
            "SELECT COUNT(*)
             FROM `core_persons`
             WHERE `id` = ?",
            PDOParams::positional([$personId])
        );

        if ($personMatches !== 1) {
            throw new RuntimeException('PersonHired event references a person that does not exist.');
        }

        // Idempotency is tenant-specific. The same global person may
        // legitimately be represented by candidates in different tenants.
        $existing = $this->db->scalarInt(
            "SELECT COUNT(*)
             FROM `hr_employees`
             WHERE `tenant_id` = ?
               AND `candidate_id` = ?",
            PDOParams::positional([$tenantId, $candidateId])
        );

        if ($existing > 0) {
            return;
        }

        $this->db->insert(
            "INSERT INTO `hr_employees` (`tenant_id`, `person_id`, `candidate_id`, `status`)
             VALUES (?, ?, ?, 'draft')",
            PDOParams::positional([$tenantId, $personId, $candidateId])
        );
    }
}

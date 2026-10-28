<?php
declare(strict_types=1);

namespace CorporateAid\Recruit\Repositories;

use CorporateAid\Core\Database;
use CorporateAid\Core\PDOParams;
use CorporateAid\Recruit\Models\Contact;

final class ContactRepository
{
    public function __construct(private Database $db) {}

    public function findById(int $id): ?Contact
    {
        $row = $this->db->selectOne(
            "SELECT * FROM `recruit_client_contacts` WHERE `id` = ? LIMIT 1",
            PDOParams::positional([$id])
        );
        return $row ? Contact::fromRow($row) : null;
    }

    public function findByCode(string $code, bool $includeDeleted = false): ?Contact
    {
        $sql = "SELECT * FROM `recruit_client_contacts` WHERE `code` = ?";
        if (!$includeDeleted) {
            $sql .= " AND `deleted_at` IS NULL";
        }
        $sql .= " LIMIT 1";

        $row = $this->db->selectOne($sql, PDOParams::positional([$code]));
        return $row ? Contact::fromRow($row) : null;
    }

    /** @return Contact[] */
    public function forOrganisation(int $organisationId, bool $includeDeleted = false): array
    {
        $sql = "SELECT * FROM `recruit_client_contacts` WHERE `organisation_id` = ?";
        if (!$includeDeleted) {
            $sql .= " AND `deleted_at` IS NULL";
        }
        $sql .= " ORDER BY `is_primary` DESC, `last_name` ASC, `first_name` ASC";

        $rows = $this->db->select($sql, PDOParams::positional([$organisationId]));
        return array_map([Contact::class, 'fromRow'], $rows);
    }

    public function countForOrganisation(int $organisationId): int
    {
        return $this->db->scalarInt(
            "SELECT COUNT(*) FROM `recruit_client_contacts`
             WHERE `organisation_id` = ? AND `deleted_at` IS NULL",
            PDOParams::positional([$organisationId])
        );
    }

    public function codeExists(string $code): bool
    {
        return $this->db->scalarInt(
            "SELECT COUNT(*) FROM `recruit_client_contacts` WHERE `code` = ?",
            PDOParams::positional([$code])
        ) > 0;
    }

    public function nextCode(): string
    {
        $this->db->execute(
            "UPDATE `core_sequence_counters` SET `last_value` = `last_value` + 1 WHERE `name` = 'rec_client_contact_code'"
        );
        $num = $this->db->scalarInt(
            "SELECT `last_value` FROM `core_sequence_counters` WHERE `name` = 'rec_client_contact_code'"
        );

        return 'CNT-' . str_pad((string) $num, 6, '0', STR_PAD_LEFT);
    }

    /**
     * @param array{
     *   organisation_id: int,
     *   code: string,
     *   first_name: string,
     *   last_name: string,
     *   position?: ?string,
     *   department?: ?string,
     *   email?: ?string,
     *   phone?: ?string,
     *   alt_phone?: ?string,
     *   country_code?: ?string,
     *   preferred_communication?: string,
     *   is_primary?: bool,
     *   is_hiring_manager?: bool,
     *   is_finance?: bool,
     *   is_hr?: bool,
     *   is_decision_maker?: bool,
     *   notes?: ?string,
     *   created_by?: ?int
     * } $data
     */
    public function create(array $data): int
    {
        $sql = "INSERT INTO `recruit_client_contacts`
            (`organisation_id`, `code`, `first_name`, `last_name`,
             `position`, `department`, `email`, `phone`, `alt_phone`,
             `country_code`, `preferred_communication`,
             `is_primary`, `is_hiring_manager`, `is_finance`, `is_hr`, `is_decision_maker`,
             `notes`, `created_by`)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        return $this->db->insert($sql, PDOParams::positional([
            $data['organisation_id'],
            $data['code'],
            $data['first_name'],
            $data['last_name'],
            $data['position'] ?? null,
            $data['department'] ?? null,
            $data['email'] ?? null,
            $data['phone'] ?? null,
            $data['alt_phone'] ?? null,
            $data['country_code'] ?? null,
            $data['preferred_communication'] ?? 'email',
            !empty($data['is_primary']) ? 1 : 0,
            !empty($data['is_hiring_manager']) ? 1 : 0,
            !empty($data['is_finance']) ? 1 : 0,
            !empty($data['is_hr']) ? 1 : 0,
            !empty($data['is_decision_maker']) ? 1 : 0,
            $data['notes'] ?? null,
            $data['created_by'] ?? null,
        ]));
    }

    /** @return array<string, array{old:mixed,new:mixed}> */
    public function update(int $id, array $data): array
    {
        $existing = $this->findById($id);
        if (!$existing) {
            throw new \RuntimeException('Contact not found.');
        }

        $allowed = [
            'first_name', 'last_name', 'position', 'department',
            'email', 'phone', 'alt_phone', 'country_code', 'preferred_communication',
            'is_primary', 'is_hiring_manager', 'is_finance', 'is_hr', 'is_decision_maker',
            'notes',
        ];

        $sets = [];
        $params = [];
        $changes = [];

        foreach ($allowed as $col) {
            if (!array_key_exists($col, $data)) continue;

            $newVal = $data[$col];

            $prop = match ($col) {
                'first_name'              => 'firstName',
                'last_name'               => 'lastName',
                'alt_phone'               => 'altPhone',
                'country_code'            => 'countryCode',
                'preferred_communication' => 'preferredCommunication',
                'is_primary'              => 'isPrimary',
                'is_hiring_manager'       => 'isHiringManager',
                'is_finance'              => 'isFinance',
                'is_hr'                   => 'isHr',
                'is_decision_maker'       => 'isDecisionMaker',
                default                   => $col,
            };
            $oldVal = $existing->$prop ?? null;

            $newForCompare = $newVal;
            if (in_array($col, ['is_primary','is_hiring_manager','is_finance','is_hr','is_decision_maker'], true)) {
                $newForCompare = (bool) $newVal;
            }

            if ($oldVal != $newForCompare) {
                $changes[$col] = ['old' => $oldVal, 'new' => $newForCompare];
            }

            $sets[] = "`{$col}` = ?";
            if (in_array($col, ['is_primary','is_hiring_manager','is_finance','is_hr','is_decision_maker'], true)) {
                $params[] = !empty($newVal) ? 1 : 0;
            } else {
                $params[] = $newVal;
            }
        }

        if (empty($sets)) return [];

        $params[] = $id;
        $this->db->execute(
            "UPDATE `recruit_client_contacts` SET " . implode(', ', $sets) . " WHERE `id` = ?",
            PDOParams::positional($params)
        );

        return $changes;
    }

    public function softDelete(int $id): void
    {
        $this->db->execute(
            "UPDATE `recruit_client_contacts` SET `deleted_at` = NOW() WHERE `id` = ?",
            PDOParams::positional([$id])
        );
    }

    public function restore(int $id): void
    {
        $this->db->execute(
            "UPDATE `recruit_client_contacts` SET `deleted_at` = NULL WHERE `id` = ?",
            PDOParams::positional([$id])
        );
    }

    /** @return Contact[] */
    public function allActive(): array
    {
        $rows = $this->db->select(
            "SELECT * FROM `recruit_client_contacts`
             WHERE `deleted_at` IS NULL
             ORDER BY `organisation_id`, `is_primary` DESC, `last_name` ASC, `first_name` ASC"
        );
        return array_map([Contact::class, 'fromRow'], $rows);
    }
}

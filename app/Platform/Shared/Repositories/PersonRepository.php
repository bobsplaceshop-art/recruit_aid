<?php
declare(strict_types=1);

namespace CorporateAid\Platform\Shared\Repositories;

use CorporateAid\Core\Database;
use CorporateAid\Core\PDOParams;
use CorporateAid\Platform\Shared\Models\Person;

final class PersonRepository
{
    public function __construct(private Database $db) {}

    public function findById(int $id): ?Person
    {
        $row = $this->db->selectOne(
            "SELECT * FROM `core_persons` WHERE `id` = ? AND `deleted_at` IS NULL LIMIT 1",
            PDOParams::positional([$id])
        );
        return $row ? Person::fromRow($row) : null;
    }

    public function findByCode(string $code): ?Person
    {
        $row = $this->db->selectOne(
            "SELECT * FROM `core_persons` WHERE `code` = ? AND `deleted_at` IS NULL LIMIT 1",
            PDOParams::positional([$code])
        );
        return $row ? Person::fromRow($row) : null;
    }

    /**
     * Find a person by exact name match. Used by the backfill script
     * to avoid creating duplicate persons for the same candidate
     * across runs. Case-sensitive on purpose — we want a strict match
     * here, not a fuzzy one.
     */
    public function findByName(string $firstName, string $lastName): ?Person
    {
        $row = $this->db->selectOne(
            "SELECT * FROM `core_persons`
             WHERE `first_name` = ? AND `last_name` = ? AND `deleted_at` IS NULL
             LIMIT 1",
            PDOParams::positional([$firstName, $lastName])
        );
        return $row ? Person::fromRow($row) : null;
    }

    /**
     * @param array{
     *   code?: string,
     *   first_name: string,
     *   last_name: string,
     *   preferred_name?: ?string,
     *   gender?: ?string,
     *   date_of_birth?: ?string,
     *   nationality?: ?string,
     *   created_by?: ?int
     * } $data
     */
    public function create(array $data): int
    {
        $code = $data['code'] ?? $this->nextCode();

        return $this->db->insert(
            "INSERT INTO `core_persons`
                (`code`, `first_name`, `last_name`, `preferred_name`,
                 `gender`, `date_of_birth`, `nationality`, `created_by`)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            PDOParams::positional([
                $code,
                $data['first_name'],
                $data['last_name'],
                $data['preferred_name'] ?? null,
                $data['gender'] ?? null,
                $data['date_of_birth'] ?? null,
                $data['nationality'] ?? null,
                $data['created_by'] ?? null,
            ])
        );
    }

    public function nextCode(): string
    {
        $this->db->execute(
            "UPDATE `core_sequence_counters` SET `last_value` = `last_value` + 1 WHERE `name` = 'core_person_code'"
        );
        $num = $this->db->scalarInt(
            "SELECT `last_value` FROM `core_sequence_counters` WHERE `name` = 'core_person_code'"
        );
        return 'PER-' . str_pad((string) $num, 6, '0', STR_PAD_LEFT);
    }
}

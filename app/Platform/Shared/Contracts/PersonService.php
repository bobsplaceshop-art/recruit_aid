<?php
declare(strict_types=1);

namespace CorporateAid\Platform\Shared\Contracts;

use CorporateAid\Platform\Shared\Models\Person;

/**
 * Write/read boundary for canonical persons.
 *
 * create() is a single INSERT and does NOT open a transaction — the
 * caller owns transactional boundaries. Any caller that needs the
 * person insert to be atomic with other writes must wrap the calls
 * itself (see CandidateService::create()).
 */
interface PersonService
{
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
     * @return int New person id
     */
    public function create(array $data): int;

    public function findById(int $id): ?Person;

    public function findByCode(string $code): ?Person;

    public function findByName(string $firstName, string $lastName): ?Person;
}

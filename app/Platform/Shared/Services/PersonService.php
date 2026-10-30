<?php
declare(strict_types=1);

namespace CorporateAid\Platform\Shared\Services;

use CorporateAid\Platform\Shared\Contracts\PersonService as PersonServiceContract;
use CorporateAid\Platform\Shared\Models\Person;
use CorporateAid\Platform\Shared\Repositories\PersonRepository;

/**
 * Thin service over PersonRepository. No business logic yet beyond
 * delegation; the seam exists so CandidateService depends on the
 * contract rather than the repository, and so Person validation /
 * dedup can be added here without touching callers.
 *
 * Transaction policy: create() is a bare INSERT. Callers that need
 * atomicity with other writes must open the transaction themselves.
 */
final class PersonService implements PersonServiceContract
{
    public function __construct(private PersonRepository $persons) {}

    public function create(array $data): int
    {
        return $this->persons->create($data);
    }

    public function findById(int $id): ?Person
    {
        return $this->persons->findById($id);
    }

    public function findByCode(string $code): ?Person
    {
        return $this->persons->findByCode($code);
    }

    public function findByName(string $firstName, string $lastName): ?Person
    {
        return $this->persons->findByName($firstName, $lastName);
    }
}

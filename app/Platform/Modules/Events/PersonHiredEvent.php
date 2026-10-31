<?php
declare(strict_types=1);

namespace CorporateAid\Platform\Modules\Events;

final class PersonHiredEvent
{
    public const TYPE = 'recruit.person.hired';

    public function __construct(
        public readonly int $personId,
        public readonly int $candidateId,
        public readonly int $tenantId
    ) {}
}

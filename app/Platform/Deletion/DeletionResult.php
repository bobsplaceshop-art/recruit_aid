<?php
declare(strict_types=1);

namespace CorporateAid\Platform\Deletion;

/**
 * Value object describing the outcome of a deletion inspection or action.
 */
final class DeletionResult
{
    /** @param array<int, array{label:string, count:int, route:string}> $dependents */
    public function __construct(
        public readonly bool $success,
        public readonly string $message,
        public readonly bool $hasDependents = false,
        public readonly array $dependents = [],
        public readonly string $requiredPhrase = '',
        public readonly int $deletedCount = 0
    ) {
    }

    public static function failure(string $message): self
    {
        return new self(false, $message);
    }

    public static function success(string $message, int $deletedCount = 0): self
    {
        return new self(true, $message, false, [], '', $deletedCount);
    }

    public static function inspection(
        bool $hasDependents,
        array $dependents,
        string $requiredPhrase
    ): self {
        return new self(true, '', $hasDependents, $dependents, $requiredPhrase);
    }

    public function dependentCount(): int
    {
        $total = 0;
        foreach ($this->dependents as $d) {
            $total += $d['count'];
        }
        return $total;
    }
}

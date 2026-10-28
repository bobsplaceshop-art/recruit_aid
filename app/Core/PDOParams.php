<?php
declare(strict_types=1);

namespace CorporateAid\Core;

/**
 * Value object wrapping the parameters passed to a prepared statement.
 *
 * PDO::execute() accepts two shapes:
 *   - positional: [1, 'foo', null]
 *   - named:      [':id' => 1, ':name' => 'foo']
 *
 * This class makes the distinction explicit at the call site so
 * PHPStan can verify what's being passed.
 *
 * Most callers use positional. Named is available when a query needs
 * the same parameter multiple times.
 */
final class PDOParams
{
    /** @var array<int|string, mixed> */
    private array $values;

    /**
     * @param array<int|string, mixed> $values
     */
    private function __construct(array $values)
    {
        $this->values = $values;
    }

    public static function empty(): self
    {
        return new self([]);
    }

    /**
     * @param array<int, mixed> $values
     */
    public static function positional(array $values): self
    {
        return new self(array_values($values));
    }

    /**
     * @param array<string, mixed> $values
     */
    public static function named(array $values): self
    {
        return new self($values);
    }

    /**
     * Extract the raw values for PDOStatement::execute().
     *
     * @return array<int|string, mixed>
     */
    public function toArray(): array
    {
        return $this->values;
    }
}

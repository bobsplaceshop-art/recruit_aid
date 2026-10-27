<?php
declare(strict_types=1);

namespace CorporateAid\Platform\Deletion;

/**
 * Generates and verifies confirmation phrases for destructive actions.
 *
 *   DELETE                    — single record, no dependents
 *   DELETE WITH CHILDREN      — single record with dependents
 *   DELETE 5                  — bulk, no dependents
 *   DELETE 5 WITH CHILDREN    — bulk with dependents
 */
final class ConfirmationPhrase
{
    public const SINGLE_NO_CHILDREN    = 'DELETE';
    public const SINGLE_WITH_CHILDREN  = 'DELETE WITH CHILDREN';

    public static function forSingle(bool $hasDependents): string
    {
        return $hasDependents ? self::SINGLE_WITH_CHILDREN : self::SINGLE_NO_CHILDREN;
    }

    public static function forBulk(int $count, bool $hasDependents): string
    {
        if ($count < 1) {
            throw new \InvalidArgumentException('Bulk confirmation requires at least one record.');
        }
        $base = 'DELETE ' . $count;
        return $hasDependents ? $base . ' WITH CHILDREN' : $base;
    }

    public static function verify(string $expected, string $typed): bool
    {
        $normalize = static function (string $s): string {
            $s = strtoupper(trim($s));
            $s = preg_replace('/\s+/', ' ', $s) ?? '';
            return $s;
        };

        return $normalize($expected) === $normalize($typed);
    }
}

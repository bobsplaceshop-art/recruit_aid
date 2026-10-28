<?php
declare(strict_types=1);

namespace CorporateAid\Core;

/**
 * Typed accessors for rows returned by Database::select()/selectOne().
 *
 * Database returns `array<int, array<string, mixed>>` — every column
 * value is `mixed` from PHP's perspective. Casting at the call site
 * (`(int) $row['id']`) triggers PHPStan Level 9's cast.string/cast.int
 * warnings.
 *
 * These helpers centralize the "read a typed value with a fallback"
 * pattern. The guard is real: if the underlying value is not numeric
 * or not scalar, the default is returned — no silent corruption.
 *
 * Usage:
 *   $id    = Row::int($row, 'id');
 *   $name  = Row::str($row, 'name');
 *   $ok    = Row::bool($row, 'is_active');
 *   $uuid  = Row::nullableStr($row, 'uuid');
 */
final class Row
{
    /**
     * @param array<string, mixed> $row
     */
    public static function int(array $row, string $key, int $default = 0): int
    {
        $v = $row[$key] ?? null;
        return is_numeric($v) ? (int) $v : $default;
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function str(array $row, string $key, string $default = ''): string
    {
        $v = $row[$key] ?? null;
        return is_scalar($v) ? (string) $v : $default;
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function bool(array $row, string $key, bool $default = false): bool
    {
        $v = $row[$key] ?? null;
        if ($v === null) return $default;
        return (bool) (is_numeric($v) ? (int) $v : 0);
    }

    /**
     * Returns null when the key is missing or the value is null.
     * Returns the string representation otherwise.
     *
     * @param array<string, mixed> $row
     */
    public static function nullableStr(array $row, string $key): ?string
    {
        $v = $row[$key] ?? null;
        if ($v === null) return null;
        return is_scalar($v) ? (string) $v : null;
    }

    /**
     * Returns null when the key is missing or the value is null.
     * Returns the int representation otherwise.
     *
     * @param array<string, mixed> $row
     */
    public static function nullableInt(array $row, string $key): ?int
    {
        $v = $row[$key] ?? null;
        if ($v === null) return null;
        return is_numeric($v) ? (int) $v : null;
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function float(array $row, string $key, float $default = 0.0): float
    {
        $v = $row[$key] ?? null;
        return is_numeric($v) ? (float) $v : $default;
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function nullableBool(array $row, string $key): ?bool
    {
        $v = $row[$key] ?? null;
        if ($v === null) return null;
        return (bool) (is_numeric($v) ? (int) $v : 0);
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function nullableFloat(array $row, string $key): ?float
    {
        $v = $row[$key] ?? null;
        if ($v === null) return null;
        return is_numeric($v) ? (float) $v : null;
    }
}

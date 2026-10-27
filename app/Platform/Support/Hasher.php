<?php
declare(strict_types=1);

namespace CorporateAid\Platform\Support;

use RuntimeException;

/**
 * Password hashing helpers. Uses Argon2id when available, else bcrypt.
 */
final class Hasher
{
    /**
     * Return the algorithm identifier to use with password_hash().
     */
    public static function algorithm(): string|int
    {
        return defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
    }

    /**
     * Hash a plaintext password.
     */
    public static function hash(string $plain): string
    {
        $hash = password_hash($plain, self::algorithm());
        if ($hash === false) {
            throw new RuntimeException('Password hashing failed.');
        }
        return $hash;
    }

    /**
     * Verify a plaintext password against a stored hash.
     */
    public static function verify(string $plain, string $hash): bool
    {
        if ($hash === '') {
            return false;
        }
        return password_verify($plain, $hash);
    }

    /**
     * True if $hash was produced with a weaker/older algorithm than current.
     */
    public static function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, self::algorithm());
    }

    /**
     * Generate a random token (for password resets, remember-me, etc.).
     */
    public static function randomToken(int $bytes = 32): string
    {
        return bin2hex(random_bytes($bytes));
    }
}

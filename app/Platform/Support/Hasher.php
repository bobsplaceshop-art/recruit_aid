<?php
declare(strict_types=1);

namespace CorporateAid\Platform\Support;

use RuntimeException;
use ValueError;

/**
 * Password hashing helpers. Uses Argon2id (PHP 7.4+ core).
 */
final class Hasher
{
    /**
     * Return the algorithm identifier to use with password_hash().
     * In PHP 8.0+, both PASSWORD_ARGON2ID and PASSWORD_BCRYPT are string constants.
     */
    public static function algorithm(): string
    {
        return PASSWORD_ARGON2ID;
    }

    /**
     * Hash a plaintext password.
     *
     * @throws RuntimeException if password hashing fails
     */
    public static function hash(string $plain): string
    {
        try {
            $hash = password_hash($plain, self::algorithm());
        } catch (ValueError $e) {
            throw new RuntimeException('Password hashing failed: ' . $e->getMessage(), 0, $e);
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
        return bin2hex(random_bytes(max(1, $bytes)));
    }
}

<?php
declare(strict_types=1);

namespace CorporateAid\Platform\Repositories;

use CorporateAid\Core\Database;

/**
 * Manages password reset tokens.
 * Tokens are stored hashed (SHA-256) — never in plaintext.
 */
final class PasswordResetRepository
{
    private const TOKEN_BYTES = 32;      // 64 hex chars
    private const TTL_MINUTES = 60;      // valid for 1 hour

    public function __construct(private Database $db) {}

    /**
     * Create a reset token for an email.
     * Deletes any existing tokens for that email first (only one active token).
     *
     * @return string The plaintext token to send to the user (only shown once)
     */
    public function createToken(string $email): string
    {
        $email = strtolower(trim($email));

        // Clear old tokens for this email
        $this->db->execute("DELETE FROM `core_password_resets` WHERE `email` = ?", [$email]);

        $plain = bin2hex(random_bytes(self::TOKEN_BYTES));
        $hash = hash('sha256', $plain);

        $this->db->execute(
            "INSERT INTO `core_password_resets` (`email`, `token_hash`, `expires_at`)
             VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE))",
            [$email, $hash, self::TTL_MINUTES]
        );

        return $plain;
    }

    /**
     * Look up a valid (non-expired) reset by plaintext token.
     * Returns the email or null.
     */
    public function findEmailByToken(string $plainToken): ?string
    {
        $hash = hash('sha256', $plainToken);

        $row = $this->db->selectOne(
            "SELECT `email` FROM `core_password_resets`
             WHERE `token_hash` = ? AND `expires_at` > NOW()
             LIMIT 1",
            [$hash]
        );

        return $row ? (string) $row['email'] : null;
    }

    /**
     * Consume a token (delete after successful reset).
     */
    public function consumeToken(string $plainToken): void
    {
        $hash = hash('sha256', $plainToken);
        $this->db->execute("DELETE FROM `core_password_resets` WHERE `token_hash` = ?", [$hash]);
    }

    /**
     * Cleanup expired tokens. Called by cron.
     */
    public function purgeExpired(): int
    {
        return $this->db->execute("DELETE FROM `core_password_resets` WHERE `expires_at` < NOW()");
    }
}

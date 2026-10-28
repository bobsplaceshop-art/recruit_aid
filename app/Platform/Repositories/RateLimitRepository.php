<?php
declare(strict_types=1);

namespace CorporateAid\Platform\Repositories;

use CorporateAid\Core\Database;
use CorporateAid\Core\PDOParams;

final class RateLimitRepository
{
    public function __construct(private Database $db) {}

    /**
     * Increment the counter for a bucket. Creates the row if missing.
     * Returns the new attempt count.
     */
    public function hit(string $bucketKey, int $windowMinutes): int
    {
        $this->db->execute(
            "INSERT INTO `core_rate_limits` (`bucket_key`, `attempts`, `reset_at`)
             VALUES (?, 1, DATE_ADD(NOW(), INTERVAL ? MINUTE))
             ON DUPLICATE KEY UPDATE
                `attempts` = IF(`reset_at` <= NOW(), 1, `attempts` + 1),
                `reset_at` = IF(`reset_at` <= NOW(), DATE_ADD(NOW(), INTERVAL ? MINUTE), `reset_at`)",
            PDOParams::positional([$bucketKey, $windowMinutes, $windowMinutes])
        );

        return $this->currentAttempts($bucketKey);
    }

    public function currentAttempts(string $bucketKey): int
    {
        return $this->db->scalarInt(
            "SELECT `attempts` FROM `core_rate_limits`
             WHERE `bucket_key` = ? AND `reset_at` > NOW()",
            PDOParams::positional([$bucketKey])
        );
    }

    public function secondsUntilReset(string $bucketKey): int
    {
        $seconds = $this->db->scalarInt(
            "SELECT TIMESTAMPDIFF(SECOND, NOW(), `reset_at`) FROM `core_rate_limits`
             WHERE `bucket_key` = ? AND `reset_at` > NOW()",
            PDOParams::positional([$bucketKey])
        );
        return max(0, $seconds);
    }

    public function clear(string $bucketKey): void
    {
        $this->db->execute(
            "DELETE FROM `core_rate_limits` WHERE `bucket_key` = ?",
            PDOParams::positional([$bucketKey])
        );
    }

    public function purgeExpired(): int
    {
        return $this->db->execute("DELETE FROM `core_rate_limits` WHERE `reset_at` < NOW()");
    }
}

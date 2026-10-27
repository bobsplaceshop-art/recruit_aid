<?php
declare(strict_types=1);

namespace CorporateAid\Platform\Repositories;

use CorporateAid\Core\Database;

final class RateLimitRepository
{
    public function __construct(private Database $db) {}

    /**
     * Increment the counter for a bucket. Creates the row if missing.
     * Returns the new attempt count.
     */
    public function hit(string $bucketKey, int $windowMinutes): int
    {
        // Upsert: on duplicate, increment attempts
        // Uses MySQL's INSERT ... ON DUPLICATE KEY UPDATE with conditional reset.
        $this->db->execute(
            "INSERT INTO `core_rate_limits` (`bucket_key`, `attempts`, `reset_at`)
             VALUES (?, 1, DATE_ADD(NOW(), INTERVAL ? MINUTE))
             ON DUPLICATE KEY UPDATE
                `attempts` = IF(`reset_at` <= NOW(), 1, `attempts` + 1),
                `reset_at` = IF(`reset_at` <= NOW(), DATE_ADD(NOW(), INTERVAL ? MINUTE), `reset_at`)",
            [$bucketKey, $windowMinutes, $windowMinutes]
        );

        return $this->currentAttempts($bucketKey);
    }

    /**
     * Read the current attempt count (0 if expired or missing).
     */
    public function currentAttempts(string $bucketKey): int
    {
        $count = $this->db->scalar(
            "SELECT `attempts` FROM `core_rate_limits`
             WHERE `bucket_key` = ? AND `reset_at` > NOW()",
            [$bucketKey]
        );
        return (int) ($count ?? 0);
    }

    /**
     * Read the seconds until the bucket resets. 0 if no active bucket.
     */
    public function secondsUntilReset(string $bucketKey): int
    {
        $seconds = $this->db->scalar(
            "SELECT TIMESTAMPDIFF(SECOND, NOW(), `reset_at`) FROM `core_rate_limits`
             WHERE `bucket_key` = ? AND `reset_at` > NOW()",
            [$bucketKey]
        );
        return max(0, (int) ($seconds ?? 0));
    }

    /**
     * Clear a bucket (called on successful action).
     */
    public function clear(string $bucketKey): void
    {
        $this->db->execute("DELETE FROM `core_rate_limits` WHERE `bucket_key` = ?", [$bucketKey]);
    }

    /**
     * Purge expired rows. Called by cron.
     */
    public function purgeExpired(): int
    {
        return $this->db->execute("DELETE FROM `core_rate_limits` WHERE `reset_at` < NOW()");
    }
}

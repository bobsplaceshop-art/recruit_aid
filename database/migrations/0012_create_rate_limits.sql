-- ============================================================
-- Recruit Aid — Rate Limiting
-- Tracks per-IP attempt counts for sensitive endpoints.
-- ============================================================

CREATE TABLE IF NOT EXISTS `core_rate_limits` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `bucket_key` VARCHAR(180) NOT NULL,
    `attempts`   INT UNSIGNED NOT NULL DEFAULT 0,
    `reset_at`   DATETIME NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_bucket` (`bucket_key`),
    KEY `idx_reset` (`reset_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

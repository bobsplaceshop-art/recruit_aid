-- =====================================================================
-- 0042_create_module_events.sql
-- Cross-module event outbox.
-- =====================================================================

CREATE TABLE IF NOT EXISTS `core_module_events` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `event_type`    VARCHAR(100) NOT NULL,
    `payload`       JSON NOT NULL,
    `status`        ENUM('pending','processing','completed','failed') NOT NULL DEFAULT 'pending',
    `attempts`      TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `last_error`    TEXT NULL,
    `scheduled_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `processed_at`  DATETIME NULL,
    PRIMARY KEY (`id`),
    KEY `idx_event_status` (`status`, `scheduled_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

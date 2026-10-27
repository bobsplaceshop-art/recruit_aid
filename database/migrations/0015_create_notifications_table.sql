-- ============================================================
-- Recruit Aid — Notifications
-- In-app notifications per user (bell icon in topbar).
-- ============================================================

CREATE TABLE IF NOT EXISTS `core_notifications` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`    INT UNSIGNED NOT NULL,
    `type`       VARCHAR(60) NOT NULL,
    `title`      VARCHAR(255) NOT NULL,
    `body`       TEXT NULL,
    `link`       VARCHAR(500) NULL,
    `read_at`    DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user_read` (`user_id`, `read_at`),
    KEY `idx_user_created` (`user_id`, `created_at`),
    KEY `idx_type` (`type`),
    CONSTRAINT `fk_core_notif_user` FOREIGN KEY (`user_id`)
        REFERENCES `core_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

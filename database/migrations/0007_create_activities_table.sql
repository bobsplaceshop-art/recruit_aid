-- ============================================================
-- Recruit Aid — activities table
-- Business-visible timeline of what happened in the app.
-- Distinct from audit_logs (security-focused).
-- ============================================================

CREATE TABLE IF NOT EXISTS `core_activities` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`      INT UNSIGNED NULL,
    `action`       VARCHAR(80) NOT NULL,
    `module`       VARCHAR(60) NOT NULL,
    `subject_type` VARCHAR(80) NULL,
    `subject_id`   BIGINT UNSIGNED NULL,
    `description`  TEXT NULL,
    `meta`         JSON NULL,
    `ip_address`   VARBINARY(16) NULL,
    `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user` (`user_id`),
    KEY `idx_module` (`module`),
    KEY `idx_subject` (`subject_type`, `subject_id`),
    KEY `idx_created` (`created_at`),
    CONSTRAINT `fk_core_act_user` FOREIGN KEY (`user_id`) REFERENCES `core_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

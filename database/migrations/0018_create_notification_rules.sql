-- ============================================================
-- Recruit Aid — Configurable notification rules
-- ============================================================
-- Each row defines one recipient rule for one event type.
-- The NotificationRuleService resolves these into user IDs at
-- dispatch time, so admins can change who gets what without
-- code changes.
-- ============================================================

CREATE TABLE IF NOT EXISTS `core_notification_rules` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `event_type`      VARCHAR(80) NOT NULL,
    `recipient_type`  ENUM(
                          'role',
                          'permission',
                          'user',
                          'assigned_recruiter',
                          'engagement_member',
                          'organisation_client_admin',
                          'organisation_client_user',
                          'hiring_manager',
                          'owner',
                          'actor_except'
                      ) NOT NULL,
    `recipient_value` VARCHAR(120) NULL,
    `channel_inapp`   TINYINT(1) NOT NULL DEFAULT 1,
    `channel_email`   TINYINT(1) NOT NULL DEFAULT 1,
    `is_system`       TINYINT(1) NOT NULL DEFAULT 0,
    `is_active`       TINYINT(1) NOT NULL DEFAULT 1,
    `notes`           VARCHAR(500) NULL,
    `created_by`      INT UNSIGNED NULL,
    `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`      DATETIME NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_event_recipient` (`event_type`, `recipient_type`, `recipient_value`),
    KEY `idx_event` (`event_type`, `is_active`),
    KEY `idx_recipient` (`recipient_type`, `recipient_value`),
    CONSTRAINT `fk_core_nr_created_by` FOREIGN KEY (`created_by`)
        REFERENCES `core_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Recruit Aid — Engagement access assignments
-- ============================================================
-- Grants specific users, roles, or permission groups
-- access to an engagement and its children (vacancies,
-- candidates, applications).
--
-- Distinct from engagement_recruiters (who does the work);
-- this table controls who can VIEW and EDIT.
-- ============================================================

CREATE TABLE IF NOT EXISTS `recruit_engagement_assignments` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `engagement_id`   INT UNSIGNED NOT NULL,
    `assignee_type`   ENUM('role','permission','user') NOT NULL,
    `assignee_value`  VARCHAR(120) NOT NULL,
    `can_view`        TINYINT(1) NOT NULL DEFAULT 1,
    `can_edit`        TINYINT(1) NOT NULL DEFAULT 0,
    `assigned_by`     INT UNSIGNED NULL,
    `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_engagement_assignee` (`engagement_id`, `assignee_type`, `assignee_value`),
    KEY `idx_engagement` (`engagement_id`),
    KEY `idx_assignee` (`assignee_type`, `assignee_value`),
    CONSTRAINT `fk_rec_ea_engagement` FOREIGN KEY (`engagement_id`)
        REFERENCES `recruit_engagements` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_rec_ea_assigned_by` FOREIGN KEY (`assigned_by`)
        REFERENCES `core_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

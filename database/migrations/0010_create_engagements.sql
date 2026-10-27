-- ============================================================
-- Recruit Aid — Engagements
-- The recruitment contract between the agency and a client.
-- An engagement defines scope, duration, capacity, and team.
-- ============================================================

-- ---------- engagement_packages ----------
-- Predefined templates that pre-fill an engagement's defaults.
CREATE TABLE IF NOT EXISTS `recruit_engagement_packages` (
    `id`                          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `slug`                        VARCHAR(60) NOT NULL,
    `name`                        VARCHAR(150) NOT NULL,
    `service_type`                ENUM('3_month','6_month','12_month','focused_search','assessment') NOT NULL,
    `default_duration_days`       INT UNSIGNED NOT NULL DEFAULT 90,
    `default_active_role_capacity` INT UNSIGNED NOT NULL DEFAULT 3,
    `default_reporting_frequency` ENUM('weekly','monthly','quarterly','custom') NOT NULL DEFAULT 'monthly',
    `description`                 TEXT NULL,
    `is_active`                   TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`                  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`                  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed default packages
INSERT INTO `recruit_engagement_packages`
    (`slug`, `name`, `service_type`, `default_duration_days`, `default_active_role_capacity`, `default_reporting_frequency`, `description`)
VALUES
    ('hiring_support_3m',   '3-Month Hiring Support',     '3_month',        90,  5,  'weekly',   'Urgent vacancies, defined campaigns, or project mobilisation.'),
    ('embedded_6m',         '6-Month Embedded Recruitment','6_month',       180,  10, 'monthly',  'Several planned hires, market entry, or sustained team growth.'),
    ('partnership_12m',     '12-Month Recruitment Partnership','12_month',  365,  20, 'quarterly','Continuous hiring, regional expansion, or ongoing recruitment capacity.'),
    ('focused_search',      'Focused Search',             'focused_search', 90,  1,  'monthly',  'Single leadership, specialist, or difficult-to-fill position.'),
    ('applicant_assessment','Applicant Assessment',       'assessment',     30,  1,  'monthly',  'Independent screening and assessment of existing applicants.')
ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `service_type` = VALUES(`service_type`),
    `description` = VALUES(`description`);

-- ---------- engagements ----------
CREATE TABLE IF NOT EXISTS `recruit_engagements` (
    `id`                          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code`                        VARCHAR(20) NOT NULL,
    `organisation_id`             INT UNSIGNED NOT NULL,
    `package_id`                  INT UNSIGNED NULL,
    `name`                        VARCHAR(200) NOT NULL,
    `service_type`                ENUM('3_month','6_month','12_month','focused_search','assessment') NOT NULL,
    `status`                      ENUM('draft','proposal','pending_agreement','active','paused','completed','cancelled') NOT NULL DEFAULT 'draft',
    `start_date`                  DATE NULL,
    `end_date`                    DATE NULL,
    `agreed_active_role_capacity` INT UNSIGNED NOT NULL DEFAULT 1,
    `reporting_frequency`         ENUM('weekly','monthly','quarterly','custom') NOT NULL DEFAULT 'monthly',
    `countries`                   JSON NULL,
    `scope_description`           TEXT NULL,
    `stages_included`             JSON NULL,
    `notes`                       TEXT NULL,
    `created_by`                  INT UNSIGNED NULL,
    `created_at`                  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`                  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`                  DATETIME NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_code` (`code`),
    KEY `idx_org` (`organisation_id`),
    KEY `idx_status` (`status`),
    KEY `idx_service_type` (`service_type`),
    KEY `idx_dates` (`start_date`, `end_date`),
    KEY `idx_deleted` (`deleted_at`),
    CONSTRAINT `fk_rec_eng_org` FOREIGN KEY (`organisation_id`)
        REFERENCES `recruit_client_organisations` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_rec_eng_package` FOREIGN KEY (`package_id`)
        REFERENCES `recruit_engagement_packages` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_rec_eng_created_by` FOREIGN KEY (`created_by`)
        REFERENCES `core_users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `chk_rec_eng_code_format` CHECK (`code` REGEXP '^ENG-[0-9]{6}$'),
    CONSTRAINT `chk_rec_eng_capacity` CHECK (`agreed_active_role_capacity` >= 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- engagement_recruiters ----------
CREATE TABLE IF NOT EXISTS `recruit_engagement_recruiters` (
    `engagement_id` INT UNSIGNED NOT NULL,
    `user_id`       INT UNSIGNED NOT NULL,
    `role`          ENUM('lead','recruiter','researcher') NOT NULL DEFAULT 'recruiter',
    `assigned_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`engagement_id`, `user_id`),
    KEY `idx_user` (`user_id`),
    CONSTRAINT `fk_rec_engrec_engagement` FOREIGN KEY (`engagement_id`)
        REFERENCES `recruit_engagements` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_rec_engrec_user` FOREIGN KEY (`user_id`)
        REFERENCES `core_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ensure sequence counter for engagement codes
INSERT INTO `core_sequence_counters` (`name`, `last_value`)
VALUES ('rec_engagement_code', 0)
ON DUPLICATE KEY UPDATE `name` = `name`;

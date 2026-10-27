-- ============================================================
-- Recruit Aid — Vacancies
-- The jobs being filled. Belong to an organisation and
-- (optionally) an engagement. Have their own pipeline stages.
-- ============================================================

CREATE TABLE IF NOT EXISTS `recruit_vacancies` (
    `id`                        INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code`                      VARCHAR(20) NOT NULL,
    `organisation_id`           INT UNSIGNED NOT NULL,
    `engagement_id`             INT UNSIGNED NULL,
    `title`                     VARCHAR(200) NOT NULL,
    `department`                VARCHAR(150) NULL,
    `country_code`              CHAR(2) NULL,
    `city`                      VARCHAR(100) NULL,
    `work_mode`                 ENUM('onsite','hybrid','remote') NOT NULL DEFAULT 'onsite',
    `employment_type`           ENUM('permanent','contract','fixed_term','internship','consultancy') NOT NULL DEFAULT 'permanent',
    `seniority`                 ENUM('junior','mid','senior','lead','manager','director','executive') NOT NULL DEFAULT 'mid',
    `positions_count`           INT UNSIGNED NOT NULL DEFAULT 1,
    `opening_date`              DATE NULL,
    `target_hire_date`          DATE NULL,
    `recruiter_id`              INT UNSIGNED NULL,
    `hiring_manager_contact_id` INT UNSIGNED NULL,
    `salary_min`                DECIMAL(14,2) NULL,
    `salary_max`                DECIMAL(14,2) NULL,
    `currency`                  CHAR(3) NULL,
    `description`               LONGTEXT NULL,
    `responsibilities`          LONGTEXT NULL,
    `requirements`              LONGTEXT NULL,
    `preferred_qualifications`  LONGTEXT NULL,
    `skills`                    JSON NULL,
    `languages`                 JSON NULL,
    `assessment_criteria`       TEXT NULL,
    `internal_notes`            TEXT NULL,
    `client_notes`              TEXT NULL,
    `status`                    ENUM(
                                    'draft','pending_approval','open','sourcing','screening',
                                    'shortlisting','client_review','interviewing','reference_check',
                                    'offer','filled','on_hold','cancelled','closed'
                                ) NOT NULL DEFAULT 'draft',
    `is_public`                 TINYINT(1) NOT NULL DEFAULT 0,
    `public_slug`               VARCHAR(220) NULL,
    `created_by`                INT UNSIGNED NULL,
    `created_at`                DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`                DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`                DATETIME NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_code` (`code`),
    UNIQUE KEY `uniq_public_slug` (`public_slug`),
    KEY `idx_org` (`organisation_id`),
    KEY `idx_engagement` (`engagement_id`),
    KEY `idx_status` (`status`),
    KEY `idx_recruiter` (`recruiter_id`),
    KEY `idx_country` (`country_code`),
    KEY `idx_created` (`created_at`),
    KEY `idx_deleted` (`deleted_at`),
    CONSTRAINT `fk_rec_vac_org` FOREIGN KEY (`organisation_id`)
        REFERENCES `recruit_client_organisations` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_rec_vac_engagement` FOREIGN KEY (`engagement_id`)
        REFERENCES `recruit_engagements` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_rec_vac_recruiter` FOREIGN KEY (`recruiter_id`)
        REFERENCES `core_users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_rec_vac_hm` FOREIGN KEY (`hiring_manager_contact_id`)
        REFERENCES `recruit_client_contacts` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_rec_vac_created_by` FOREIGN KEY (`created_by`)
        REFERENCES `core_users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `chk_rec_vac_code_format` CHECK (`code` REGEXP '^VAC-[0-9]{6}$'),
    CONSTRAINT `chk_rec_vac_country` CHECK (`country_code` IS NULL OR `country_code` REGEXP '^[A-Z]{2}$'),
    CONSTRAINT `chk_rec_vac_positions` CHECK (`positions_count` >= 1),
    CONSTRAINT `chk_rec_vac_salary` CHECK (
        `salary_min` IS NULL OR `salary_max` IS NULL OR `salary_max` >= `salary_min`
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `recruit_vacancy_stages` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `vacancy_id`  INT UNSIGNED NOT NULL,
    `key_slug`    VARCHAR(60) NOT NULL,
    `label`       VARCHAR(120) NOT NULL,
    `sort_order`  INT NOT NULL DEFAULT 0,
    `is_terminal` TINYINT(1) NOT NULL DEFAULT 0,
    `is_default`  TINYINT(1) NOT NULL DEFAULT 0,
    `color`       VARCHAR(20) NULL,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_vacancy_slug` (`vacancy_id`, `key_slug`),
    KEY `idx_vacancy_sort` (`vacancy_id`, `sort_order`),
    CONSTRAINT `fk_rec_vs_vacancy` FOREIGN KEY (`vacancy_id`)
        REFERENCES `recruit_vacancies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `recruit_vacancy_requirements` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `vacancy_id`   INT UNSIGNED NOT NULL,
    `type`         ENUM('responsibility','requirement','preferred','skill','language') NOT NULL,
    `value`        VARCHAR(500) NOT NULL,
    `sort_order`   INT NOT NULL DEFAULT 0,
    `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_vacancy_type` (`vacancy_id`, `type`, `sort_order`),
    CONSTRAINT `fk_rec_vr_vacancy` FOREIGN KEY (`vacancy_id`)
        REFERENCES `recruit_vacancies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `core_sequence_counters` (`name`, `last_value`)
VALUES ('rec_vacancy_code', 0)
ON DUPLICATE KEY UPDATE `name` = `name`;

-- =====================================================================
-- 0028_create_candidates.sql
-- Core candidate identity + normalized child tables (skills, experience,
-- education, tags) + GDPR tracking columns.
-- =====================================================================

CREATE TABLE IF NOT EXISTS `recruit_candidates` (
    `id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code`                VARCHAR(20) NOT NULL,
    `first_name`          VARCHAR(100) NOT NULL,
    `last_name`           VARCHAR(100) NOT NULL,
    `email`               VARCHAR(255) NOT NULL,
    `phone`               VARCHAR(50) NULL,
    `country_code`        CHAR(2) NULL,
    `city`                VARCHAR(100) NULL,
    `linkedin_url`        VARCHAR(255) NULL,
    `portfolio_url`       VARCHAR(255) NULL,
    `current_job_title`   VARCHAR(150) NULL,
    `current_company`     VARCHAR(150) NULL,
    `years_experience`    INT UNSIGNED NULL,
    `seniority`           ENUM('junior','mid','senior','lead','manager','director','executive') NULL,
    `skills`              JSON NULL,
    `languages`           JSON NULL,
    `summary`             TEXT NULL,
    `internal_notes`      TEXT NULL,
    `status`              ENUM('active','passive','hired','blacklisted','archived') NOT NULL DEFAULT 'active',
    `source`              VARCHAR(100) NULL,
    `recruiter_id`        INT UNSIGNED NULL,
    `created_by`          INT UNSIGNED NULL,
    `consent_given_at`    DATETIME NULL,
    `retention_until`     DATE NULL,
    `gdpr_erased_at`      DATETIME NULL,
    `created_at`          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`          DATETIME NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_rec_cand_code` (`code`),
    UNIQUE KEY `uniq_rec_cand_email` (`email`),
    UNIQUE KEY `uniq_rec_cand_linkedin` (`linkedin_url`),
    KEY `idx_rec_cand_status` (`status`),
    KEY `idx_rec_cand_recruiter` (`recruiter_id`),
    KEY `idx_rec_cand_created` (`created_at`),
    KEY `idx_rec_cand_retention` (`retention_until`),
    CONSTRAINT `fk_rec_cand_recruiter` FOREIGN KEY (`recruiter_id`) REFERENCES `core_users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_rec_cand_creator` FOREIGN KEY (`created_by`) REFERENCES `core_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `recruit_candidate_skills` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `candidate_id`    INT UNSIGNED NOT NULL,
    `skill_name`      VARCHAR(100) NOT NULL,
    `proficiency`     ENUM('beginner','intermediate','advanced','expert') NULL,
    `years_used`      INT UNSIGNED NULL,
    `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_rec_cand_skill_candidate` (`candidate_id`),
    KEY `idx_rec_cand_skill_name` (`skill_name`),
    CONSTRAINT `fk_rec_cand_skill_candidate` FOREIGN KEY (`candidate_id`) REFERENCES `recruit_candidates` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `recruit_candidate_experience` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `candidate_id`    INT UNSIGNED NOT NULL,
    `job_title`       VARCHAR(150) NOT NULL,
    `company`         VARCHAR(150) NOT NULL,
    `location`        VARCHAR(150) NULL,
    `start_date`      DATE NULL,
    `end_date`        DATE NULL,
    `is_current`      TINYINT(1) NOT NULL DEFAULT 0,
    `description`     TEXT NULL,
    `sort_order`      INT NOT NULL DEFAULT 0,
    `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_rec_cand_exp_candidate` (`candidate_id`),
    KEY `idx_rec_cand_exp_dates` (`candidate_id`, `start_date`, `end_date`),
    CONSTRAINT `fk_rec_cand_exp_candidate` FOREIGN KEY (`candidate_id`) REFERENCES `recruit_candidates` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `recruit_candidate_education` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `candidate_id`    INT UNSIGNED NOT NULL,
    `institution`     VARCHAR(150) NOT NULL,
    `degree`          VARCHAR(150) NULL,
    `field_of_study`  VARCHAR(150) NULL,
    `start_year`      YEAR NULL,
    `end_year`        YEAR NULL,
    `grade`           VARCHAR(50) NULL,
    `description`     TEXT NULL,
    `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_rec_cand_edu_candidate` (`candidate_id`),
    CONSTRAINT `fk_rec_cand_edu_candidate` FOREIGN KEY (`candidate_id`) REFERENCES `recruit_candidates` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `recruit_candidate_tags` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `candidate_id`    INT UNSIGNED NOT NULL,
    `tag`             VARCHAR(60) NOT NULL,
    `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_rec_cand_tag` (`candidate_id`, `tag`),
    KEY `idx_rec_cand_tag_name` (`tag`),
    CONSTRAINT `fk_rec_cand_tag_candidate` FOREIGN KEY (`candidate_id`) REFERENCES `recruit_candidates` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `core_sequence_counters` (`name`, `last_value`) VALUES ('rec_candidate_code', 0);

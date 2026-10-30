-- =====================================================================
-- 0038_create_persons.sql
-- Canonical Person identity. One row per human, shared across all
-- modules (Recruit Aid, HR Aid, Payroll Aid, etc.).
--
-- This is NOT the same as a Candidate or an Employee. A person can
-- have a candidate profile in Recruit Aid and an employment record
-- in HR Aid, linked to the same person_id.
-- =====================================================================

CREATE TABLE IF NOT EXISTS `core_persons` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code`           VARCHAR(20) NOT NULL,
    `first_name`     VARCHAR(100) NOT NULL,
    `last_name`      VARCHAR(100) NOT NULL,
    `preferred_name` VARCHAR(100) NULL,
    `gender`         ENUM('male','female','other','prefer_not_to_say') NULL,
    `date_of_birth`  DATE NULL,
    `nationality`    CHAR(2) NULL,
    `created_by`     INT UNSIGNED NULL,
    `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`     DATETIME NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_person_code` (`code`),
    KEY `idx_person_name` (`last_name`, `first_name`),
    KEY `idx_person_deleted` (`deleted_at`),
    CONSTRAINT `fk_person_creator` FOREIGN KEY (`created_by`)
        REFERENCES `core_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sequence counter for person codes
INSERT IGNORE INTO `core_sequence_counters` (`name`, `last_value`)
VALUES ('core_person_code', 0);

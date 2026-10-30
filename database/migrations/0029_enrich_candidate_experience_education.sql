-- =====================================================================
-- 0029_enrich_candidate_experience_education.sql
-- Enriches experience and education child tables.
-- Seeds the education_level lookup group.
--
-- NOTE: This migration creates `core_lookups` if it doesn't already
-- exist, because it references it via a foreign key. The dedicated
-- 0031_create_lookups.sql migration also creates it with
-- CREATE TABLE IF NOT EXISTS, so running in either order is safe.
-- This resolves a forward-dependency that only surfaces on a fresh
-- install (doctrine #16: migrations run in order on an empty DB).
-- =====================================================================

-- Ensure core_lookups exists before referencing it (idempotent with 0031)
CREATE TABLE IF NOT EXISTS `core_lookups` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `group_slug`  VARCHAR(60)  NOT NULL,
    `slug`        VARCHAR(60)  NOT NULL,
    `label`       VARCHAR(150) NOT NULL,
    `description` VARCHAR(500) NULL,
    `sort_order`  INT          NOT NULL DEFAULT 0,
    `is_active`   TINYINT(1)   NOT NULL DEFAULT 1,
    `is_system`   TINYINT(1)   NOT NULL DEFAULT 0,
    `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`  DATETIME     NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_group_slug` (`group_slug`, `slug`),
    KEY `idx_group_order` (`group_slug`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Experience: add company_type
ALTER TABLE `recruit_candidate_experience`
    ADD COLUMN `company_type` VARCHAR(100) NULL AFTER `company`;

-- Education: add school_location, minor, education_level_id
ALTER TABLE `recruit_candidate_education`
    ADD COLUMN `school_location` VARCHAR(150) NULL AFTER `institution`,
    ADD COLUMN `minor` VARCHAR(150) NULL AFTER `field_of_study`,
    ADD COLUMN `education_level_id` INT UNSIGNED NULL AFTER `degree`,
    ADD CONSTRAINT `fk_rec_cand_edu_level` FOREIGN KEY (`education_level_id`) REFERENCES `core_lookups` (`id`) ON DELETE SET NULL;

-- Seed education_level lookup group
INSERT IGNORE INTO `core_lookups` (`group_slug`, `slug`, `label`, `sort_order`, `is_active`, `is_system`) VALUES
('education_level', 'certificate', 'Certificate', 10, 1, 0),
('education_level', 'diploma', 'Diploma', 20, 1, 0),
('education_level', 'bachelors', 'Bachelor\'s Degree', 30, 1, 0),
('education_level', 'masters', 'Master\'s Degree', 40, 1, 0),
('education_level', 'doctorate', 'Doctorate (PhD)', 50, 1, 0),
('education_level', 'postdoctoral', 'Postdoctoral', 60, 1, 0),
('education_level', 'professional_cert', 'Professional Certification', 70, 1, 0),
('education_level', 'other', 'Other', 80, 1, 0);

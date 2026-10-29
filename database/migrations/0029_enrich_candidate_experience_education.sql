-- =====================================================================
-- 0029_enrich_candidate_experience_education.sql
-- Enriches experience and education child tables.
-- Seeds the education_level lookup group.
-- =====================================================================

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

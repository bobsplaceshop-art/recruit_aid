-- =====================================================================
-- 0030_create_applications.sql
-- Applications layer: links a Candidate (global identity) to a Vacancy
-- (specific hiring opportunity) with stage tracking.
-- Also adds pipeline_mode support:
--   - Global default: core_system_settings['recruit.pipeline_mode']
--   - Per-engagement override: recruit_engagements.pipeline_mode (NULL = inherit)
-- =====================================================================

CREATE TABLE IF NOT EXISTS `recruit_applications` (
    `id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code`                VARCHAR(20) NOT NULL,
    `candidate_id`        INT UNSIGNED NOT NULL,
    `vacancy_id`          INT UNSIGNED NOT NULL,
    `current_stage_id`    INT UNSIGNED NULL,
    `status`              ENUM('active','withdrawn','rejected','hired','archived') NOT NULL DEFAULT 'active',
    `source`              VARCHAR(100) NULL,
    `notes`               TEXT NULL,
    `applied_at`          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_by`          INT UNSIGNED NULL,
    `created_at`          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`          DATETIME NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_rec_app_code` (`code`),
    UNIQUE KEY `uniq_rec_app_cand_vac` (`candidate_id`, `vacancy_id`),
    KEY `idx_rec_app_vacancy` (`vacancy_id`),
    KEY `idx_rec_app_candidate` (`candidate_id`),
    KEY `idx_rec_app_stage` (`current_stage_id`),
    KEY `idx_rec_app_status` (`status`),
    CONSTRAINT `fk_rec_app_candidate` FOREIGN KEY (`candidate_id`) REFERENCES `recruit_candidates` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_rec_app_vacancy`   FOREIGN KEY (`vacancy_id`)   REFERENCES `recruit_vacancies` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_rec_app_stage`     FOREIGN KEY (`current_stage_id`) REFERENCES `recruit_vacancy_stages` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_rec_app_creator`   FOREIGN KEY (`created_by`)   REFERENCES `core_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `recruit_application_stage_history` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `application_id`  INT UNSIGNED NOT NULL,
    `from_stage_id`   INT UNSIGNED NULL,
    `to_stage_id`     INT UNSIGNED NULL,
    `moved_by`        INT UNSIGNED NULL,
    `moved_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `notes`           TEXT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_rec_app_sh_application` (`application_id`),
    KEY `idx_rec_app_sh_moved_at` (`application_id`, `moved_at`),
    CONSTRAINT `fk_rec_app_sh_application` FOREIGN KEY (`application_id`) REFERENCES `recruit_applications` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_rec_app_sh_from` FOREIGN KEY (`from_stage_id`) REFERENCES `recruit_vacancy_stages` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_rec_app_sh_to`   FOREIGN KEY (`to_stage_id`)   REFERENCES `recruit_vacancy_stages` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_rec_app_sh_mover` FOREIGN KEY (`moved_by`) REFERENCES `core_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Per-engagement pipeline mode override.
-- NULL = inherit the global default from core_system_settings.
ALTER TABLE `recruit_engagements`
    ADD COLUMN `pipeline_mode` ENUM('open','straight') NULL DEFAULT NULL AFTER `notes`;

-- Global default: pipeline mode
INSERT IGNORE INTO `core_system_settings` (`key_slug`, `value`, `type`) VALUES
('recruit.pipeline_mode', 'open', 'string');

-- Sequence counter
INSERT IGNORE INTO `core_sequence_counters` (`name`, `last_value`) VALUES ('rec_application_code', 0);

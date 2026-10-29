-- =====================================================================
-- 0031_create_interviews.sql
-- Interviews module foundation.
-- An interview is scheduled against an application (candidate × vacancy).
-- Multiple interviewers can be assigned. Each interviewer submits their
-- own scorecard (independent, before seeing others' scores).
-- =====================================================================

CREATE TABLE IF NOT EXISTS `recruit_interviews` (
    `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code`              VARCHAR(20) NOT NULL,
    `application_id`    INT UNSIGNED NOT NULL,
    `scheduled_at`      DATETIME NOT NULL,
    `duration_minutes`  INT UNSIGNED NOT NULL DEFAULT 60,
    `type`              ENUM('phone','video','onsite','technical','panel','final') NOT NULL DEFAULT 'video',
    `location`          VARCHAR(255) NULL,
    `status`            ENUM('scheduled','completed','cancelled','no_show') NOT NULL DEFAULT 'scheduled',
    `notes`             TEXT NULL,
    `created_by`        INT UNSIGNED NULL,
    `created_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`        DATETIME NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_rec_int_code` (`code`),
    KEY `idx_rec_int_application` (`application_id`),
    KEY `idx_rec_int_scheduled` (`scheduled_at`),
    KEY `idx_rec_int_status` (`status`),
    CONSTRAINT `fk_rec_int_application` FOREIGN KEY (`application_id`) REFERENCES `recruit_applications` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_rec_int_creator` FOREIGN KEY (`created_by`) REFERENCES `core_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `recruit_interview_participants` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `interview_id`    INT UNSIGNED NOT NULL,
    `user_id`         INT UNSIGNED NOT NULL,
    `role`            ENUM('lead','interviewer','observer') NOT NULL DEFAULT 'interviewer',
    `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_rec_intp_int_user` (`interview_id`, `user_id`),
    KEY `idx_rec_intp_user` (`user_id`),
    CONSTRAINT `fk_rec_intp_interview` FOREIGN KEY (`interview_id`) REFERENCES `recruit_interviews` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_rec_intp_user` FOREIGN KEY (`user_id`) REFERENCES `core_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `recruit_scorecards` (
    `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `interview_id`      INT UNSIGNED NOT NULL,
    `interviewer_id`    INT UNSIGNED NOT NULL,
    `recommendation`    ENUM('strong_yes','yes','neutral','no','strong_no') NULL,
    `notes`             TEXT NULL,
    `submitted_at`      DATETIME NULL,
    `created_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_rec_sc_interview_interviewer` (`interview_id`, `interviewer_id`),
    KEY `idx_rec_sc_interview` (`interview_id`),
    KEY `idx_rec_sc_interviewer` (`interviewer_id`),
    CONSTRAINT `fk_rec_sc_interview` FOREIGN KEY (`interview_id`) REFERENCES `recruit_interviews` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_rec_sc_interviewer` FOREIGN KEY (`interviewer_id`) REFERENCES `core_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `recruit_scorecard_attributes` (
    `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `scorecard_id`      INT UNSIGNED NOT NULL,
    `competency_name`   VARCHAR(120) NOT NULL,
    `score`             TINYINT UNSIGNED NOT NULL,
    `evidence`          TEXT NULL,
    `sort_order`        INT NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `idx_rec_sca_scorecard` (`scorecard_id`),
    CONSTRAINT `fk_rec_sca_scorecard` FOREIGN KEY (`scorecard_id`) REFERENCES `recruit_scorecards` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Interview code sequence
INSERT IGNORE INTO `core_sequence_counters` (`name`, `last_value`) VALUES ('rec_interview_code', 0);

-- Permissions for the Interviews module
INSERT IGNORE INTO `core_permissions` (`slug`, `module`, `description`) VALUES
('recruit.interviews.view',    'recruit.interviews', 'View interviews'),
('recruit.interviews.create',  'recruit.interviews', 'Schedule interviews'),
('recruit.interviews.edit',    'recruit.interviews', 'Edit interviews'),
('recruit.interviews.delete',  'recruit.interviews', 'Cancel interviews'),
('recruit.scorecards.submit',  'recruit.interviews', 'Submit scorecards'),
('recruit.scorecards.view',    'recruit.interviews', 'View scorecards (after submission)');

-- Grant all interview permissions to super_admin (id=1)
INSERT IGNORE INTO `core_role_permissions` (`role_id`, `permission_id`)
SELECT 1, id FROM `core_permissions` WHERE `module` = 'recruit.interviews';

-- Grant view + create to admin (id=2)
INSERT IGNORE INTO `core_role_permissions` (`role_id`, `permission_id`)
SELECT 2, id FROM `core_permissions`
WHERE `slug` IN ('recruit.interviews.view', 'recruit.interviews.create', 'recruit.interviews.edit',
                 'recruit.scorecards.submit', 'recruit.scorecards.view');

-- Grant view + submit to recruiter (id=4)
INSERT IGNORE INTO `core_role_permissions` (`role_id`, `permission_id`)
SELECT 4, id FROM `core_permissions`
WHERE `slug` IN ('recruit.interviews.view', 'recruit.scorecards.submit');

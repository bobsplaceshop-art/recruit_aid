-- =====================================================================
-- 0032_create_offers.sql
-- Offers module foundation.
-- An offer is extended to a candidate for a specific application
-- (candidate × vacancy). Status lifecycle: draft → sent → accepted /
-- declined / expired / withdrawn.
-- =====================================================================

CREATE TABLE IF NOT EXISTS `recruit_offers` (
    `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code`             VARCHAR(20) NOT NULL,
    `application_id`   INT UNSIGNED NOT NULL,
    `offered_salary`   DECIMAL(14,2) NOT NULL,
    `currency`         CHAR(3) NOT NULL,
    `start_date`       DATE NOT NULL,
    `expires_at`       DATE NOT NULL,
    `status`           ENUM('draft','sent','accepted','declined','expired','withdrawn') NOT NULL DEFAULT 'draft',
    `notes`            TEXT NULL,
    `response_notes`   TEXT NULL,
    `sent_at`          DATETIME NULL,
    `responded_at`     DATETIME NULL,
    `created_by`       INT UNSIGNED NULL,
    `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`       DATETIME NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_rec_off_code` (`code`),
    KEY `idx_rec_off_application` (`application_id`),
    KEY `idx_rec_off_status` (`status`),
    KEY `idx_rec_off_expires` (`expires_at`),
    CONSTRAINT `fk_rec_off_application` FOREIGN KEY (`application_id`) REFERENCES `recruit_applications` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_rec_off_creator` FOREIGN KEY (`created_by`) REFERENCES `core_users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `chk_rec_off_salary_positive` CHECK (`offered_salary` > 0),
    CONSTRAINT `chk_rec_off_dates` CHECK (`expires_at` >= `start_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `recruit_offer_history` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `offer_id`        INT UNSIGNED NOT NULL,
    `from_status`     VARCHAR(20) NULL,
    `to_status`       VARCHAR(20) NOT NULL,
    `changed_by`      INT UNSIGNED NULL,
    `changed_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `notes`           TEXT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_rec_offh_offer` (`offer_id`),
    KEY `idx_rec_offh_changed` (`offer_id`, `changed_at`),
    CONSTRAINT `fk_rec_offh_offer` FOREIGN KEY (`offer_id`) REFERENCES `recruit_offers` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_rec_offh_changer` FOREIGN KEY (`changed_by`) REFERENCES `core_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Permissions
INSERT IGNORE INTO `core_permissions` (`slug`, `module`, `description`) VALUES
('recruit.offers.view',   'recruit.offers', 'View offers'),
('recruit.offers.create', 'recruit.offers', 'Create offers'),
('recruit.offers.edit',   'recruit.offers', 'Edit draft offers'),
('recruit.offers.send',   'recruit.offers', 'Send offers to candidates'),
('recruit.offers.respond','recruit.offers', 'Record candidate responses (accept/decline)');

-- Grant all offers permissions to super_admin
INSERT IGNORE INTO `core_role_permissions` (`role_id`, `permission_id`)
SELECT 1, id FROM `core_permissions` WHERE `module` = 'recruit.offers';

-- Grant view + create + edit + send + respond to admin
INSERT IGNORE INTO `core_role_permissions` (`role_id`, `permission_id`)
SELECT 2, id FROM `core_permissions` WHERE `module` = 'recruit.offers';

-- Grant view + create + send to recruiter (id=4)
INSERT IGNORE INTO `core_role_permissions` (`role_id`, `permission_id`)
SELECT 4, id FROM `core_permissions`
WHERE `slug` IN ('recruit.offers.view', 'recruit.offers.create', 'recruit.offers.send');

-- Sequence counter
INSERT IGNORE INTO `core_sequence_counters` (`name`, `last_value`) VALUES ('rec_offer_code', 0);

-- ============================================================
-- Recruit Aid — Organisation Contacts
-- The people at each client organisation.
-- ============================================================

CREATE TABLE IF NOT EXISTS `recruit_client_contacts` (
    `id`                        INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `organisation_id`           INT UNSIGNED NOT NULL,
    `code`                      VARCHAR(20)  NOT NULL,
    `first_name`                VARCHAR(100) NOT NULL,
    `last_name`                 VARCHAR(100) NOT NULL,
    `position`                  VARCHAR(150) NULL,
    `department`                VARCHAR(150) NULL,
    `email`                     VARCHAR(190) NULL,
    `phone`                     VARCHAR(50)  NULL,
    `alt_phone`                 VARCHAR(50)  NULL,
    `country_code`              CHAR(2)      NULL,
    `preferred_communication`   ENUM('email','phone','whatsapp','sms','any') NOT NULL DEFAULT 'email',
    `is_primary`                TINYINT(1) NOT NULL DEFAULT 0,
    `is_hiring_manager`         TINYINT(1) NOT NULL DEFAULT 0,
    `is_finance`                TINYINT(1) NOT NULL DEFAULT 0,
    `is_hr`                     TINYINT(1) NOT NULL DEFAULT 0,
    `is_decision_maker`         TINYINT(1) NOT NULL DEFAULT 0,
    `notes`                     TEXT NULL,
    `created_by`                INT UNSIGNED NULL,
    `created_at`                DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`                DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`                DATETIME NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_code` (`code`),
    KEY `idx_org` (`organisation_id`),
    KEY `idx_email` (`email`),
    KEY `idx_name` (`last_name`, `first_name`),
    KEY `idx_deleted` (`deleted_at`),
    CONSTRAINT `fk_rec_contact_org` FOREIGN KEY (`organisation_id`)
        REFERENCES `recruit_client_organisations` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_rec_contact_created_by` FOREIGN KEY (`created_by`)
        REFERENCES `core_users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `chk_rec_contact_code_format`
        CHECK (`code` REGEXP '^CNT-[0-9]{6}$'),
    CONSTRAINT `chk_rec_contact_country`
        CHECK (`country_code` IS NULL OR `country_code` REGEXP '^[A-Z]{2}$')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ensure sequence counter for contact codes
INSERT INTO `core_sequence_counters` (`name`, `last_value`)
VALUES ('rec_client_contact_code', 0)
ON DUPLICATE KEY UPDATE `name` = `name`;

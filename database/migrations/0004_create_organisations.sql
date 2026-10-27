-- ============================================================
-- Recruit Aid — Organisations
-- The clients of the recruitment agency. Every engagement,
-- vacancy, and application ultimately belongs to an organisation.
-- ============================================================

CREATE TABLE IF NOT EXISTS `recruit_client_organisations` (
    `id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code`                VARCHAR(20)  NOT NULL,
    `name`                VARCHAR(255) NOT NULL,
    `legal_name`          VARCHAR(255) NULL,
    `industry`            VARCHAR(100) NULL,
    `website`             VARCHAR(255) NULL,
    `country_code`        CHAR(2)      NULL,
    `countries_operation` JSON         NULL,
    `address`             TEXT         NULL,
    `phone`               VARCHAR(50)  NULL,
    `email`               VARCHAR(190) NULL,
    `status`              ENUM('prospect','active','inactive','former_client') NOT NULL DEFAULT 'prospect',
    `notes`               TEXT         NULL,
    `created_by`          INT UNSIGNED NULL,
    `created_at`          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`          DATETIME     NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_code` (`code`),
    KEY `idx_name` (`name`),
    KEY `idx_status` (`status`),
    KEY `idx_country` (`country_code`),
    KEY `idx_deleted` (`deleted_at`),
    CONSTRAINT `fk_rec_org_created_by` FOREIGN KEY (`created_by`) REFERENCES `core_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

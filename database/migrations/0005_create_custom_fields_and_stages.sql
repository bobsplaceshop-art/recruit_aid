-- ============================================================
-- Recruit Aid — Custom Fields and Pipeline Stages
-- ============================================================

CREATE TABLE IF NOT EXISTS `core_custom_fields` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `entity_type`   VARCHAR(60) NOT NULL,
    `field_key`     VARCHAR(80) NOT NULL,
    `label`         VARCHAR(190) NOT NULL,
    `input_type`    VARCHAR(40) NOT NULL,
    `is_required`   TINYINT(1) NOT NULL DEFAULT 0,
    `options`       JSON NULL,
    `constraints`   JSON NULL,
    `default_value` VARCHAR(500) NULL,
    `help_text`     VARCHAR(500) NULL,
    `sort_order`    INT NOT NULL DEFAULT 0,
    `is_active`     TINYINT(1) NOT NULL DEFAULT 1,
    `is_system`     TINYINT(1) NOT NULL DEFAULT 0,
    `created_by`    INT UNSIGNED NULL,
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`    DATETIME NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_entity_key` (`entity_type`, `field_key`),
    KEY `idx_entity` (`entity_type`),
    KEY `idx_active` (`entity_type`, `is_active`),
    CONSTRAINT `fk_core_cf_created_by` FOREIGN KEY (`created_by`) REFERENCES `core_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `core_custom_field_values` (
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `custom_field_id` INT UNSIGNED NOT NULL,
    `entity_type`     VARCHAR(60) NOT NULL,
    `entity_id`       INT UNSIGNED NOT NULL,
    `value`           LONGTEXT NULL,
    `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_field_entity` (`custom_field_id`, `entity_type`, `entity_id`),
    KEY `idx_entity` (`entity_type`, `entity_id`),
    CONSTRAINT `fk_core_cfv_field` FOREIGN KEY (`custom_field_id`) REFERENCES `core_custom_fields` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `recruit_pipeline_stages` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `organisation_id` INT UNSIGNED NULL,
    `key_slug`        VARCHAR(60) NOT NULL,
    `label`           VARCHAR(120) NOT NULL,
    `sort_order`      INT NOT NULL DEFAULT 0,
    `is_terminal`     TINYINT(1) NOT NULL DEFAULT 0,
    `is_default`      TINYINT(1) NOT NULL DEFAULT 0,
    `color`           VARCHAR(20) NULL,
    `description`     VARCHAR(500) NULL,
    `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`      DATETIME NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_org_slug` (`organisation_id`, `key_slug`),
    KEY `idx_org` (`organisation_id`),
    KEY `idx_sort` (`organisation_id`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed default (organisation_id = NULL) pipeline stages
INSERT INTO `recruit_pipeline_stages` (`organisation_id`, `key_slug`, `label`, `sort_order`, `is_terminal`, `is_default`, `color`) VALUES
    (NULL, 'sourced',          'Sourced',         10,  0, 1, '#94a3b8'),
    (NULL, 'contacted',        'Contacted',       20,  0, 1, '#93c5fd'),
    (NULL, 'applied',          'Applied',         30,  0, 1, '#60a5fa'),
    (NULL, 'screening',        'Screening',       40,  0, 1, '#3b82f6'),
    (NULL, 'assessment',       'Assessment',      50,  0, 1, '#8b5cf6'),
    (NULL, 'shortlisted',      'Shortlisted',     60,  0, 1, '#a855f7'),
    (NULL, 'client_review',    'Client Review',   70,  0, 1, '#d946ef'),
    (NULL, 'interview',        'Interview',       80,  0, 1, '#ec4899'),
    (NULL, 'reference_check',  'Reference Check', 90,  0, 1, '#f43f5e'),
    (NULL, 'offer',            'Offer',           100, 0, 1, '#f97316'),
    (NULL, 'hired',            'Hired',           110, 1, 1, '#16a34a'),
    (NULL, 'rejected',         'Rejected',        120, 1, 1, '#dc2626'),
    (NULL, 'withdrawn',        'Withdrawn',       130, 1, 1, '#737373'),
    (NULL, 'talent_pool',      'Talent Pool',     140, 1, 1, '#0ea5e9')
ON DUPLICATE KEY UPDATE `label` = VALUES(`label`), `sort_order` = VALUES(`sort_order`);

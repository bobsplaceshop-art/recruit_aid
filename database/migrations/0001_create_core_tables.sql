-- ============================================================
-- Recruit Aid - Core identity & access tables
-- ============================================================

CREATE TABLE IF NOT EXISTS `core_users` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(150) NOT NULL,
    `email` VARCHAR(190) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `phone` VARCHAR(40) NULL,
    `avatar_path` VARCHAR(255) NULL,
    `timezone` VARCHAR(60) NOT NULL DEFAULT 'Africa/Nairobi',
    `locale` VARCHAR(10) NOT NULL DEFAULT 'en',
    `status` ENUM('pending','active','suspended','disabled') NOT NULL DEFAULT 'pending',
    `is_client_user` TINYINT(1) NOT NULL DEFAULT 0,
    `organisation_id` INT UNSIGNED NULL,
    `failed_logins` INT UNSIGNED NOT NULL DEFAULT 0,
    `locked_until` DATETIME NULL,
    `last_login_at` DATETIME NULL,
    `remember_token_hash` VARCHAR(255) NULL,
    `two_factor_secret_enc` VARBINARY(255) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_email` (`email`),
    KEY `idx_status` (`status`),
    KEY `idx_client` (`is_client_user`, `organisation_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `core_roles` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(80) NOT NULL,
    `slug` VARCHAR(80) NOT NULL,
    `description` VARCHAR(255) NULL,
    `is_system` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `core_permissions` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `slug` VARCHAR(120) NOT NULL,
    `module` VARCHAR(60) NOT NULL,
    `description` VARCHAR(255) NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_slug` (`slug`),
    KEY `idx_module` (`module`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `core_role_permissions` (
    `role_id` INT UNSIGNED NOT NULL,
    `permission_id` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`role_id`, `permission_id`),
    CONSTRAINT `fk_core_rp_role` FOREIGN KEY (`role_id`) REFERENCES `core_roles` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_core_rp_perm` FOREIGN KEY (`permission_id`) REFERENCES `core_permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `core_user_roles` (
    `user_id` INT UNSIGNED NOT NULL,
    `role_id` INT UNSIGNED NOT NULL,
    `assigned_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`user_id`, `role_id`),
    CONSTRAINT `fk_core_ur_user` FOREIGN KEY (`user_id`) REFERENCES `core_users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_core_ur_role` FOREIGN KEY (`role_id`) REFERENCES `core_roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `core_password_resets` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `email` VARCHAR(190) NOT NULL,
    `token_hash` VARCHAR(255) NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_email` (`email`),
    KEY `idx_token` (`token_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `core_audit_logs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NULL,
    `action` VARCHAR(80) NOT NULL,
    `module` VARCHAR(60) NOT NULL,
    `record_type` VARCHAR(80) NULL,
    `record_id` BIGINT UNSIGNED NULL,
    `description` TEXT NULL,
    `ip_address` VARBINARY(16) NULL,
    `user_agent` VARCHAR(255) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user` (`user_id`),
    KEY `idx_module` (`module`),
    KEY `idx_record` (`record_type`, `record_id`),
    KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `core_system_settings` (
    `key_slug` VARCHAR(120) NOT NULL,
    `value` LONGTEXT NULL,
    `type` ENUM('string','int','bool','json','secret') NOT NULL DEFAULT 'string',
    `is_encrypted` TINYINT(1) NOT NULL DEFAULT 0,
    `updated_by` INT UNSIGNED NULL,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`key_slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

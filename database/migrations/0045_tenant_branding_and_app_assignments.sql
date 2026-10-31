-- =====================================================================
-- 0045_tenant_branding_and_app_assignments.sql
-- =====================================================================

ALTER TABLE `core_tenants`
    ADD COLUMN `logo_path` VARCHAR(255) NULL,
    ADD COLUMN `primary_color` VARCHAR(7) NULL DEFAULT '#0d6efd',
    ADD COLUMN `secondary_color` VARCHAR(7) NULL DEFAULT '#6c757d',
    ADD COLUMN `login_message` TEXT NULL;

CREATE TABLE IF NOT EXISTS `core_user_applications` (
    `user_id` INT UNSIGNED NOT NULL,
    `module_key` VARCHAR(40) NOT NULL,
    `is_default` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`user_id`, `module_key`),
    CONSTRAINT `fk_ua_user` FOREIGN KEY (`user_id`) REFERENCES `core_users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ua_module` FOREIGN KEY (`module_key`) REFERENCES `core_modules` (`module_key`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

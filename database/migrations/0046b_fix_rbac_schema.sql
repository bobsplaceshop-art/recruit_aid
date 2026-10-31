-- =====================================================================
-- 0046b_fix_rbac_schema.sql
-- Corrective migration. 0046 partially applied (is_system already
-- existed, so the ALTER chain aborted). This migration adds only
-- the missing pieces. Safe to re-run only if migration history is
-- managed correctly (it is - migrator skips already-applied files).
-- =====================================================================

-- 1. Add template_slug to core_roles (is_system already exists)
ALTER TABLE `core_roles`
    ADD COLUMN `template_slug` VARCHAR(80) NULL AFTER `is_system`;

-- 2. Extend core_user_roles
ALTER TABLE `core_user_roles`
    ADD COLUMN `assigned_by` INT UNSIGNED NULL AFTER `role_id`,
    ADD COLUMN `expires_at` DATETIME NULL AFTER `assigned_at`,
    ADD CONSTRAINT `fk_ur_assigned_by` FOREIGN KEY (`assigned_by`) REFERENCES `core_users` (`id`) ON DELETE SET NULL;

-- 3. Create core_permission_registrations
CREATE TABLE IF NOT EXISTS `core_permission_registrations` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `module_key` VARCHAR(40) NOT NULL,
    `slug` VARCHAR(120) NOT NULL,
    `name` VARCHAR(150) NOT NULL,
    `description` TEXT NULL,
    `sensitivity` ENUM('public', 'sensitive', 'restricted') NOT NULL DEFAULT 'public',
    `registered_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_reg_module_slug` (`module_key`, `slug`),
    CONSTRAINT `fk_reg_module` FOREIGN KEY (`module_key`) REFERENCES `core_modules` (`module_key`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Create core_admin_delegations
CREATE TABLE IF NOT EXISTS `core_admin_delegations` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `tenant_id` INT UNSIGNED NOT NULL,
    `module_key` VARCHAR(40) NOT NULL,
    `granted_by` INT UNSIGNED NOT NULL,
    `granted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `expires_at` DATETIME NULL,
    `revoked_at` DATETIME NULL,
    `allowed_permissions_json` JSON NOT NULL,
    `denied_permissions_json` JSON NULL,
    PRIMARY KEY (`id`),
    KEY `idx_del_user_module` (`user_id`, `module_key`),
    CONSTRAINT `fk_del_user` FOREIGN KEY (`user_id`) REFERENCES `core_users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_del_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `core_tenants` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_del_module` FOREIGN KEY (`module_key`) REFERENCES `core_modules` (`module_key`) ON DELETE CASCADE,
    CONSTRAINT `fk_del_granted_by` FOREIGN KEY (`granted_by`) REFERENCES `core_users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Create core_permission_policies
CREATE TABLE IF NOT EXISTS `core_permission_policies` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id` INT UNSIGNED NOT NULL,
    `permission_slug` VARCHAR(120) NOT NULL,
    `effect` ENUM('allow', 'deny') NOT NULL DEFAULT 'deny',
    `reason` VARCHAR(255) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_pol_tenant_perm` (`tenant_id`, `permission_slug`),
    CONSTRAINT `fk_pol_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `core_tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Create core_rbac_audit_logs
CREATE TABLE IF NOT EXISTS `core_rbac_audit_logs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id` INT UNSIGNED NOT NULL,
    `actor_id` INT UNSIGNED NULL,
    `action` VARCHAR(80) NOT NULL,
    `target_type` VARCHAR(40) NULL,
    `target_id` INT UNSIGNED NULL,
    `module_key` VARCHAR(40) NULL,
    `before_json` JSON NULL,
    `after_json` JSON NULL,
    `ip_address` VARCHAR(45) NULL,
    `user_agent` VARCHAR(255) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_audit_tenant_action` (`tenant_id`, `action`),
    KEY `idx_audit_actor` (`actor_id`),
    KEY `idx_audit_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
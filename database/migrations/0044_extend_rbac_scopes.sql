-- =====================================================================
-- 0044_extend_rbac_scopes.sql
-- Distinguish between Global (CAS) permissions and App (Module) permissions.
-- =====================================================================

ALTER TABLE `core_permissions`
    ADD COLUMN `scope` ENUM('global', 'app') NOT NULL DEFAULT 'app' AFTER `description`,
    ADD COLUMN `module_key` VARCHAR(40) NULL AFTER `scope`;

ALTER TABLE `core_roles`
    ADD COLUMN `scope` ENUM('global', 'app') NOT NULL DEFAULT 'app' AFTER `description`,
    ADD COLUMN `module_key` VARCHAR(40) NULL AFTER `scope`;

-- Set existing roles/permissions based on their module
UPDATE `core_permissions` SET `scope` = 'app', `module_key` = `module` WHERE `module` != 'core';
UPDATE `core_permissions` SET `scope` = 'global', `module_key` = NULL WHERE `module` = 'core';

UPDATE `core_roles` SET `scope` = 'app', `module_key` = 'recruit' WHERE `slug` != 'super_admin';
UPDATE `core_roles` SET `scope` = 'global', `module_key` = NULL WHERE `slug` = 'super_admin';

-- Create the Global Dashboard permission
INSERT IGNORE INTO `core_permissions` (`slug`, `module`, `description`, `scope`) 
VALUES ('cas.dashboard.view', 'core', 'Allows access to the central CAS Suit dashboard', 'global');

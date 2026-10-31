-- =====================================================================
-- 0049_extend_user_permissions.sql
-- Add `denied` and `expires_at` to core_user_permissions.
--   denied = 0 (default): explicit grant
--   denied = 1: explicit denial (overrides role grants)
--   expires_at NULL: permanent; otherwise grant/denial stops at this time
-- =====================================================================

ALTER TABLE `core_user_permissions`
    ADD COLUMN `denied` TINYINT(1) NOT NULL DEFAULT 0 AFTER `granted_by`,
    ADD COLUMN `expires_at` DATETIME NULL AFTER `granted_at`;
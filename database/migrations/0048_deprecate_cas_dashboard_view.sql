-- =====================================================================
-- 0048_deprecate_cas_dashboard_view.sql
-- The cas.dashboard.view slug was seeded by 0044 and superseded
-- by cas.dashboard.access in 0047. Remove the orphan.
-- =====================================================================

-- Migrate any role assignments from the old slug to the new one first.
INSERT IGNORE INTO `core_role_permissions` (`role_id`, `permission_id`)
SELECT rp.`role_id`, p_new.`id`
FROM `core_role_permissions` rp
JOIN `core_permissions` p_old ON p_old.`id` = rp.`permission_id`
JOIN `core_permissions` p_new ON p_new.`slug` = 'cas.dashboard.access'
WHERE p_old.`slug` = 'cas.dashboard.view';

-- Remove old assignments and the permission itself.
DELETE FROM `core_role_permissions`
WHERE `permission_id` IN (SELECT `id` FROM `core_permissions` WHERE `slug` = 'cas.dashboard.view');

DELETE FROM `core_permissions` WHERE `slug` = 'cas.dashboard.view';
-- 0072_seed_calendar_view_permissions.sql
--
-- Two permissions:
--   recruit.calendar.view  - Recruit tenant calendar (/recruit/calendar)
--   cas.calendar.view      - Platform calendar (/cas/calendar)
--
-- Recruit permission: granted to admin, recruitment_manager, recruiter,
-- researcher in tenant 1.
--
-- CAS permission: granted to cas_admin and cas_developer platform roles.
-- Raw cross-tenant events are NOT a permission - they require an explicit
-- context switch. Delegated tenants show only aggregated counts.

INSERT INTO `core_permissions`
    (`slug`, `module`, `description`, `scope`, `module_key`, `sensitivity`, `is_active`)
VALUES
    ('recruit.calendar.view', 'recruit.calendar', 'View Recruit Aid calendar', 'app',    'recruit', 'public', 1),
    ('cas.calendar.view',     'cas.calendar',     'View platform calendar',    'global', 'cas',     'public', 1)
ON DUPLICATE KEY UPDATE `is_active` = 1;

INSERT IGNORE INTO `core_role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `core_roles` r
JOIN `core_permissions` p
    ON p.`slug` = 'recruit.calendar.view'
   AND p.`is_active` = 1
WHERE r.`tenant_id` = 1
  AND r.`module_key` = 'recruit'
  AND r.`slug` IN ('admin', 'recruitment_manager', 'recruiter', 'researcher');

INSERT IGNORE INTO `core_platform_role_permissions` (`role_id`, `permission_id`)
SELECT pr.`id`, p.`id`
FROM `core_platform_roles` pr
JOIN `core_permissions` p
    ON p.`slug` = 'cas.calendar.view'
   AND p.`is_active` = 1
WHERE pr.`slug` IN ('cas_admin', 'cas_developer');

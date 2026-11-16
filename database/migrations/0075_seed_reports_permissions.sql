-- 0075_seed_reports_permissions.sql
--
-- The ReportController routes require 'recruit.reports.view' on every
-- action, but the permission was never seeded into core_permissions.
-- PermissionMiddleware's AuthorizationService lookup returns null
-- (Rule 2: permission must exist and be active) → DENY → 403 or
-- /no-access. This migration seeds the permission and grants it.

INSERT IGNORE INTO `core_permissions` (`slug`, `module`, `description`, `scope`, `module_key`, `sensitivity`, `is_active`)
VALUES
    ('recruit.reports.view',   'recruit.reports', 'View reports',   'app', 'recruit', 'public', 1),
    ('recruit.reports.export', 'recruit.reports', 'Export reports', 'app', 'recruit', 'public', 1);

INSERT IGNORE INTO `core_role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `core_roles` r
CROSS JOIN `core_permissions` p
WHERE r.`tenant_id` = 1
  AND r.`slug` IN ('admin', 'recruitment_manager', 'recruiter')
  AND p.`slug` IN ('recruit.reports.view', 'recruit.reports.export');

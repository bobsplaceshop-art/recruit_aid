-- =====================================================================
-- 0050_activate_hr_payroll_permissions.sql
-- Activate HR Aid's payroll and employee permissions. Uses an
-- idempotent upsert so re-running is safe and existing inactive rows
-- become active with the authoritative sensitivity classes.
-- =====================================================================

INSERT INTO `core_permissions`
    (`slug`, `module`, `description`, `scope`, `module_key`, `sensitivity`, `is_active`)
VALUES
    ('hr.payroll.view',    'hr.payroll',   'View payroll records',  'app', 'hr', 'sensitive',  1),
    ('hr.payroll.approve', 'hr.payroll',   'Approve payroll runs',  'app', 'hr', 'restricted', 1),
    ('hr.employees.view',  'hr.employees', 'View employee records', 'app', 'hr', 'public',     1),
    ('hr.employees.edit',  'hr.employees', 'Edit employee records', 'app', 'hr', 'public',     1)
ON DUPLICATE KEY UPDATE
    `module`      = VALUES(`module`),
    `description` = VALUES(`description`),
    `scope`       = VALUES(`scope`),
    `module_key`  = VALUES(`module_key`),
    `sensitivity` = VALUES(`sensitivity`),
    `is_active`   = VALUES(`is_active`);
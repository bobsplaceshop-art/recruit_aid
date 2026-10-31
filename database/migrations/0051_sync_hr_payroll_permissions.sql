-- =====================================================================
-- 0051_sync_hr_payroll_permissions.sql
-- Corrective for 0050. 0050 used INSERT IGNORE, which does not update
-- existing rows. This migration uses an idempotent upsert so the
-- permissions are authoritative: module, description, scope,
-- module_key, sensitivity, and is_active all synced.
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
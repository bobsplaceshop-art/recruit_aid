-- =====================================================================
-- 0047_seed_cas_permissions.sql
-- Seeds the CAS Core permissions and backfills existing recruit.*
-- =====================================================================

INSERT IGNORE INTO `core_permissions` (`slug`, `module`, `description`, `scope`, `module_key`, `sensitivity`) VALUES
('cas.dashboard.access', 'core', 'Access the CAS Suit central dashboard', 'global', NULL, 'public'),
('cas.users.manage', 'core', 'Manage users across all applications', 'global', NULL, 'public'),
('cas.applications.manage', 'core', 'Assign users to applications', 'global', NULL, 'public'),
('cas.rbac.manage_global', 'core', 'Create and manage global roles and permissions', 'global', NULL, 'public'),
('cas.rbac.delegate_admin', 'core', 'Appoint and revoke application administrators', 'global', NULL, 'sensitive'),
('cas.rbac.audit.view', 'core', 'View the RBAC audit trail', 'global', NULL, 'sensitive'),
('cas.shared_data.manage', 'core', 'Configure cross-module data sharing', 'global', NULL, 'sensitive');

UPDATE `core_permissions` SET `sensitivity` = 'public' WHERE `module` = 'recruit';
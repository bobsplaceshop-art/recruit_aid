-- 0068_seed_cas_tenants_manage_permission.sql
--
-- Adds the cas.tenants.manage permission and grants it to cas_admin.
--
-- The /cas/tenants/* CRUD surface requires this permission. It's a
-- platform-scope permission (no tenant context), so it belongs in the
-- cas.* namespace and is evaluated via hasPlatformPermission().
--
-- Only cas_admin holds it. If a future need arises for a separate
-- "tenant viewer" role, a cas.tenants.view permission can be added then.

-- 1. Seed the permission
INSERT INTO `core_permissions`
    (`slug`, `module`, `description`, `scope`, `module_key`, `sensitivity`, `is_active`)
VALUES
    ('cas.tenants.manage', 'cas.tenants', 'Manage tenant lifecycle (create, suspend, archive)', 'global', 'cas', 'public', 1)
ON DUPLICATE KEY UPDATE `is_active` = 1;

-- 2. Grant it to cas_admin
INSERT IGNORE INTO `core_platform_role_permissions` (`role_id`, `permission_id`)
SELECT pr.`id`, p.`id`
FROM `core_platform_roles` pr
JOIN `core_permissions` p ON p.`slug` = 'cas.tenants.manage' AND p.`is_active` = 1
WHERE pr.`slug` = 'cas_admin';

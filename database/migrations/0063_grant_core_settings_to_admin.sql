-- 0063_grant_core_settings_to_admin.sql
--
-- Migration 0061 removes the super_admin role (and its permissions).
-- core.settings.manage was granted ONLY to super_admin, so after 0061
-- no role holds it. The `admin` role (tenant administrator) already holds
-- every other administrative permission (core.users.*, core.custom_fields.*,
-- core.documents.*, all recruit.*) — the absence of core.settings.manage
-- is an oversight from the pre-migration model.
--
-- This migration restores the intended grant to every `admin` role,
-- across all tenants. Future tenants' admin roles receive it through
-- their own tenant-provisioning flow (out of scope here).

INSERT IGNORE INTO core_role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM core_roles r
JOIN core_permissions p ON p.slug = 'core.settings.manage' AND p.is_active = 1
WHERE r.slug = 'admin';

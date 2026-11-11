-- 0070_assign_cas_developer_role.sql
--
-- Assign the newly-seeded cas_developer role to the cas-developer user
-- and insert the platform_owners row.
--
-- Migration 0069 seeds the role; this migration assigns it to the
-- already-created cas-developer@recruitaid.test user. Idempotent.

INSERT IGNORE INTO `core_user_platform_roles` (`user_id`, `role_id`, `assigned_by`)
SELECT u.`id`, pr.`id`, u.`id`
FROM `core_users` u
JOIN `core_platform_roles` pr ON pr.`slug` = 'cas_developer'
WHERE u.`email` = 'cas-developer@recruitaid.test'
  AND u.`deleted_at` IS NULL;

INSERT IGNORE INTO `platform_owners` (`user_id`, `designation`, `contract_reference`)
SELECT u.`id`, 'Founder / Lead Developer', 'INTERNAL-2026-001'
FROM `core_users` u
WHERE u.`email` = 'cas-developer@recruitaid.test'
  AND u.`deleted_at` IS NULL;

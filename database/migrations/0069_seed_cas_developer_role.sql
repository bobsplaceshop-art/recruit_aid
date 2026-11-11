-- 0069_seed_cas_developer_role.sql
--
-- The doctrine distinguishes cas_admin (operational) from cas_developer
-- (contractual owner) — see RBAC-ARCHITECTURE.md §4.1. Migration 0061
-- seeded cas_admin but not cas_developer. This migration adds it.
--
-- cas_developer is a superset: it holds all cas.* permissions. The
-- additional authority (grant cas_developer delegations, cancel pending
-- tenant revocations) is enforced via platform_owners, not via the role
-- itself.

INSERT INTO `core_platform_roles` (`name`, `slug`, `description`, `is_system`)
VALUES ('CAS Developer', 'cas_developer', 'Contractual platform owner', 1)
ON DUPLICATE KEY UPDATE `is_system` = 1;

INSERT IGNORE INTO `core_platform_role_permissions` (`role_id`, `permission_id`)
SELECT pr.`id`, p.`id`
FROM `core_platform_roles` pr
JOIN `core_permissions` p
    ON p.`slug` LIKE 'cas.%'
   AND p.`is_active` = 1
WHERE pr.`slug` = 'cas_developer';

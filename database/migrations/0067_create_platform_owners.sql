-- 0067_create_platform_owners.sql
--
-- Designates the contractual platform owner(s).
--
-- Per RBAC-ARCHITECTURE.md §14.1, the platform has two authority levels:
--   * cas_admin: operational, holds cas.* permissions, can context-switch.
--   * cas_developer: contractual owner, the only party allowed to grant
--     "cas_developer" delegations and cancel pending tenant revocations.
--
-- This table distinguishes the two. The web application reads it (for
-- permission checks) but never writes to it. Adding or removing a
-- platform owner requires direct DB access or a signed CLI command,
-- per the doctrine "bootstrapped, not assigned".
--
-- ON DELETE RESTRICT: the developer's identity is contractual. A user
-- row cannot be deleted while they are still designated the owner.
-- Ownership must be transferred first.

CREATE TABLE `platform_owners` (
    `user_id` INT UNSIGNED NOT NULL,
    `designation` VARCHAR(100) NOT NULL,
    `contract_reference` VARCHAR(200) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`user_id`),
    CONSTRAINT `fk_platform_owner_user`
        FOREIGN KEY (`user_id`) REFERENCES `core_users` (`id`)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 0064_create_support_delegations.sql
--
-- Support delegations: an explicit, expiring, auditable grant that allows
-- a platform operator (cas_admin) or a tenant principal to access a
-- specific tenant's data, scoped to read-only or read-write, without
-- changing the grantee's home tenant.
--
-- Asymmetric revocation:
--   * If granted_by_type = 'cas_developer', revocation is immediate.
--   * If granted_by_type = 'tenant_admin', revocation goes through an
--     internal authorization layer with a cooling-off window. The
--     developer can cancel a pending tenant revocation.

CREATE TABLE `core_support_delegations` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `tenant_id` INT UNSIGNED NOT NULL,
    `granted_by_user_id` INT UNSIGNED NOT NULL,
    `granted_by_type` ENUM('tenant_admin','cas_developer') NOT NULL,
    `scope` ENUM('read_only','read_write','full') NOT NULL DEFAULT 'read_only',
    `reason` TEXT NULL,
    `expires_at` DATETIME NOT NULL,
    `revocation_state` ENUM('active','revocation_pending','revoked') NOT NULL DEFAULT 'active',
    `revocation_requested_at` DATETIME NULL,
    `revocation_effective_at` DATETIME NULL,
    `revoked_at` DATETIME NULL,
    `revoked_by_user_id` INT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_support_user_tenant` (`user_id`, `tenant_id`, `revocation_state`),
    KEY `idx_support_expiry` (`expires_at`, `revocation_state`),
    KEY `idx_support_pending` (`revocation_state`, `revocation_effective_at`),
    CONSTRAINT `fk_support_user` FOREIGN KEY (`user_id`) REFERENCES `core_users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_support_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `core_tenants` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_support_granted_by` FOREIGN KEY (`granted_by_user_id`) REFERENCES `core_users` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_support_revoked_by` FOREIGN KEY (`revoked_by_user_id`) REFERENCES `core_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

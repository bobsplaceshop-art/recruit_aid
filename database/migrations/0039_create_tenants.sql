-- =====================================================================
-- 0039_create_tenants.sql
-- Tenants are the top-level isolation boundary in CAS Suit.
-- One tenant = one customer's install. All business data belongs
-- to exactly one tenant. Users belong to exactly one tenant.
--
-- This is different from a "client organisation" (the agency's
-- customer, i.e. the companies the agency places candidates at).
-- Every tenant has many client organisations.
-- =====================================================================

CREATE TABLE IF NOT EXISTS `core_tenants` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code`        VARCHAR(20) NOT NULL,
    `name`        VARCHAR(255) NOT NULL,
    `status`      ENUM('active','suspended','archived') NOT NULL DEFAULT 'active',
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`  DATETIME NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_tenant_code` (`code`),
    KEY `idx_tenant_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed the default tenant for this install
INSERT IGNORE INTO `core_tenants` (`id`, `code`, `name`, `status`)
VALUES (1, 'TEN-000001', 'Default Tenant', 'active');

-- Add tenant_id to core_users (nullable first; backfilled below)
ALTER TABLE `core_users`
    ADD COLUMN `tenant_id` INT UNSIGNED NULL AFTER `id`,
    ADD KEY `idx_user_tenant` (`tenant_id`),
    ADD CONSTRAINT `fk_user_tenant` FOREIGN KEY (`tenant_id`)
        REFERENCES `core_tenants` (`id`) ON DELETE RESTRICT;

-- Backfill: every existing user belongs to the default tenant
UPDATE `core_users` SET `tenant_id` = 1 WHERE `tenant_id` IS NULL;

-- Now make it required
ALTER TABLE `core_users`
    MODIFY COLUMN `tenant_id` INT UNSIGNED NOT NULL;

-- Sequence counter for future tenant codes
INSERT IGNORE INTO `core_sequence_counters` (`name`, `last_value`)
VALUES ('core_tenant_code', 1);

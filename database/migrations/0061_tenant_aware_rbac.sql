-- 0061: Separate global CAS platform authority from tenant-owned Recruit Aid RBAC.
-- Existing Recruit Aid roles and data are preserved under tenant 1.
-- The former super_admin assignment is moved to the separate CAS platform RBAC.

-- 1. Create a separate global platform RBAC model.
CREATE TABLE `core_platform_roles` (
    `id` int unsigned NOT NULL AUTO_INCREMENT,
    `name` varchar(80) NOT NULL,
    `slug` varchar(80) NOT NULL,
    `description` varchar(255) DEFAULT NULL,
    `is_system` tinyint(1) NOT NULL DEFAULT 1,
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_platform_role_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `core_platform_role_permissions` (
    `role_id` int unsigned NOT NULL,
    `permission_id` int unsigned NOT NULL,
    PRIMARY KEY (`role_id`, `permission_id`),
    KEY `idx_platform_role_permissions_permission` (`permission_id`),
    CONSTRAINT `fk_cprp_role`
        FOREIGN KEY (`role_id`) REFERENCES `core_platform_roles` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_cprp_permission`
        FOREIGN KEY (`permission_id`) REFERENCES `core_permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `core_user_platform_roles` (
    `user_id` int unsigned NOT NULL,
    `role_id` int unsigned NOT NULL,
    `assigned_by` int unsigned DEFAULT NULL,
    `assigned_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `expires_at` datetime DEFAULT NULL,
    PRIMARY KEY (`user_id`, `role_id`),
    KEY `idx_cupr_role` (`role_id`),
    KEY `idx_cupr_assigned_by` (`assigned_by`),
    CONSTRAINT `fk_cupr_user`
        FOREIGN KEY (`user_id`) REFERENCES `core_users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_cupr_role`
        FOREIGN KEY (`role_id`) REFERENCES `core_platform_roles` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_cupr_assigned_by`
        FOREIGN KEY (`assigned_by`) REFERENCES `core_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `core_platform_roles` (`name`, `slug`, `description`, `is_system`)
VALUES (
    'CAS Administrator',
    'cas_admin',
    'Global administrator of the Corporate Aid Solutions platform.',
    1
);

-- CAS administrators receive only active CAS platform permissions.
INSERT INTO `core_platform_role_permissions` (`role_id`, `permission_id`)
SELECT pr.id, p.id
FROM `core_platform_roles` pr
JOIN `core_permissions` p
    ON p.slug LIKE 'cas.%'
   AND p.is_active = 1
WHERE pr.slug = 'cas_admin';

-- Transfer existing super_admin assignments to the new platform role.
-- The old role is removed from tenant RBAC below.
INSERT INTO `core_user_platform_roles` (`user_id`, `role_id`, `assigned_by`, `assigned_at`, `expires_at`)
SELECT ur.user_id, pr.id, ur.assigned_by, ur.assigned_at, ur.expires_at
FROM `core_user_roles` ur
JOIN `core_roles` old_role ON old_role.id = ur.role_id
JOIN `core_platform_roles` pr ON pr.slug = 'cas_admin'
WHERE old_role.slug = 'super_admin';

-- 2. Convert the existing Recruit Aid role catalogue into tenant-owned roles.
ALTER TABLE `core_roles`
    ADD COLUMN `tenant_id` int unsigned NULL AFTER `id`;

UPDATE `core_roles`
SET `tenant_id` = 1
WHERE `tenant_id` IS NULL;

UPDATE `core_roles`
SET `scope` = 'app',
    `module_key` = 'recruit'
WHERE `slug` <> 'super_admin';

-- Remove the legacy global role after its user assignment has been transferred.
DELETE rp
FROM `core_role_permissions` rp
JOIN `core_roles` r ON r.id = rp.role_id
WHERE r.slug = 'super_admin';

DELETE FROM `core_user_roles`
WHERE `role_id` = (
    SELECT id FROM `core_roles` WHERE slug = 'super_admin' LIMIT 1
);

DELETE FROM `core_roles`
WHERE `slug` = 'super_admin';

ALTER TABLE `core_roles`
    MODIFY COLUMN `tenant_id` int unsigned NOT NULL,
    DROP INDEX `uniq_slug`,
    ADD UNIQUE KEY `uniq_role_id_tenant` (`id`, `tenant_id`),
    ADD UNIQUE KEY `uniq_tenant_module_slug` (`tenant_id`, `module_key`, `slug`),
    ADD KEY `idx_roles_tenant_module` (`tenant_id`, `module_key`),
    ADD CONSTRAINT `fk_core_roles_tenant`
        FOREIGN KEY (`tenant_id`) REFERENCES `core_tenants` (`id`) ON DELETE RESTRICT;

-- 3. Make Recruit Aid role assignments tenant-aware and enforce matching ownership.
ALTER TABLE `core_users`
    ADD UNIQUE KEY `uniq_user_id_tenant` (`id`, `tenant_id`);

ALTER TABLE `core_user_roles`
    ADD COLUMN `tenant_id` int unsigned NULL FIRST;

UPDATE `core_user_roles` ur
JOIN `core_users` u ON u.id = ur.user_id
SET ur.tenant_id = u.tenant_id;

ALTER TABLE `core_user_roles`
    DROP FOREIGN KEY `fk_core_ur_role`,
    DROP FOREIGN KEY `fk_core_ur_user`,
    DROP PRIMARY KEY,
    MODIFY COLUMN `tenant_id` int unsigned NOT NULL,
    ADD PRIMARY KEY (`tenant_id`, `user_id`, `role_id`),
    ADD KEY `idx_cur_user_tenant` (`user_id`, `tenant_id`),
    ADD KEY `idx_cur_role_tenant` (`role_id`, `tenant_id`),
    ADD CONSTRAINT `fk_cur_user_tenant`
        FOREIGN KEY (`user_id`, `tenant_id`)
        REFERENCES `core_users` (`id`, `tenant_id`) ON DELETE CASCADE,
    ADD CONSTRAINT `fk_cur_role_tenant`
        FOREIGN KEY (`role_id`, `tenant_id`)
        REFERENCES `core_roles` (`id`, `tenant_id`) ON DELETE CASCADE;

-- 4. Make direct user grants and denials tenant-aware.
ALTER TABLE `core_user_permissions`
    ADD COLUMN `tenant_id` int unsigned NULL AFTER `id`;

UPDATE `core_user_permissions` up
JOIN `core_users` u ON u.id = up.user_id
SET up.tenant_id = u.tenant_id;

ALTER TABLE `core_user_permissions`
    DROP FOREIGN KEY `fk_core_up_user`,
    DROP INDEX `uniq_user_permission`,
    MODIFY COLUMN `tenant_id` int unsigned NOT NULL,
    ADD UNIQUE KEY `uniq_tenant_user_permission` (`tenant_id`, `user_id`, `permission_id`),
    ADD KEY `idx_user_permissions_tenant` (`tenant_id`),
    ADD CONSTRAINT `fk_cup_user_tenant`
        FOREIGN KEY (`user_id`, `tenant_id`)
        REFERENCES `core_users` (`id`, `tenant_id`) ON DELETE CASCADE;

-- 5. Make notification rules tenant-owned.
-- Existing rules are assigned to the original tenant.
ALTER TABLE `core_notification_rules`
    ADD COLUMN `tenant_id` int unsigned NULL AFTER `id`;

UPDATE `core_notification_rules`
SET `tenant_id` = 1
WHERE `tenant_id` IS NULL;

ALTER TABLE `core_notification_rules`
    DROP INDEX `uniq_event_recipient`,
    MODIFY COLUMN `tenant_id` int unsigned NOT NULL,
    ADD UNIQUE KEY `uniq_tenant_event_recipient`
        (`tenant_id`, `event_type`, `recipient_type`, `recipient_value`),
    ADD KEY `idx_notification_rules_tenant_event` (`tenant_id`, `event_type`, `is_active`),
    ADD CONSTRAINT `fk_notification_rules_tenant`
        FOREIGN KEY (`tenant_id`) REFERENCES `core_tenants` (`id`) ON DELETE CASCADE;

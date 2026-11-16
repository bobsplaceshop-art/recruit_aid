-- ============================================================
-- Corporate Aid — seed admin and researcher users
-- ============================================================
-- Creates the initial super_admin and a researcher test account.
-- Both are for local development. On production, the installer
-- collects the first admin's credentials during setup.
--
-- Password hashes use Argon2id, generated with
-- CorporateAid\Platform\Support\Hasher::hash().
--
-- tenant_id = 1 (Default Tenant) — every user belongs to exactly
-- one tenant. This matches core_tenants seeded in migration 0039.
-- ============================================================

INSERT INTO `core_users` (`tenant_id`, `name`, `email`, `password_hash`, `status`, `timezone`, `locale`, `is_client_user`) VALUES
    (
        1,
        'Admin User',
        'admin@recruitaid.test',
        '$argon2id$v=19$m=65536,t=4,p=1$OTRWNDZLTFUwblE1bVN5RQ$9HCVBMd48qYxB7pEuUBTIm9DCLP8tf9HPMp5e5QlAck',
        'active',
        'Africa/Nairobi',
        'en',
        0
    ),
    (
        1,
        'Test Researcher',
        'researcher@recruitaid.test',
        '$argon2id$v=19$m=65536,t=4,p=1$eGNNeUQucTAxMUpGdGhmLw$wmrXBC4INVRD7kj5ulSS2yNjkLQRgY6XjFL/LN3mA7g',
        'active',
        'Africa/Nairobi',
        'en',
        0
    )
ON DUPLICATE KEY UPDATE
    `name`          = VALUES(`name`),
    `password_hash` = VALUES(`password_hash`),
    `status`        = VALUES(`status`);

-- Attach admin role to admin user.
-- Note: super_admin was deleted by migration 0061. The admin role is
-- the tenant-scoped equivalent (grants all permissions except
-- core.settings.manage and core.audit.view per 0001's role assignments).
-- The tenant_id column was added to core_user_roles by migration 0061
-- with NOT NULL — the insert must include it.
INSERT IGNORE INTO `core_user_roles` (`tenant_id`, `user_id`, `role_id`)
SELECT u.`tenant_id`, u.`id`, r.`id` FROM `core_users` u CROSS JOIN `core_roles` r
WHERE u.`email` = 'admin@recruitaid.test'
  AND r.`slug` = 'admin'
  AND r.`tenant_id` = u.`tenant_id`;

-- Attach researcher role to researcher.
-- Includes tenant_id (required by migration 0061).
INSERT IGNORE INTO `core_user_roles` (`tenant_id`, `user_id`, `role_id`)
SELECT u.`tenant_id`, u.`id`, r.`id` FROM `core_users` u CROSS JOIN `core_roles` r
WHERE u.`email` = 'researcher@recruitaid.test'
  AND r.`slug` = 'researcher'
  AND r.`tenant_id` = u.`tenant_id`;

-- Attach the recruit application to both users.
-- Required for app_access:recruit middleware — without it, the user
-- passes authentication but is denied by the app-access gate.
INSERT IGNORE INTO `core_user_applications` (`user_id`, `module_key`, `is_default`)
SELECT u.`id`, 'recruit', 1
FROM `core_users` u
WHERE u.`email` = 'admin@recruitaid.test';

INSERT IGNORE INTO `core_user_applications` (`user_id`, `module_key`, `is_default`)
SELECT u.`id`, 'recruit', 1
FROM `core_users` u
WHERE u.`email` = 'researcher@recruitaid.test';

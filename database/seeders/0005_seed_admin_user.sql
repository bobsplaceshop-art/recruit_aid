-- ============================================================
-- Corporate Aid — seed admin and researcher users
-- ============================================================
-- Creates the initial super_admin and a researcher test account.
-- Both are for local development. On production, the installer
-- collects the first admin's credentials during setup.
--
-- Password hashes use Argon2id, generated with
-- CorporateAid\Platform\Support\Hasher::hash().
-- ============================================================

INSERT INTO `core_users` (`name`, `email`, `password_hash`, `status`, `timezone`, `locale`, `is_client_user`) VALUES
    (
        'Admin User',
        'admin@recruitaid.test',
        '$argon2id$v=19$m=65536,t=4,p=1$OTRWNDZLTFUwblE1bVN5RQ$9HCVBMd48qYxB7pEuUBTIm9DCLP8tf9HPMp5e5QlAck',
        'active',
        'Africa/Nairobi',
        'en',
        0
    ),
    (
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

-- Attach super_admin role to admin
INSERT IGNORE INTO `core_user_roles` (`user_id`, `role_id`)
SELECT u.`id`, r.`id` FROM `core_users` u CROSS JOIN `core_roles` r
WHERE u.`email` = 'admin@recruitaid.test' AND r.`slug` = 'super_admin';

-- Attach researcher role to researcher
INSERT IGNORE INTO `core_user_roles` (`user_id`, `role_id`)
SELECT u.`id`, r.`id` FROM `core_users` u CROSS JOIN `core_roles` r
WHERE u.`email` = 'researcher@recruitaid.test' AND r.`slug` = 'researcher';

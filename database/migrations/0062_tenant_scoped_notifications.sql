-- 0062: Enforce tenant ownership for notification records and correct
-- default notification recipients after the platform/tenant RBAC split.

-- 1. Assign existing notifications to the tenant that owns each user.
ALTER TABLE `core_notifications`
    ADD COLUMN `tenant_id` INT UNSIGNED NULL AFTER `id`;

UPDATE `core_notifications` n
JOIN `core_users` u ON u.id = n.user_id
SET n.tenant_id = u.tenant_id
WHERE n.tenant_id IS NULL;

-- Fail safely if any notification has no matching user.
-- The NOT NULL conversion will reject unresolved records.

ALTER TABLE `core_notifications`
    DROP FOREIGN KEY `fk_core_notif_user`,
    MODIFY COLUMN `tenant_id` INT UNSIGNED NOT NULL,
    ADD KEY `idx_notif_tenant_user_read` (`tenant_id`, `user_id`, `read_at`),
    ADD KEY `idx_notif_tenant_user_created` (`tenant_id`, `user_id`, `created_at`),
    ADD CONSTRAINT `fk_notif_user_tenant`
        FOREIGN KEY (`user_id`, `tenant_id`)
        REFERENCES `core_users` (`id`, `tenant_id`) ON DELETE CASCADE;

-- 2. Tenant account events must notify tenant administrators,
-- not the former global super_admin role.
UPDATE `core_notification_rules`
SET
    `recipient_value` = 'admin',
    `notes` = 'Notify tenant administrators when a user account is created'
WHERE `event_type` = 'user.created'
  AND `recipient_type` = 'role'
  AND `recipient_value` = 'super_admin';

UPDATE `core_notification_rules`
SET
    `recipient_value` = 'admin',
    `notes` = 'Notify tenant administrators when a user status changes'
WHERE `event_type` = 'user.status_changed'
  AND `recipient_type` = 'role'
  AND `recipient_value` = 'super_admin';

-- 3. These are CAS platform security events, not tenant events.
-- Disable their legacy tenant rules until platform notifications
-- have their own recipient resolution and storage.
UPDATE `core_notification_rules`
SET `is_active` = 0
WHERE `event_type` IN ('settings.changed', 'rate_limit.hit');

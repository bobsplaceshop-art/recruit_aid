-- 0071_recruit_tasks.sql
--
-- Adds the recruit_tasks table and the four recruit.tasks.* permissions.
--
-- A task is a lightweight, assignable work item attached to an entity
-- (candidate, vacancy, application, interview, offer) or standalone.
--
-- Task permissions:
--   recruit.tasks.view   — see the task list, list tasks for an entity
--   recruit.tasks.create — create new tasks
--   recruit.tasks.edit   — complete, reopen, edit
--   recruit.tasks.delete — soft-delete tasks
--
-- Granted to admin, recruitment_manager, recruiter (all four) and
-- researcher (view + create only).

CREATE TABLE `recruit_tasks` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id` INT UNSIGNED NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT NULL,
    `status` ENUM('open','completed','cancelled') NOT NULL DEFAULT 'open',
    `priority` ENUM('low','normal','high') NOT NULL DEFAULT 'normal',
    `due_at` DATETIME NULL,
    `assigned_to` INT UNSIGNED NULL,
    `created_by` INT UNSIGNED NOT NULL,
    `entity_type` VARCHAR(40) NULL,
    `entity_id` BIGINT UNSIGNED NULL,
    `completed_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME NULL,
    PRIMARY KEY (`id`),
    KEY `idx_tasks_tenant_status` (`tenant_id`, `status`),
    KEY `idx_tasks_assigned` (`tenant_id`, `assigned_to`, `status`),
    KEY `idx_tasks_entity` (`tenant_id`, `entity_type`, `entity_id`),
    KEY `idx_tasks_due` (`tenant_id`, `due_at`, `status`),
    CONSTRAINT `fk_tasks_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `core_tenants` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_tasks_assigned` FOREIGN KEY (`assigned_to`) REFERENCES `core_users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_tasks_creator` FOREIGN KEY (`created_by`) REFERENCES `core_users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed the four permissions (idempotent).
INSERT INTO `core_permissions`
    (`slug`, `module`, `description`, `scope`, `module_key`, `sensitivity`, `is_active`)
VALUES
    ('recruit.tasks.view',   'recruit.tasks', 'View tasks',                    'app', 'recruit', 'public', 1),
    ('recruit.tasks.create', 'recruit.tasks', 'Create tasks',                  'app', 'recruit', 'public', 1),
    ('recruit.tasks.edit',   'recruit.tasks', 'Edit, complete, reopen tasks', 'app', 'recruit', 'public', 1),
    ('recruit.tasks.delete', 'recruit.tasks', 'Delete tasks',                  'app', 'recruit', 'public', 1)
ON DUPLICATE KEY UPDATE `is_active` = 1;

-- Grant to admin, recruitment_manager, recruiter (all four).
INSERT IGNORE INTO `core_role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `core_roles` r
JOIN `core_permissions` p ON p.`slug` LIKE 'recruit.tasks.%' AND p.`is_active` = 1
WHERE r.`tenant_id` = 1
  AND r.`module_key` = 'recruit'
  AND r.`slug` IN ('admin', 'recruitment_manager', 'recruiter');

-- Grant to researcher (view + create only).
INSERT IGNORE INTO `core_role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `core_roles` r
JOIN `core_permissions` p
    ON p.`slug` IN ('recruit.tasks.view', 'recruit.tasks.create')
   AND p.`is_active` = 1
WHERE r.`tenant_id` = 1
  AND r.`module_key` = 'recruit'
  AND r.`slug` = 'researcher';

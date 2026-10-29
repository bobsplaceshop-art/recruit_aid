-- 0032_create_contact_roles.sql
--
-- Stage 1 of migrating contact role flags (is_primary, is_hiring_manager,
-- etc.) from boolean columns on recruit_client_contacts to a proper
-- pivot table driven by the lookups system.
--
-- This migration:
--   1. Creates the pivot table `recruit_contact_roles`
--   2. Seeds the `contact_role` lookups group (5 system roles)
--   3. Backfills existing boolean flags into the pivot
--
-- The boolean columns remain untouched for now (Stage 2 will switch
-- the code to read/write the pivot; Stage 3 will eventually drop them).

CREATE TABLE IF NOT EXISTS `recruit_contact_roles` (
    `contact_id` INT UNSIGNED NOT NULL,
    `role_slug`  VARCHAR(60)  NOT NULL,
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`contact_id`, `role_slug`),
    CONSTRAINT `fk_rec_cr_contact` FOREIGN KEY (`contact_id`)
        REFERENCES `recruit_client_contacts` (`id`) ON DELETE CASCADE,
    KEY `idx_role` (`role_slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed the contact_role lookups group
INSERT IGNORE INTO `core_lookups` (`group_slug`, `slug`, `label`, `sort_order`, `is_system`) VALUES
  ('contact_role', 'is_primary',        'Primary Contact', 1, 1),
  ('contact_role', 'is_hiring_manager', 'Hiring Manager',  2, 1),
  ('contact_role', 'is_finance',        'Finance',         3, 1),
  ('contact_role', 'is_hr',             'HR',              4, 1),
  ('contact_role', 'is_decision_maker', 'Decision Maker',  5, 1);

-- Backfill: for each boolean column that is 1, insert one pivot row
INSERT IGNORE INTO `recruit_contact_roles` (`contact_id`, `role_slug`)
SELECT `id`, 'is_primary' FROM `recruit_client_contacts`
WHERE `is_primary` = 1 AND `deleted_at` IS NULL;

INSERT IGNORE INTO `recruit_contact_roles` (`contact_id`, `role_slug`)
SELECT `id`, 'is_hiring_manager' FROM `recruit_client_contacts`
WHERE `is_hiring_manager` = 1 AND `deleted_at` IS NULL;

INSERT IGNORE INTO `recruit_contact_roles` (`contact_id`, `role_slug`)
SELECT `id`, 'is_finance' FROM `recruit_client_contacts`
WHERE `is_finance` = 1 AND `deleted_at` IS NULL;

INSERT IGNORE INTO `recruit_contact_roles` (`contact_id`, `role_slug`)
SELECT `id`, 'is_hr' FROM `recruit_client_contacts`
WHERE `is_hr` = 1 AND `deleted_at` IS NULL;

INSERT IGNORE INTO `recruit_contact_roles` (`contact_id`, `role_slug`)
SELECT `id`, 'is_decision_maker' FROM `recruit_client_contacts`
WHERE `is_decision_maker` = 1 AND `deleted_at` IS NULL;

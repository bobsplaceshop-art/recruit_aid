-- 0031_create_lookups.sql
--
-- Central table for configurable enums. Replaces hardcoded arrays in
-- validators and views (employment_type, work_mode, seniority, etc.).
--
-- Each row is one option in one group. Admins manage them from
-- /admin/lookups without touching code.
--
-- Groups are referenced by group_slug (e.g. 'work_mode'). Slugs are
-- unique within a group. is_system=1 means the row was seeded and
-- cannot be deleted (but can be renamed or hidden via is_active).

CREATE TABLE IF NOT EXISTS `core_lookups` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `group_slug`  VARCHAR(60)  NOT NULL,
    `slug`        VARCHAR(60)  NOT NULL,
    `label`       VARCHAR(150) NOT NULL,
    `description` VARCHAR(500) NULL,
    `sort_order`  INT          NOT NULL DEFAULT 0,
    `is_active`   TINYINT(1)   NOT NULL DEFAULT 1,
    `is_system`   TINYINT(1)   NOT NULL DEFAULT 0,
    `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`  DATETIME     NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_group_slug` (`group_slug`, `slug`),
    KEY `idx_group_order` (`group_slug`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

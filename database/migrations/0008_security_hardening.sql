-- ============================================================
-- Recruit Aid — Security Hardening (Layer 1)
-- ============================================================
-- Creates the monotonic sequence counter table and seeds the
-- counter keys used by Recruit Aid's repositories.
--
-- Historical note: earlier versions of this migration assumed
-- the table already existed (created manually during development).
-- This rewrite makes the migration self-contained: it creates the
-- table if missing and seeds the counter rows idempotently.
--
-- Counter key naming follows docs/NAMING-CONVENTION.md section 5:
--   {module_short}_{entity}_code
-- ============================================================

CREATE TABLE IF NOT EXISTS `core_sequence_counters` (
    `name`       VARCHAR(60) NOT NULL,
    `last_value` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `core_sequence_counters` (`name`, `last_value`) VALUES
    ('rec_client_org_code',     0),
    ('rec_client_contact_code', 0),
    ('rec_engagement_code',     0),
    ('rec_vacancy_code',        0)
ON DUPLICATE KEY UPDATE `name` = `name`;

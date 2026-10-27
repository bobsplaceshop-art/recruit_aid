-- ============================================================
-- Recruit Aid — Entity-file links
-- ============================================================
-- Many-to-many between files and entities. A single uploaded
-- file can be linked to multiple entities if needed (e.g. a
-- shared certificate across two applications).
--
-- `file_category` distinguishes purposes within one entity:
--   'cv', 'certificate', 'id_proof', 'avatar', 'logo', 'report', etc.
--
-- Unique key prevents duplicate links of the same file to the
-- same entity+category. This is what enforces "one avatar per user"
-- at the schema level.
-- ============================================================

CREATE TABLE `core_entity_files` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `file_id`         INT UNSIGNED NOT NULL,
    `entity_type`     VARCHAR(60) NOT NULL,
    `entity_id`       INT UNSIGNED NOT NULL,
    `file_category`   VARCHAR(60) NOT NULL,
    `linked_by`       INT UNSIGNED NULL,
    `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_file_entity_category`
        (`file_id`, `entity_type`, `entity_id`, `file_category`),
    KEY `idx_entity`   (`entity_type`, `entity_id`),
    KEY `idx_file`     (`file_id`),
    KEY `idx_category` (`file_category`),
    CONSTRAINT `fk_core_ef_file`
        FOREIGN KEY (`file_id`) REFERENCES `core_files` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_core_ef_linked_by`
        FOREIGN KEY (`linked_by`) REFERENCES `core_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

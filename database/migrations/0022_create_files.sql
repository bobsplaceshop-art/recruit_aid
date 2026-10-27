-- ============================================================
-- Recruit Aid — Files (metadata for uploaded documents)
-- ============================================================
-- One row per uploaded file. Entity-agnostic — linking to
-- specific entities (candidate, user, organisation) happens
-- via the `core_entity_files` link table (migration 0023).
--
-- Design notes:
--   - `uuid` is the public reference. Never expose the numeric id.
--   - `stored_name` is a regenerated, safe name — never trust
--     the user-supplied original filename on disk.
--   - `sha256_hash` enables dedup detection and integrity checks.
--   - `deleted_at` is soft-delete: physical file stays on disk
--     until a purge cron removes orphans.
-- ============================================================

CREATE TABLE `core_files` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `uuid`            CHAR(36) NOT NULL,
    `original_name`   VARCHAR(255) NOT NULL,
    `stored_name`     VARCHAR(255) NOT NULL,
    `storage_path`    VARCHAR(500) NOT NULL,
    `mime_type`       VARCHAR(127) NOT NULL,
    `extension`       VARCHAR(20)  NOT NULL,
    `size_bytes`      BIGINT UNSIGNED NOT NULL,
    `sha256_hash`     CHAR(64) NOT NULL,
    `uploaded_by`     INT UNSIGNED NULL,
    `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `deleted_at`      DATETIME NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_uuid` (`uuid`),
    KEY `idx_hash`     (`sha256_hash`),
    KEY `idx_uploader` (`uploaded_by`),
    KEY `idx_deleted`  (`deleted_at`),
    CONSTRAINT `fk_core_files_uploader`
        FOREIGN KEY (`uploaded_by`) REFERENCES `core_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

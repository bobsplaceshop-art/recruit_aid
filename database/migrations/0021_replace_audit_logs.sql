-- ============================================================
-- Recruit Aid — Replace audit_logs with HTTP request audit
-- ============================================================
-- Migration 0001 created `core_audit_logs` as a "security events"
-- table (action / module / record_type / record_id / description).
-- Migration 0007 added `core_activities` for the business timeline
-- and explicitly distinguished the two. Nothing has ever written
-- to `core_audit_logs` — it is empty and unused.
--
-- Migration 0020 attempted to create a proper HTTP-request
-- audit table under the same name, but its
-- `CREATE TABLE IF NOT EXISTS` clause silently no-opped against
-- the legacy table.
--
-- This migration retires the legacy table and creates the new
-- HTTP audit log in one atomic step. Migration 0020 is a no-op
-- going forward and can be ignored.
-- ============================================================

-- 1. Move the legacy table aside (empty, unused, safe)
RENAME TABLE `core_audit_logs` TO `core_audit_logs_legacy`;

-- 2. Create the new HTTP request audit log
CREATE TABLE `core_audit_logs` (
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`         INT UNSIGNED NULL,
    `method`          VARCHAR(10) NOT NULL,
    `path`            VARCHAR(255) NOT NULL,
    `ip_address`      VARCHAR(45) NULL,
    `user_agent`      VARCHAR(255) NULL,
    `request_hash`    CHAR(64) NULL,
    `response_status` SMALLINT UNSIGNED NULL,
    `entity_type`     VARCHAR(60) NULL,
    `entity_id`       INT UNSIGNED NULL,
    `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user_time`   (`user_id`, `created_at`),
    KEY `idx_path_time`   (`path`(100), `created_at`),
    KEY `idx_entity`      (`entity_type`, `entity_id`),
    KEY `idx_created_at`  (`created_at`),
    CONSTRAINT `fk_core_al_user`
        FOREIGN KEY (`user_id`) REFERENCES `core_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

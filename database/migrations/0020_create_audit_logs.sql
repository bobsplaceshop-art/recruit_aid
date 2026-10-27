-- ============================================================
-- Recruit Aid — Audit log
-- ============================================================
-- Immutable, append-only record of every state-changing HTTP
-- request (POST / PUT / PATCH / DELETE). Distinct from the
-- `core_activities` table, which is a business timeline shown in UI.
--
-- - user_id NULL = unauthenticated request (public form, etc.)
-- - request_hash = SHA-256 of the request payload (no PII stored)
-- - entity_type / entity_id = optional business context, set by
--   controllers via AuditContext before the response is returned.
--
-- No updated_at, no deleted_at — rows are never modified or removed.
-- ============================================================

CREATE TABLE IF NOT EXISTS `core_audit_logs` (
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

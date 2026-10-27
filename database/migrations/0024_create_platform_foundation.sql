-- ============================================================
-- Recruit Aid — Platform Foundation
-- ============================================================
-- Foundational tables for:
--   * Module registry and licensing
--   * Server Manager (integrations, health checks)
--   * Update System (available updates, history)
--   * Scheduled task registry
--
-- These tables are created empty. Features that use them are
-- built in later cycles. Having the schema from day one means
-- no migration against live customer data is needed later.
-- ============================================================

-- ---------- Module Registry ----------

CREATE TABLE IF NOT EXISTS `core_modules` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `module_key`      VARCHAR(40) NOT NULL,
    `name`            VARCHAR(100) NOT NULL,
    `description`     TEXT NULL,
    `current_version` VARCHAR(20) NOT NULL,
    `is_core`         TINYINT(1) NOT NULL DEFAULT 0,
    `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_module_key` (`module_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Module Licenses ----------

CREATE TABLE IF NOT EXISTS `core_module_licenses` (
    `id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `module_key`          VARCHAR(40) NOT NULL,
    `license_key_hash`    CHAR(64) NOT NULL,
    `license_payload`     TEXT NULL,
    `licensed_to`         VARCHAR(200) NULL,
    `issued_at`           DATETIME NULL,
    `expires_at`          DATETIME NULL,
    `revoked_at`          DATETIME NULL,
    `activated_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `activated_by`        INT UNSIGNED NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_license_hash` (`license_key_hash`),
    KEY `idx_module_key` (`module_key`),
    CONSTRAINT `fk_core_ml_module`
        FOREIGN KEY (`module_key`) REFERENCES `core_modules` (`module_key`) ON DELETE CASCADE,
    CONSTRAINT `fk_core_ml_activated_by`
        FOREIGN KEY (`activated_by`) REFERENCES `core_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Module State ----------

CREATE TABLE IF NOT EXISTS `core_module_state` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `module_key`      VARCHAR(40) NOT NULL,
    `is_enabled`      TINYINT(1) NOT NULL DEFAULT 1,
    `enabled_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `disabled_at`     DATETIME NULL,
    `disabled_reason` VARCHAR(255) NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_state_module` (`module_key`),
    CONSTRAINT `fk_core_ms_module`
        FOREIGN KEY (`module_key`) REFERENCES `core_modules` (`module_key`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Integrations (Server Manager) ----------

CREATE TABLE IF NOT EXISTS `core_integrations` (
    `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `integration_key`  VARCHAR(60) NOT NULL,
    `name`             VARCHAR(150) NOT NULL,
    `integration_type` VARCHAR(40) NOT NULL,
    `is_active`        TINYINT(1) NOT NULL DEFAULT 1,
    `config`           JSON NULL,
    `secret_encrypted` VARBINARY(2000) NULL,
    `last_check_at`    DATETIME NULL,
    `last_status`      VARCHAR(20) NULL,
    `last_error`       TEXT NULL,
    `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_integration_key` (`integration_key`),
    KEY `idx_integration_type` (`integration_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `core_integration_logs` (
    `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `integration_id` INT UNSIGNED NOT NULL,
    `direction`      ENUM('outbound','inbound') NOT NULL,
    `operation`      VARCHAR(80) NOT NULL,
    `status`         ENUM('success','error','timeout') NOT NULL,
    `http_status`    SMALLINT UNSIGNED NULL,
    `duration_ms`    INT UNSIGNED NULL,
    `error_message`  TEXT NULL,
    `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_integration_time` (`integration_id`, `created_at`),
    KEY `idx_created` (`created_at`),
    CONSTRAINT `fk_core_il_integration`
        FOREIGN KEY (`integration_id`) REFERENCES `core_integrations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Scheduled Tasks ----------

CREATE TABLE IF NOT EXISTS `core_scheduled_tasks` (
    `id`                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `task_key`             VARCHAR(80) NOT NULL,
    `name`                 VARCHAR(150) NOT NULL,
    `schedule_expression`  VARCHAR(60) NOT NULL,
    `handler_class`        VARCHAR(255) NOT NULL,
    `is_active`            TINYINT(1) NOT NULL DEFAULT 1,
    `last_run_at`          DATETIME NULL,
    `last_run_status`      VARCHAR(20) NULL,
    `last_run_duration_ms` INT UNSIGNED NULL,
    `next_run_at`          DATETIME NULL,
    `run_count`            BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `error_count`          BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `created_at`           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_task_key` (`task_key`),
    KEY `idx_next_run` (`next_run_at`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Health Checks ----------

CREATE TABLE IF NOT EXISTS `core_health_checks` (
    `id`                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `checked_at`         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `php_version`        VARCHAR(20) NULL,
    `db_version`         VARCHAR(40) NULL,
    `disk_free_bytes`    BIGINT UNSIGNED NULL,
    `disk_total_bytes`   BIGINT UNSIGNED NULL,
    `db_size_bytes`      BIGINT UNSIGNED NULL,
    `extensions_ok`      TINYINT(1) NULL,
    `failed_extension`   VARCHAR(40) NULL,
    `pending_migrations` INT UNSIGNED NOT NULL DEFAULT 0,
    `error_count_24h`    INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `idx_checked_at` (`checked_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Updates Available ----------

CREATE TABLE IF NOT EXISTS `core_updates_available` (
    `id`                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `module_key`         VARCHAR(40) NOT NULL,
    `from_version`       VARCHAR(20) NOT NULL,
    `to_version`         VARCHAR(20) NOT NULL,
    `release_type`       ENUM('patch','minor','major','security') NOT NULL,
    `release_notes_url`  VARCHAR(500) NULL,
    `release_summary`    TEXT NULL,
    `is_security`        TINYINT(1) NOT NULL DEFAULT 0,
    `downloaded_at`      DATETIME NULL,
    `applied_at`         DATETIME NULL,
    `skipped_at`         DATETIME NULL,
    `skipped_by`         INT UNSIGNED NULL,
    `skip_reason`        VARCHAR(255) NULL,
    `manifest_signature` VARCHAR(255) NULL,
    `created_at`         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_update` (`module_key`, `from_version`, `to_version`),
    KEY `idx_pending` (`applied_at`, `skipped_at`),
    CONSTRAINT `fk_core_ua_skipped_by`
        FOREIGN KEY (`skipped_by`) REFERENCES `core_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `core_updates_history` (
    `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `module_key`     VARCHAR(40) NOT NULL,
    `from_version`   VARCHAR(20) NOT NULL,
    `to_version`     VARCHAR(20) NOT NULL,
    `release_type`   ENUM('patch','minor','major','security') NOT NULL,
    `outcome`        ENUM('applied','rolled_back','failed') NOT NULL,
    `failure_reason` TEXT NULL,
    `applied_by`     INT UNSIGNED NULL,
    `applied_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `duration_ms`    INT UNSIGNED NULL,
    PRIMARY KEY (`id`),
    KEY `idx_module_time` (`module_key`, `applied_at`),
    CONSTRAINT `fk_core_uh_applied_by`
        FOREIGN KEY (`applied_by`) REFERENCES `core_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `core_update_manifest` (
    `id`                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `fetched_at`         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `source_url`         VARCHAR(500) NOT NULL,
    `signature_verified` TINYINT(1) NOT NULL DEFAULT 0,
    `raw_payload`        TEXT NOT NULL,
    `parsed_module_count` INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `idx_fetched_at` (`fetched_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

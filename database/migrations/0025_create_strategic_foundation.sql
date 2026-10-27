-- ============================================================
-- Recruit Aid — Strategic Foundation
-- ============================================================
-- Tables that support features likely to ship within 12 months
-- and that would require schema changes to existing columns if
-- added later. Creating them now (empty) avoids live migration
-- pain in future cycles.
-- ============================================================

-- ---------- Currencies ----------

CREATE TABLE IF NOT EXISTS `core_currencies` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code`          CHAR(3) NOT NULL,
    `name`          VARCHAR(80) NOT NULL,
    `symbol`        VARCHAR(10) NULL,
    `decimal_places` TINYINT UNSIGNED NOT NULL DEFAULT 2,
    `is_active`     TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Polymorphic Notes ----------

CREATE TABLE IF NOT EXISTS `core_notes` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `entity_type`   VARCHAR(60) NOT NULL,
    `entity_id`     INT UNSIGNED NOT NULL,
    `body`          TEXT NOT NULL,
    `is_pinned`     TINYINT(1) NOT NULL DEFAULT 0,
    `created_by`    INT UNSIGNED NULL,
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`    DATETIME NULL,
    PRIMARY KEY (`id`),
    KEY `idx_entity` (`entity_type`, `entity_id`),
    KEY `idx_created_by` (`created_by`),
    CONSTRAINT `fk_core_notes_created_by`
        FOREIGN KEY (`created_by`) REFERENCES `core_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Feature Flags ----------

CREATE TABLE IF NOT EXISTS `core_feature_flags` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `flag_key`      VARCHAR(80) NOT NULL,
    `name`          VARCHAR(150) NOT NULL,
    `description`   TEXT NULL,
    `is_enabled`    TINYINT(1) NOT NULL DEFAULT 0,
    `module_key`    VARCHAR(40) NULL,
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_flag_key` (`flag_key`),
    KEY `idx_module` (`module_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Webhooks ----------

CREATE TABLE IF NOT EXISTS `core_webhooks` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`            VARCHAR(150) NOT NULL,
    `url`             VARCHAR(500) NOT NULL,
    `secret_encrypted` VARBINARY(500) NULL,
    `event_scope`     JSON NULL,
    `is_active`       TINYINT(1) NOT NULL DEFAULT 1,
    `last_fired_at`   DATETIME NULL,
    `last_status`     SMALLINT UNSIGNED NULL,
    `failure_count`   INT UNSIGNED NOT NULL DEFAULT 0,
    `created_by`      INT UNSIGNED NULL,
    `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`      DATETIME NULL,
    PRIMARY KEY (`id`),
    KEY `idx_active` (`is_active`, `deleted_at`),
    CONSTRAINT `fk_core_webhooks_created_by`
        FOREIGN KEY (`created_by`) REFERENCES `core_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `core_webhook_deliveries` (
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `webhook_id`      INT UNSIGNED NOT NULL,
    `event_type`      VARCHAR(80) NOT NULL,
    `payload`         JSON NULL,
    `response_status` SMALLINT UNSIGNED NULL,
    `response_body`   TEXT NULL,
    `duration_ms`     INT UNSIGNED NULL,
    `attempted_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `succeeded`       TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `idx_webhook_time` (`webhook_id`, `attempted_at`),
    KEY `idx_event` (`event_type`),
    CONSTRAINT `fk_core_wd_webhook`
        FOREIGN KEY (`webhook_id`) REFERENCES `core_webhooks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Email Templates ----------

CREATE TABLE IF NOT EXISTS `core_email_templates` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `template_key` VARCHAR(80) NOT NULL,
    `locale`     VARCHAR(10) NOT NULL DEFAULT 'en',
    `subject`    VARCHAR(500) NOT NULL,
    `body_html`  MEDIUMTEXT NULL,
    `body_text`  MEDIUMTEXT NULL,
    `variables`  JSON NULL,
    `is_active`  TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_template_locale` (`template_key`, `locale`),
    KEY `idx_template_key` (`template_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- GDPR / DSAR / Consent ----------

CREATE TABLE IF NOT EXISTS `core_data_requests` (
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `request_type`    ENUM('access','erasure','rectification','portability','restriction') NOT NULL,
    `subject_type`    VARCHAR(60) NOT NULL,
    `subject_id`      INT UNSIGNED NOT NULL,
    `requested_by`    INT UNSIGNED NULL,
    `requested_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `fulfilled_at`    DATETIME NULL,
    `fulfilled_by`    INT UNSIGNED NULL,
    `status`          ENUM('pending','in_progress','fulfilled','rejected') NOT NULL DEFAULT 'pending',
    `rejection_reason` TEXT NULL,
    `output_path`     VARCHAR(500) NULL,
    PRIMARY KEY (`id`),
    KEY `idx_subject` (`subject_type`, `subject_id`),
    KEY `idx_status` (`status`),
    CONSTRAINT `fk_core_dr_requested_by`
        FOREIGN KEY (`requested_by`) REFERENCES `core_users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_core_dr_fulfilled_by`
        FOREIGN KEY (`fulfilled_by`) REFERENCES `core_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `core_consent_log` (
    `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `subject_type`   VARCHAR(60) NOT NULL,
    `subject_id`     INT UNSIGNED NOT NULL,
    `purpose`        VARCHAR(80) NOT NULL,
    `consent_given`  TINYINT(1) NOT NULL,
    `source`         VARCHAR(100) NULL,
    `ip_address`     VARCHAR(45) NULL,
    `recorded_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_subject` (`subject_type`, `subject_id`),
    KEY `idx_purpose` (`purpose`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Login History ----------

CREATE TABLE IF NOT EXISTS `core_login_history` (
    `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`        INT UNSIGNED NULL,
    `email_attempted` VARCHAR(190) NULL,
    `success`        TINYINT(1) NOT NULL,
    `ip_address`     VARCHAR(45) NULL,
    `user_agent`     VARCHAR(255) NULL,
    `fail_reason`    VARCHAR(80) NULL,
    `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user_time` (`user_id`, `created_at`),
    KEY `idx_email_time` (`email_attempted`, `created_at`),
    KEY `idx_success` (`success`),
    CONSTRAINT `fk_core_lh_user`
        FOREIGN KEY (`user_id`) REFERENCES `core_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- User-level Permission Overrides ----------

CREATE TABLE IF NOT EXISTS `core_user_permissions` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`        INT UNSIGNED NOT NULL,
    `permission_id`  INT UNSIGNED NOT NULL,
    `granted_by`     INT UNSIGNED NULL,
    `granted_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_user_permission` (`user_id`, `permission_id`),
    CONSTRAINT `fk_core_up_user`
        FOREIGN KEY (`user_id`) REFERENCES `core_users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_core_up_permission`
        FOREIGN KEY (`permission_id`) REFERENCES `core_permissions` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_core_up_granted_by`
        FOREIGN KEY (`granted_by`) REFERENCES `core_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Workflow Engine (tables only, engine built later) ----------

CREATE TABLE IF NOT EXISTS `core_workflow_definitions` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `workflow_key`  VARCHAR(80) NOT NULL,
    `name`          VARCHAR(150) NOT NULL,
    `entity_type`   VARCHAR(60) NOT NULL,
    `description`   TEXT NULL,
    `is_active`     TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_workflow_key` (`workflow_key`),
    KEY `idx_entity_type` (`entity_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `core_workflow_states` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `workflow_id`     INT UNSIGNED NOT NULL,
    `state_key`       VARCHAR(60) NOT NULL,
    `label`           VARCHAR(120) NOT NULL,
    `sort_order`      INT NOT NULL DEFAULT 0,
    `is_initial`      TINYINT(1) NOT NULL DEFAULT 0,
    `is_terminal`     TINYINT(1) NOT NULL DEFAULT 0,
    `color`           VARCHAR(20) NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_workflow_state` (`workflow_id`, `state_key`),
    CONSTRAINT `fk_core_ws_workflow`
        FOREIGN KEY (`workflow_id`) REFERENCES `core_workflow_definitions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `core_workflow_transitions` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `workflow_id`    INT UNSIGNED NOT NULL,
    `from_state_id`  INT UNSIGNED NOT NULL,
    `to_state_id`    INT UNSIGNED NOT NULL,
    `requires_permission` VARCHAR(120) NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_transition` (`workflow_id`, `from_state_id`, `to_state_id`),
    CONSTRAINT `fk_core_wt_workflow`
        FOREIGN KEY (`workflow_id`) REFERENCES `core_workflow_definitions` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_core_wt_from`
        FOREIGN KEY (`from_state_id`) REFERENCES `core_workflow_states` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_core_wt_to`
        FOREIGN KEY (`to_state_id`) REFERENCES `core_workflow_states` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Job History for Scheduled Tasks ----------

CREATE TABLE IF NOT EXISTS `core_scheduled_task_runs` (
    `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `task_id`        INT UNSIGNED NOT NULL,
    `started_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `finished_at`    DATETIME NULL,
    `status`         ENUM('running','success','failed') NOT NULL DEFAULT 'running',
    `duration_ms`    INT UNSIGNED NULL,
    `error_message`  TEXT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_task_time` (`task_id`, `started_at`),
    CONSTRAINT `fk_core_str_task`
        FOREIGN KEY (`task_id`) REFERENCES `core_scheduled_tasks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

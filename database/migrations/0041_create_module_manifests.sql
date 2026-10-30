-- =====================================================================
-- 0041_create_module_manifests.sql
-- Modules declare their capabilities (migrations, permissions, events).
-- This table is the extended payload for the core_modules registry.
-- =====================================================================

CREATE TABLE IF NOT EXISTS `core_module_manifests` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `module_key`      VARCHAR(40) NOT NULL,
    `version`         VARCHAR(20) NOT NULL,
    `migrations`      JSON NULL,
    `permissions`     JSON NULL,
    `events`          JSON NULL,
    `dependencies`    JSON NULL,
    `registered_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_manifest_module` (`module_key`),
    CONSTRAINT `fk_manifest_module`
        FOREIGN KEY (`module_key`) REFERENCES `core_modules` (`module_key`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 0043_create_hr_employees.sql
-- =====================================================================

CREATE TABLE IF NOT EXISTS `hr_employees` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id`      INT UNSIGNED NOT NULL,
    `person_id`      INT UNSIGNED NOT NULL,
    `candidate_id`   INT UNSIGNED NULL,
    `status`         ENUM('draft','active','terminated') NOT NULL DEFAULT 'draft',
    `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_hr_emp_tenant` (`tenant_id`),
    KEY `idx_hr_emp_person` (`person_id`),
    CONSTRAINT `fk_hr_emp_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `core_tenants` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_hr_emp_person` FOREIGN KEY (`person_id`) REFERENCES `core_persons` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

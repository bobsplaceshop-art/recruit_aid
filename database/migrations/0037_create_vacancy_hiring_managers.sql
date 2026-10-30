-- =====================================================================
-- 0037_create_vacancy_hiring_managers.sql
-- Many-to-many vacancy <-> client contacts (hiring managers).
-- The existing recruit_vacancies.hiring_manager_contact_id remains
-- as the "primary" HM (first picked) for backward compatibility with
-- existing views and reports. This pivot holds the full set.
-- =====================================================================

CREATE TABLE IF NOT EXISTS `recruit_vacancy_hiring_managers` (
    `vacancy_id`    INT UNSIGNED NOT NULL,
    `contact_id`    INT UNSIGNED NOT NULL,
    `assigned_by`   INT UNSIGNED NULL,
    `assigned_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`vacancy_id`, `contact_id`),
    KEY `idx_vhiring_contact` (`contact_id`),
    CONSTRAINT `fk_vhiring_vacancy`
        FOREIGN KEY (`vacancy_id`) REFERENCES `recruit_vacancies` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_vhiring_contact`
        FOREIGN KEY (`contact_id`) REFERENCES `recruit_client_contacts` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_vhiring_assigner`
        FOREIGN KEY (`assigned_by`) REFERENCES `core_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Backfill: existing single hiring_manager_contact_id becomes the first row
INSERT IGNORE INTO `recruit_vacancy_hiring_managers` (`vacancy_id`, `contact_id`, `assigned_by`)
SELECT `id`, `hiring_manager_contact_id`, `created_by`
FROM `recruit_vacancies`
WHERE `hiring_manager_contact_id` IS NOT NULL AND `deleted_at` IS NULL;

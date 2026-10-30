-- =====================================================================
-- 0036_create_vacancy_recruiters.sql
-- Many-to-many vacancy <-> users (recruiters).
-- The existing recruit_vacancies.recruiter_id remains the LEAD
-- recruiter (single). This pivot table holds the full set.
-- =====================================================================

CREATE TABLE IF NOT EXISTS `recruit_vacancy_recruiters` (
    `vacancy_id`   INT UNSIGNED NOT NULL,
    `user_id`      INT UNSIGNED NOT NULL,
    `role`         ENUM('lead','recruiter','researcher') NOT NULL DEFAULT 'recruiter',
    `assigned_by`  INT UNSIGNED NULL,
    `assigned_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`vacancy_id`, `user_id`),
    KEY `idx_vacrec_user` (`user_id`),
    CONSTRAINT `fk_rec_vacrec_vacancy`
        FOREIGN KEY (`vacancy_id`) REFERENCES `recruit_vacancies` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_rec_vacrec_user`
        FOREIGN KEY (`user_id`) REFERENCES `core_users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_rec_vacrec_assigner`
        FOREIGN KEY (`assigned_by`) REFERENCES `core_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Backfill: existing recruiter_id becomes the lead in the pivot
INSERT IGNORE INTO `recruit_vacancy_recruiters` (`vacancy_id`, `user_id`, `role`, `assigned_by`)
SELECT `id`, `recruiter_id`, 'lead', `created_by`
FROM `recruit_vacancies`
WHERE `recruiter_id` IS NOT NULL AND `deleted_at` IS NULL;

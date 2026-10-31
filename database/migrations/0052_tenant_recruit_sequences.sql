-- =====================================================================
-- 0052_tenant_recruit_sequences.sql
--
-- Introduce tenant-specific Recruit code sequences.
-- Existing global counters are retained for compatibility.
--
-- Each tenant's starting value is derived from its existing records.
-- GREATEST() prevents a rerun from reducing an existing counter.
-- =====================================================================

-- Applications: APP-000001
INSERT INTO `core_sequence_counters` (`name`, `last_value`)
SELECT
    CONCAT('rec_application_code_t', t.`id`),
    COALESCE(MAX(CAST(SUBSTRING_INDEX(a.`code`, '-', -1) AS UNSIGNED)), 0)
FROM `core_tenants` t
LEFT JOIN `recruit_applications` a ON a.`tenant_id` = t.`id`
GROUP BY t.`id`
ON DUPLICATE KEY UPDATE
    `last_value` = GREATEST(`last_value`, VALUES(`last_value`));

-- Candidates: CAND-000001
INSERT INTO `core_sequence_counters` (`name`, `last_value`)
SELECT
    CONCAT('rec_candidate_code_t', t.`id`),
    COALESCE(MAX(CAST(SUBSTRING_INDEX(c.`code`, '-', -1) AS UNSIGNED)), 0)
FROM `core_tenants` t
LEFT JOIN `recruit_candidates` c ON c.`tenant_id` = t.`id`
GROUP BY t.`id`
ON DUPLICATE KEY UPDATE
    `last_value` = GREATEST(`last_value`, VALUES(`last_value`));

-- Client contacts: CNT-000001
INSERT INTO `core_sequence_counters` (`name`, `last_value`)
SELECT
    CONCAT('rec_client_contact_code_t', t.`id`),
    COALESCE(MAX(CAST(SUBSTRING_INDEX(c.`code`, '-', -1) AS UNSIGNED)), 0)
FROM `core_tenants` t
LEFT JOIN `recruit_client_contacts` c ON c.`tenant_id` = t.`id`
GROUP BY t.`id`
ON DUPLICATE KEY UPDATE
    `last_value` = GREATEST(`last_value`, VALUES(`last_value`));

-- Client organisations: ORG-000001
INSERT INTO `core_sequence_counters` (`name`, `last_value`)
SELECT
    CONCAT('rec_client_org_code_t', t.`id`),
    COALESCE(MAX(CAST(SUBSTRING_INDEX(o.`code`, '-', -1) AS UNSIGNED)), 0)
FROM `core_tenants` t
LEFT JOIN `recruit_client_organisations` o ON o.`tenant_id` = t.`id`
GROUP BY t.`id`
ON DUPLICATE KEY UPDATE
    `last_value` = GREATEST(`last_value`, VALUES(`last_value`));

-- Engagements: ENG-000001
INSERT INTO `core_sequence_counters` (`name`, `last_value`)
SELECT
    CONCAT('rec_engagement_code_t', t.`id`),
    COALESCE(MAX(CAST(SUBSTRING_INDEX(e.`code`, '-', -1) AS UNSIGNED)), 0)
FROM `core_tenants` t
LEFT JOIN `recruit_engagements` e ON e.`tenant_id` = t.`id`
GROUP BY t.`id`
ON DUPLICATE KEY UPDATE
    `last_value` = GREATEST(`last_value`, VALUES(`last_value`));

-- Interviews: INT-000001
INSERT INTO `core_sequence_counters` (`name`, `last_value`)
SELECT
    CONCAT('rec_interview_code_t', t.`id`),
    COALESCE(MAX(CAST(SUBSTRING_INDEX(i.`code`, '-', -1) AS UNSIGNED)), 0)
FROM `core_tenants` t
LEFT JOIN `recruit_interviews` i ON i.`tenant_id` = t.`id`
GROUP BY t.`id`
ON DUPLICATE KEY UPDATE
    `last_value` = GREATEST(`last_value`, VALUES(`last_value`));

-- Offers: OFF-000001
INSERT INTO `core_sequence_counters` (`name`, `last_value`)
SELECT
    CONCAT('rec_offer_code_t', t.`id`),
    COALESCE(MAX(CAST(SUBSTRING_INDEX(o.`code`, '-', -1) AS UNSIGNED)), 0)
FROM `core_tenants` t
LEFT JOIN `recruit_offers` o ON o.`tenant_id` = t.`id`
GROUP BY t.`id`
ON DUPLICATE KEY UPDATE
    `last_value` = GREATEST(`last_value`, VALUES(`last_value`));

-- Vacancies: VAC-000001
INSERT INTO `core_sequence_counters` (`name`, `last_value`)
SELECT
    CONCAT('rec_vacancy_code_t', t.`id`),
    COALESCE(MAX(CAST(SUBSTRING_INDEX(v.`code`, '-', -1) AS UNSIGNED)), 0)
FROM `core_tenants` t
LEFT JOIN `recruit_vacancies` v ON v.`tenant_id` = t.`id`
GROUP BY t.`id`
ON DUPLICATE KEY UPDATE
    `last_value` = GREATEST(`last_value`, VALUES(`last_value`));

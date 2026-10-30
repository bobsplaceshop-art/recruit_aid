-- =====================================================================
-- 0039_rename_client_org_and_add_tenant_id.sql (corrected v2)
--
-- Renames organisation_id -> client_organisation_id on the four
-- tables that have it, and adds tenant_id to all 25 recruit_* tables.
--
-- No "AFTER id" clauses: some tables (e.g. recruitment join tables)
-- use composite primary keys and have no `id` column. Column position
-- doesn't matter functionally.
-- =====================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- =====================================================================
-- PART 1: Rename organisation_id -> client_organisation_id
-- =====================================================================

ALTER TABLE `recruit_client_contacts` DROP FOREIGN KEY `fk_rec_contact_org`;
ALTER TABLE `recruit_client_contacts` DROP INDEX `idx_org`;
ALTER TABLE `recruit_client_contacts`
    CHANGE COLUMN `organisation_id` `client_organisation_id` INT UNSIGNED NOT NULL;
ALTER TABLE `recruit_client_contacts`
    ADD KEY `idx_client_org` (`client_organisation_id`),
    ADD CONSTRAINT `fk_rec_contact_client_org`
        FOREIGN KEY (`client_organisation_id`)
        REFERENCES `recruit_client_organisations` (`id`) ON DELETE CASCADE;

ALTER TABLE `recruit_engagements` DROP FOREIGN KEY `fk_rec_eng_org`;
ALTER TABLE `recruit_engagements` DROP INDEX `idx_org`;
ALTER TABLE `recruit_engagements`
    CHANGE COLUMN `organisation_id` `client_organisation_id` INT UNSIGNED NOT NULL;
ALTER TABLE `recruit_engagements`
    ADD KEY `idx_client_org` (`client_organisation_id`),
    ADD CONSTRAINT `fk_rec_eng_client_org`
        FOREIGN KEY (`client_organisation_id`)
        REFERENCES `recruit_client_organisations` (`id`) ON DELETE CASCADE;

ALTER TABLE `recruit_vacancies` DROP FOREIGN KEY `fk_rec_vac_org`;
ALTER TABLE `recruit_vacancies` DROP INDEX `idx_org`;
ALTER TABLE `recruit_vacancies`
    CHANGE COLUMN `organisation_id` `client_organisation_id` INT UNSIGNED NOT NULL;
ALTER TABLE `recruit_vacancies`
    ADD KEY `idx_client_org` (`client_organisation_id`),
    ADD CONSTRAINT `fk_rec_vac_client_org`
        FOREIGN KEY (`client_organisation_id`)
        REFERENCES `recruit_client_organisations` (`id`) ON DELETE CASCADE;

ALTER TABLE `recruit_pipeline_stages`
    DROP INDEX `idx_org`,
    DROP INDEX `idx_sort`,
    DROP INDEX `uniq_org_slug`;
ALTER TABLE `recruit_pipeline_stages`
    CHANGE COLUMN `organisation_id` `client_organisation_id` INT UNSIGNED NULL;
ALTER TABLE `recruit_pipeline_stages`
    ADD KEY `idx_client_org` (`client_organisation_id`),
    ADD KEY `idx_client_sort` (`client_organisation_id`, `sort_order`),
    ADD UNIQUE KEY `uniq_client_org_slug` (`client_organisation_id`, `key_slug`);

-- =====================================================================
-- PART 2: Add tenant_id to all 25 recruit_* tables
-- =====================================================================
-- No AFTER id clauses. Column added at the end; backfilled to 1;
-- made NOT NULL; indexed; FK to core_tenants.

ALTER TABLE `recruit_client_organisations` ADD COLUMN `tenant_id` INT UNSIGNED NULL;
UPDATE `recruit_client_organisations` SET `tenant_id` = 1 WHERE `tenant_id` IS NULL;
ALTER TABLE `recruit_client_organisations` MODIFY COLUMN `tenant_id` INT UNSIGNED NOT NULL;
ALTER TABLE `recruit_client_organisations`
    ADD KEY `idx_tenant` (`tenant_id`),
    ADD CONSTRAINT `fk_rec_org_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `core_tenants` (`id`) ON DELETE RESTRICT;

ALTER TABLE `recruit_pipeline_stages` ADD COLUMN `tenant_id` INT UNSIGNED NULL;
UPDATE `recruit_pipeline_stages` SET `tenant_id` = 1 WHERE `tenant_id` IS NULL;
ALTER TABLE `recruit_pipeline_stages` MODIFY COLUMN `tenant_id` INT UNSIGNED NOT NULL;
ALTER TABLE `recruit_pipeline_stages`
    ADD KEY `idx_tenant` (`tenant_id`),
    ADD CONSTRAINT `fk_rec_ps_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `core_tenants` (`id`) ON DELETE RESTRICT;

ALTER TABLE `recruit_client_contacts` ADD COLUMN `tenant_id` INT UNSIGNED NULL;
UPDATE `recruit_client_contacts` SET `tenant_id` = 1 WHERE `tenant_id` IS NULL;
ALTER TABLE `recruit_client_contacts` MODIFY COLUMN `tenant_id` INT UNSIGNED NOT NULL;
ALTER TABLE `recruit_client_contacts`
    ADD KEY `idx_tenant` (`tenant_id`),
    ADD CONSTRAINT `fk_rec_contact_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `core_tenants` (`id`) ON DELETE RESTRICT;

ALTER TABLE `recruit_engagement_packages` ADD COLUMN `tenant_id` INT UNSIGNED NULL;
UPDATE `recruit_engagement_packages` SET `tenant_id` = 1 WHERE `tenant_id` IS NULL;
ALTER TABLE `recruit_engagement_packages` MODIFY COLUMN `tenant_id` INT UNSIGNED NOT NULL;
ALTER TABLE `recruit_engagement_packages`
    ADD KEY `idx_tenant` (`tenant_id`),
    ADD CONSTRAINT `fk_rec_engpkg_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `core_tenants` (`id`) ON DELETE RESTRICT;

ALTER TABLE `recruit_engagements` ADD COLUMN `tenant_id` INT UNSIGNED NULL;
UPDATE `recruit_engagements` SET `tenant_id` = 1 WHERE `tenant_id` IS NULL;
ALTER TABLE `recruit_engagements` MODIFY COLUMN `tenant_id` INT UNSIGNED NOT NULL;
ALTER TABLE `recruit_engagements`
    ADD KEY `idx_tenant` (`tenant_id`),
    ADD CONSTRAINT `fk_rec_eng_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `core_tenants` (`id`) ON DELETE RESTRICT;

ALTER TABLE `recruit_engagement_recruiters` ADD COLUMN `tenant_id` INT UNSIGNED NULL;
UPDATE `recruit_engagement_recruiters` SET `tenant_id` = 1 WHERE `tenant_id` IS NULL;
ALTER TABLE `recruit_engagement_recruiters` MODIFY COLUMN `tenant_id` INT UNSIGNED NOT NULL;
ALTER TABLE `recruit_engagement_recruiters`
    ADD KEY `idx_tenant` (`tenant_id`),
    ADD CONSTRAINT `fk_rec_engrec_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `core_tenants` (`id`) ON DELETE RESTRICT;

ALTER TABLE `recruit_vacancies` ADD COLUMN `tenant_id` INT UNSIGNED NULL;
UPDATE `recruit_vacancies` SET `tenant_id` = 1 WHERE `tenant_id` IS NULL;
ALTER TABLE `recruit_vacancies` MODIFY COLUMN `tenant_id` INT UNSIGNED NOT NULL;
ALTER TABLE `recruit_vacancies`
    ADD KEY `idx_tenant` (`tenant_id`),
    ADD CONSTRAINT `fk_rec_vac_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `core_tenants` (`id`) ON DELETE RESTRICT;

ALTER TABLE `recruit_vacancy_stages` ADD COLUMN `tenant_id` INT UNSIGNED NULL;
UPDATE `recruit_vacancy_stages` SET `tenant_id` = 1 WHERE `tenant_id` IS NULL;
ALTER TABLE `recruit_vacancy_stages` MODIFY COLUMN `tenant_id` INT UNSIGNED NOT NULL;
ALTER TABLE `recruit_vacancy_stages`
    ADD KEY `idx_tenant` (`tenant_id`),
    ADD CONSTRAINT `fk_rec_vs_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `core_tenants` (`id`) ON DELETE RESTRICT;

ALTER TABLE `recruit_vacancy_requirements` ADD COLUMN `tenant_id` INT UNSIGNED NULL;
UPDATE `recruit_vacancy_requirements` SET `tenant_id` = 1 WHERE `tenant_id` IS NULL;
ALTER TABLE `recruit_vacancy_requirements` MODIFY COLUMN `tenant_id` INT UNSIGNED NOT NULL;
ALTER TABLE `recruit_vacancy_requirements`
    ADD KEY `idx_tenant` (`tenant_id`),
    ADD CONSTRAINT `fk_rec_vr_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `core_tenants` (`id`) ON DELETE RESTRICT;

ALTER TABLE `recruit_engagement_assignments` ADD COLUMN `tenant_id` INT UNSIGNED NULL;
UPDATE `recruit_engagement_assignments` SET `tenant_id` = 1 WHERE `tenant_id` IS NULL;
ALTER TABLE `recruit_engagement_assignments` MODIFY COLUMN `tenant_id` INT UNSIGNED NOT NULL;
ALTER TABLE `recruit_engagement_assignments`
    ADD KEY `idx_tenant` (`tenant_id`),
    ADD CONSTRAINT `fk_rec_ea_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `core_tenants` (`id`) ON DELETE RESTRICT;

ALTER TABLE `recruit_candidates` ADD COLUMN `tenant_id` INT UNSIGNED NULL;
UPDATE `recruit_candidates` SET `tenant_id` = 1 WHERE `tenant_id` IS NULL;
ALTER TABLE `recruit_candidates` MODIFY COLUMN `tenant_id` INT UNSIGNED NOT NULL;
ALTER TABLE `recruit_candidates`
    ADD KEY `idx_tenant` (`tenant_id`),
    ADD CONSTRAINT `fk_rec_cand_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `core_tenants` (`id`) ON DELETE RESTRICT;

ALTER TABLE `recruit_candidate_skills` ADD COLUMN `tenant_id` INT UNSIGNED NULL;
UPDATE `recruit_candidate_skills` SET `tenant_id` = 1 WHERE `tenant_id` IS NULL;
ALTER TABLE `recruit_candidate_skills` MODIFY COLUMN `tenant_id` INT UNSIGNED NOT NULL;
ALTER TABLE `recruit_candidate_skills`
    ADD KEY `idx_tenant` (`tenant_id`),
    ADD CONSTRAINT `fk_rec_cs_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `core_tenants` (`id`) ON DELETE RESTRICT;

ALTER TABLE `recruit_candidate_experience` ADD COLUMN `tenant_id` INT UNSIGNED NULL;
UPDATE `recruit_candidate_experience` SET `tenant_id` = 1 WHERE `tenant_id` IS NULL;
ALTER TABLE `recruit_candidate_experience` MODIFY COLUMN `tenant_id` INT UNSIGNED NOT NULL;
ALTER TABLE `recruit_candidate_experience`
    ADD KEY `idx_tenant` (`tenant_id`),
    ADD CONSTRAINT `fk_rec_ce_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `core_tenants` (`id`) ON DELETE RESTRICT;

ALTER TABLE `recruit_candidate_education` ADD COLUMN `tenant_id` INT UNSIGNED NULL;
UPDATE `recruit_candidate_education` SET `tenant_id` = 1 WHERE `tenant_id` IS NULL;
ALTER TABLE `recruit_candidate_education` MODIFY COLUMN `tenant_id` INT UNSIGNED NOT NULL;
ALTER TABLE `recruit_candidate_education`
    ADD KEY `idx_tenant` (`tenant_id`),
    ADD CONSTRAINT `fk_rec_ced_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `core_tenants` (`id`) ON DELETE RESTRICT;

ALTER TABLE `recruit_candidate_tags` ADD COLUMN `tenant_id` INT UNSIGNED NULL;
UPDATE `recruit_candidate_tags` SET `tenant_id` = 1 WHERE `tenant_id` IS NULL;
ALTER TABLE `recruit_candidate_tags` MODIFY COLUMN `tenant_id` INT UNSIGNED NOT NULL;
ALTER TABLE `recruit_candidate_tags`
    ADD KEY `idx_tenant` (`tenant_id`),
    ADD CONSTRAINT `fk_rec_ct_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `core_tenants` (`id`) ON DELETE RESTRICT;

ALTER TABLE `recruit_applications` ADD COLUMN `tenant_id` INT UNSIGNED NULL;
UPDATE `recruit_applications` SET `tenant_id` = 1 WHERE `tenant_id` IS NULL;
ALTER TABLE `recruit_applications` MODIFY COLUMN `tenant_id` INT UNSIGNED NOT NULL;
ALTER TABLE `recruit_applications`
    ADD KEY `idx_tenant` (`tenant_id`),
    ADD CONSTRAINT `fk_rec_app_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `core_tenants` (`id`) ON DELETE RESTRICT;

ALTER TABLE `recruit_application_stage_history` ADD COLUMN `tenant_id` INT UNSIGNED NULL;
UPDATE `recruit_application_stage_history` SET `tenant_id` = 1 WHERE `tenant_id` IS NULL;
ALTER TABLE `recruit_application_stage_history` MODIFY COLUMN `tenant_id` INT UNSIGNED NOT NULL;
ALTER TABLE `recruit_application_stage_history`
    ADD KEY `idx_tenant` (`tenant_id`),
    ADD CONSTRAINT `fk_rec_ash_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `core_tenants` (`id`) ON DELETE RESTRICT;

ALTER TABLE `recruit_interviews` ADD COLUMN `tenant_id` INT UNSIGNED NULL;
UPDATE `recruit_interviews` SET `tenant_id` = 1 WHERE `tenant_id` IS NULL;
ALTER TABLE `recruit_interviews` MODIFY COLUMN `tenant_id` INT UNSIGNED NOT NULL;
ALTER TABLE `recruit_interviews`
    ADD KEY `idx_tenant` (`tenant_id`),
    ADD CONSTRAINT `fk_rec_int_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `core_tenants` (`id`) ON DELETE RESTRICT;

ALTER TABLE `recruit_interview_participants` ADD COLUMN `tenant_id` INT UNSIGNED NULL;
UPDATE `recruit_interview_participants` SET `tenant_id` = 1 WHERE `tenant_id` IS NULL;
ALTER TABLE `recruit_interview_participants` MODIFY COLUMN `tenant_id` INT UNSIGNED NOT NULL;
ALTER TABLE `recruit_interview_participants`
    ADD KEY `idx_tenant` (`tenant_id`),
    ADD CONSTRAINT `fk_rec_intp_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `core_tenants` (`id`) ON DELETE RESTRICT;

ALTER TABLE `recruit_scorecards` ADD COLUMN `tenant_id` INT UNSIGNED NULL;
UPDATE `recruit_scorecards` SET `tenant_id` = 1 WHERE `tenant_id` IS NULL;
ALTER TABLE `recruit_scorecards` MODIFY COLUMN `tenant_id` INT UNSIGNED NOT NULL;
ALTER TABLE `recruit_scorecards`
    ADD KEY `idx_tenant` (`tenant_id`),
    ADD CONSTRAINT `fk_rec_sc_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `core_tenants` (`id`) ON DELETE RESTRICT;

ALTER TABLE `recruit_scorecard_attributes` ADD COLUMN `tenant_id` INT UNSIGNED NULL;
UPDATE `recruit_scorecard_attributes` SET `tenant_id` = 1 WHERE `tenant_id` IS NULL;
ALTER TABLE `recruit_scorecard_attributes` MODIFY COLUMN `tenant_id` INT UNSIGNED NOT NULL;
ALTER TABLE `recruit_scorecard_attributes`
    ADD KEY `idx_tenant` (`tenant_id`),
    ADD CONSTRAINT `fk_rec_sca_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `core_tenants` (`id`) ON DELETE RESTRICT;

ALTER TABLE `recruit_contact_roles` ADD COLUMN `tenant_id` INT UNSIGNED NULL;
UPDATE `recruit_contact_roles` SET `tenant_id` = 1 WHERE `tenant_id` IS NULL;
ALTER TABLE `recruit_contact_roles` MODIFY COLUMN `tenant_id` INT UNSIGNED NOT NULL;
ALTER TABLE `recruit_contact_roles`
    ADD KEY `idx_tenant` (`tenant_id`),
    ADD CONSTRAINT `fk_rec_cr_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `core_tenants` (`id`) ON DELETE RESTRICT;

ALTER TABLE `recruit_offers` ADD COLUMN `tenant_id` INT UNSIGNED NULL;
UPDATE `recruit_offers` SET `tenant_id` = 1 WHERE `tenant_id` IS NULL;
ALTER TABLE `recruit_offers` MODIFY COLUMN `tenant_id` INT UNSIGNED NOT NULL;
ALTER TABLE `recruit_offers`
    ADD KEY `idx_tenant` (`tenant_id`),
    ADD CONSTRAINT `fk_rec_off_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `core_tenants` (`id`) ON DELETE RESTRICT;

ALTER TABLE `recruit_offer_history` ADD COLUMN `tenant_id` INT UNSIGNED NULL;
UPDATE `recruit_offer_history` SET `tenant_id` = 1 WHERE `tenant_id` IS NULL;
ALTER TABLE `recruit_offer_history` MODIFY COLUMN `tenant_id` INT UNSIGNED NOT NULL;
ALTER TABLE `recruit_offer_history`
    ADD KEY `idx_tenant` (`tenant_id`),
    ADD CONSTRAINT `fk_rec_oh_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `core_tenants` (`id`) ON DELETE RESTRICT;

ALTER TABLE `recruit_vacancy_recruiters` ADD COLUMN `tenant_id` INT UNSIGNED NULL;
UPDATE `recruit_vacancy_recruiters` SET `tenant_id` = 1 WHERE `tenant_id` IS NULL;
ALTER TABLE `recruit_vacancy_recruiters` MODIFY COLUMN `tenant_id` INT UNSIGNED NOT NULL;
ALTER TABLE `recruit_vacancy_recruiters`
    ADD KEY `idx_tenant` (`tenant_id`),
    ADD CONSTRAINT `fk_rec_vr2_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `core_tenants` (`id`) ON DELETE RESTRICT;

ALTER TABLE `recruit_vacancy_hiring_managers` ADD COLUMN `tenant_id` INT UNSIGNED NULL;
UPDATE `recruit_vacancy_hiring_managers` SET `tenant_id` = 1 WHERE `tenant_id` IS NULL;
ALTER TABLE `recruit_vacancy_hiring_managers` MODIFY COLUMN `tenant_id` INT UNSIGNED NOT NULL;
ALTER TABLE `recruit_vacancy_hiring_managers`
    ADD KEY `idx_tenant` (`tenant_id`),
    ADD CONSTRAINT `fk_rec_vhm_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `core_tenants` (`id`) ON DELETE RESTRICT;

SET FOREIGN_KEY_CHECKS = 1;

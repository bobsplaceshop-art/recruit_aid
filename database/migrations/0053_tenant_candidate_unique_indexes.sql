-- =====================================================================
-- 0053_tenant_candidate_unique_indexes.sql
--
-- Replace global candidate uniqueness constraints with tenant-scoped
-- constraints.
-- =====================================================================

ALTER TABLE `recruit_candidates`
    DROP INDEX `uniq_rec_cand_code`,
    DROP INDEX `uniq_rec_cand_email`,
    DROP INDEX `uniq_rec_cand_linkedin`,
    ADD UNIQUE KEY `uniq_rec_cand_tenant_code` (`tenant_id`, `code`),
    ADD UNIQUE KEY `uniq_rec_cand_tenant_email` (`tenant_id`, `email`),
    ADD UNIQUE KEY `uniq_rec_cand_tenant_linkedin` (`tenant_id`, `linkedin_url`);

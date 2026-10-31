-- Replace global application uniqueness with tenant-scoped constraints.
ALTER TABLE `recruit_applications`
    DROP INDEX `uniq_rec_app_code`,
    DROP INDEX `uniq_rec_app_cand_vac`,
    ADD UNIQUE KEY `uniq_rec_app_tenant_code` (`tenant_id`, `code`),
    ADD UNIQUE KEY `uniq_rec_app_tenant_cand_vac`
        (`tenant_id`, `candidate_id`, `vacancy_id`);

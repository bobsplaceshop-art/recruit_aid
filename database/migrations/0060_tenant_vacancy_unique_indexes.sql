-- Replace global vacancy code uniqueness with tenant-scoped uniqueness.
-- Keep public_slug globally unique.
ALTER TABLE `recruit_vacancies`
    DROP INDEX `uniq_code`,
    ADD UNIQUE KEY `uniq_vac_tenant_code` (`tenant_id`, `code`);

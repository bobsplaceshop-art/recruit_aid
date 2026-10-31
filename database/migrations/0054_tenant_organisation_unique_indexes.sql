-- =====================================================================
-- 0054_tenant_organisation_unique_indexes.sql
--
-- Replace global organisation code uniqueness with tenant-scoped
-- uniqueness.
-- =====================================================================

ALTER TABLE `recruit_client_organisations`
    DROP INDEX `uniq_code`,
    ADD UNIQUE KEY `uniq_org_tenant_code` (`tenant_id`, `code`);

-- =====================================================================
-- 0055_tenant_contact_unique_indexes.sql
--
-- Replace global contact code uniqueness with tenant-scoped
-- uniqueness.
-- =====================================================================

ALTER TABLE `recruit_client_contacts`
    DROP INDEX `uniq_code`,
    ADD UNIQUE KEY `uniq_contact_tenant_code` (`tenant_id`, `code`);

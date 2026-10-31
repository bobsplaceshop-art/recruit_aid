-- =====================================================================
-- 0056_tenant_engagement_unique_indexes.sql
--
-- Replace global engagement code uniqueness with tenant-scoped
-- uniqueness.
-- =====================================================================

ALTER TABLE `recruit_engagements`
    DROP INDEX `uniq_code`,
    ADD UNIQUE KEY `uniq_engagement_tenant_code` (`tenant_id`, `code`);

-- Replace global offer code uniqueness with tenant-scoped uniqueness.
ALTER TABLE `recruit_offers`
    DROP INDEX `uniq_rec_off_code`,
    ADD UNIQUE KEY `uniq_rec_off_tenant_code` (`tenant_id`, `code`);

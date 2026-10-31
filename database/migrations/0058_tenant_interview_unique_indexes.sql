-- Replace global interview code uniqueness with tenant-scoped uniqueness.
ALTER TABLE `recruit_interviews`
    DROP INDEX `uniq_rec_int_code`,
    ADD UNIQUE KEY `uniq_rec_int_tenant_code` (`tenant_id`, `code`);

-- 0027_demote_non_super_admin_roles.sql
--
-- Only `super_admin` should be a system role (undeletable).
-- All other seeded roles (admin, recruiter, researcher, etc.) are
-- starting templates — admins can rename, delete, or replace them.
--
-- super_admin stays system because User::isSuperAdmin() bypasses all
-- permission checks; deleting it would lock out every admin.
--
-- This migration fixes existing installs that were seeded before the
-- seeder was corrected. New installs get the right values directly
-- from 0001_seed_roles_and_permissions.sql.

UPDATE `core_roles` SET `is_system` = 0 WHERE `slug` != 'super_admin';

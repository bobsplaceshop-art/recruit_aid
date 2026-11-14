-- 0073_fix_core_namespace_scope.sql
--
-- Migration 0044 established the invariant: permissions with module='core'
-- are scope='global', and permissions with module!='core' are scope='app'.
-- Permissions seeded AFTER 0044 with module='core' were not caught by
-- 0044's one-shot UPDATE. This migration re-applies the invariant.
--
-- Rule 3b (RBAC-ARCHITECTURE.md §5.1) relies on this invariant: it grants
-- only scope='app' permissions to platform owners. Any 'core.*' permission
-- with scope='app' would be incorrectly granted by Rule 3b.

UPDATE `core_permissions`
SET `scope` = 'global', `module_key` = NULL
WHERE `module` = 'core' AND `scope` = 'app';

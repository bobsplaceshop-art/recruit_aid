-- 0074_fix_core_namespace_scope_v2.sql
--
-- Migration 0073 attempted to set scope='global' for module='core',
-- but the actual data has module='core.settings', 'core.users', etc.
-- (i.e., module = full slug prefix, never bare 'core'). Migration 0073
-- matched zero rows and had no effect.
--
-- This migration uses the correct predicate: any permission whose slug
-- begins with 'core.' is a platform-domain permission and must be
-- scope='global'. This is the invariant Rule 3b (RBAC-ARCHITECTURE.md
-- §5.1) relies on — Rule 3b grants only scope='app' permissions to
-- platform owners.

UPDATE `core_permissions`
SET `scope` = 'global', `module_key` = NULL
WHERE `slug` LIKE 'core.%' AND `scope` = 'app';

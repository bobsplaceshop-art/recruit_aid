-- 0065_add_permission_lists_to_support_delegations.sql
--
-- Adds the enforcement columns that turn a support delegation from an
-- advisory grant into an enforceable ceiling:
--
--   * scope (existing): read_only | read_write | full
--     → security ceiling; a read_only delegation cannot approve or
--       delete, regardless of what the JSON says.
--
--   * module_key (new): the delegation applies to one module. A support
--     engineer for Recruit is not automatically a support engineer for HR.
--
--   * allowed_permissions_json (new): explicit allowlist, materialized at
--     grant time by SupportDelegationService from the scope ceiling. If
--     empty, the scope ceiling applies alone. If populated, the requested
--     permission must appear here.
--
--   * denied_permissions_json (new): explicit denylist. Evaluated BEFORE
--     the allowlist. A permission in this list is denied even if it is in
--     the allowlist and within the scope ceiling.
--
-- Evaluation order in AuthorizationService (for a support delegation):
--   1. scope ceiling: action suffix must be compatible with scope
--   2. explicit denial: if in denied_permissions_json → DENY
--   3. explicit allowlist: if allowed_permissions_json is populated and
--      the permission is not in it → DENY
--   4. otherwise → ALLOW (subject to tenant/application checks already
--      performed by Rule 2 and Rule 4)
--
-- Matches core_admin_delegations conventions:
--   * JSON native type
--   * allowed NOT NULL, denied NULL
--   * module_key FK to core_modules ON DELETE CASCADE
--
-- Table is empty (COUNT(*) = 0 before migration), so NOT NULL columns
-- can be added directly without a backfill step.

ALTER TABLE `core_support_delegations`
    ADD COLUMN `module_key` VARCHAR(40) NOT NULL AFTER `tenant_id`,
    ADD COLUMN `allowed_permissions_json` JSON NOT NULL AFTER `scope`,
    ADD COLUMN `denied_permissions_json` JSON NULL AFTER `allowed_permissions_json`,
    ADD KEY `idx_support_user_module` (`user_id`, `module_key`, `revocation_state`),
    ADD CONSTRAINT `fk_support_module`
        FOREIGN KEY (`module_key`) REFERENCES `core_modules` (`module_key`)
        ON DELETE CASCADE;

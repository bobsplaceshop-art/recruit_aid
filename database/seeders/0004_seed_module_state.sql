-- ============================================================
-- Corporate Aid — seed module state
-- ============================================================
-- Enables the modules shipped with this installation. A module
-- can be present (in core_modules) but disabled here.
-- ============================================================

INSERT INTO `core_module_state` (`module_key`, `is_enabled`) VALUES
    ('recruit', 1)
ON DUPLICATE KEY UPDATE `is_enabled` = VALUES(`is_enabled`);

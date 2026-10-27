-- ============================================================
-- Corporate Aid — seed modules
-- ============================================================
-- Registers the modules this product suite knows about.
-- Currently only 'recruit' ships. Future modules (hr, crm, etc.)
-- will be added here as they are built.
-- ============================================================

INSERT INTO `core_modules` (`module_key`, `name`, `description`, `current_version`, `is_core`) VALUES
    ('recruit', 'Recruit Aid', 'Recruitment Operations & Applicant Tracking', '0.6.0-alpha', 0)
ON DUPLICATE KEY UPDATE
    `name`            = VALUES(`name`),
    `description`     = VALUES(`description`),
    `current_version` = VALUES(`current_version`);

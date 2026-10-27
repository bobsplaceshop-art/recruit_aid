-- ============================================================
-- Recruit Aid — Add rate limit settings for password confirmation
-- ============================================================

INSERT INTO `core_system_settings` (`key_slug`, `value`, `type`) VALUES
    ('security.confirm_password.max_attempts',   '3',  'int'),
    ('security.confirm_password.window_minutes', '15', 'int')
ON DUPLICATE KEY UPDATE `key_slug` = `key_slug`;

-- ============================================================
-- Recruit Aid — Seed default system settings
-- ============================================================
-- These are the out-of-the-box values. Admins can change
-- them from Admin → Settings. Each change is audited.
-- ============================================================

INSERT INTO `core_system_settings` (`key_slug`, `value`, `type`) VALUES
    -- Security: login rate limiting
    ('security.login.max_attempts',              '5',      'int'),
    ('security.login.lockout_minutes',           '15',     'int'),

    -- Security: password reset request rate limiting
    ('security.password_reset.max_attempts',     '3',      'int'),
    ('security.password_reset.window_minutes',   '60',     'int'),
    ('security.password_reset.submit_max_attempts',   '5',  'int'),
    ('security.password_reset.submit_window_minutes', '60', 'int'),

    -- Security: password policy
    ('security.password.min_length',             '12',     'int'),
    ('security.password.min_length_relaxed',     '10',     'int'),
    ('security.password.require_classes',        '3',      'int'),

    -- Security: sessions
    ('security.session.idle_timeout',            '7200',   'int'),
    ('security.session.reauth_window_minutes',   '15',     'int'),

    -- API (disabled by default)
    ('api.enabled',                              '0',      'bool'),

    -- Webhooks (disabled by default)
    ('webhooks.enabled',                         '0',      'bool')
ON DUPLICATE KEY UPDATE `key_slug` = `key_slug`;

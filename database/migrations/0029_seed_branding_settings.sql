-- 0029_seed_branding_settings.sql
--
-- Seeds branding settings with defaults matching the current hardcoded
-- values. Customers can override any of these from /admin/settings/branding
-- to make the app fully white-labeled.

INSERT IGNORE INTO `core_system_settings` (`key_slug`, `value`, `type`) VALUES
  ('branding.app_name',          'Recruit Aid',                       'string'),
  ('branding.tagline',           'Recruitment Operations Platform',   'string'),
  ('branding.logo_path',         '',                                  'string'),
  ('branding.favicon_path',      '',                                  'string'),
  ('branding.primary_color',     '#0d6efd',                           'string'),
  ('branding.client_org_name',   '',                                  'string'),
  ('branding.client_org_address','',                                  'string'),
  ('branding.client_org_phone',  '',                                  'string'),
  ('branding.client_org_email',  '',                                  'string');

-- ============================================================
-- Recruit Aid — seed roles and permissions
-- ============================================================
-- Roles and their permission assignments. Idempotent — safe to
-- re-run.
--
-- Slug naming follows docs/NAMING-CONVENTION.md section 6:
--   {module}.{entity}.{action}
-- ============================================================

INSERT INTO `core_roles` (`name`, `slug`, `description`, `is_system`) VALUES
  ('Super Administrator', 'super_admin',   'Full system access', 1),
  ('Administrator',       'admin',         'Operational administration', 1),
  ('Recruitment Manager', 'recruitment_manager', 'Manages recruiters, clients and recruitment performance', 1),
  ('Recruiter',           'recruiter',     'Manages assigned vacancies and candidates', 1),
  ('Researcher',          'researcher',    'Sourcing and candidate research', 1),
  ('Client Administrator','client_admin',  'Client-side organisation administrator', 1),
  ('Client User',         'client_user',   'Client-side reviewer', 1)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `description` = VALUES(`description`);

-- ============================================================
-- Permissions
-- ============================================================
-- Core (shared platform) permissions

INSERT INTO `core_permissions` (`slug`, `module`, `description`) VALUES
  ('core.users.view',              'core.users',           'View users'),
  ('core.users.create',            'core.users',           'Create users'),
  ('core.users.edit',              'core.users',           'Edit users'),
  ('core.users.delete',            'core.users',           'Delete users'),
  ('core.settings.manage',         'core.settings',        'Manage system settings'),
  ('core.audit.view',              'core.audit',           'View audit log'),
  ('core.documents.view',          'core.documents',       'View documents'),
  ('core.documents.upload',        'core.documents',       'Upload documents'),
  ('core.custom_fields.manage',    'core.custom_fields',   'Manage custom fields')
ON DUPLICATE KEY UPDATE `module` = VALUES(`module`), `description` = VALUES(`description`);

-- Recruit Aid module permissions

INSERT INTO `core_permissions` (`slug`, `module`, `description`) VALUES
  ('recruit.organisations.view',    'recruit.organisations', 'View organisations'),
  ('recruit.organisations.create',  'recruit.organisations', 'Create organisations'),
  ('recruit.organisations.edit',    'recruit.organisations', 'Edit organisations'),

  ('recruit.contacts.view',         'recruit.contacts',      'View contacts'),
  ('recruit.contacts.create',       'recruit.contacts',      'Create contacts'),
  ('recruit.contacts.edit',         'recruit.contacts',      'Edit contacts'),

  ('recruit.engagements.view',      'recruit.engagements',   'View engagements'),
  ('recruit.engagements.create',    'recruit.engagements',   'Create engagements'),
  ('recruit.engagements.edit',      'recruit.engagements',   'Edit engagements'),

  ('recruit.vacancies.view',        'recruit.vacancies',     'View vacancies'),
  ('recruit.vacancies.create',      'recruit.vacancies',     'Create vacancies'),
  ('recruit.vacancies.edit',        'recruit.vacancies',     'Edit vacancies'),
  ('recruit.vacancies.close',       'recruit.vacancies',     'Close vacancies'),

  ('recruit.candidates.view',       'recruit.candidates',    'View candidates'),
  ('recruit.candidates.create',     'recruit.candidates',    'Create candidates'),
  ('recruit.candidates.edit',       'recruit.candidates',    'Edit candidates'),
  ('recruit.candidates.export',     'recruit.candidates',    'Export candidates'),
  ('recruit.candidates.delete',     'recruit.candidates',    'Delete candidates'),

  ('recruit.assessments.view',      'recruit.assessments',   'View assessments'),
  ('recruit.assessments.create',    'recruit.assessments',   'Create assessments'),
  ('recruit.assessments.evaluate',  'recruit.assessments',   'Score assessments'),

  ('recruit.interviews.view',       'recruit.interviews',    'View interviews'),
  ('recruit.interviews.create',     'recruit.interviews',    'Create interviews'),
  ('recruit.interviews.edit',       'recruit.interviews',    'Edit interviews'),

  ('recruit.reports.view',          'recruit.reports',       'View reports'),
  ('recruit.reports.export',        'recruit.reports',       'Export reports')
ON DUPLICATE KEY UPDATE `module` = VALUES(`module`), `description` = VALUES(`description`);

-- ============================================================
-- Role-permission assignments
-- ============================================================

-- Super Administrator: all permissions
INSERT IGNORE INTO `core_role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id` FROM `core_roles` r CROSS JOIN `core_permissions` p
WHERE r.`slug` = 'super_admin';

-- Administrator: most permissions except settings.manage and audit.view
INSERT IGNORE INTO `core_role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id` FROM `core_roles` r CROSS JOIN `core_permissions` p
WHERE r.`slug` = 'admin' AND p.`slug` NOT IN ('core.settings.manage', 'core.audit.view');

-- Recruitment Manager: operational permissions
INSERT IGNORE INTO `core_role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id` FROM `core_roles` r CROSS JOIN `core_permissions` p
WHERE r.`slug` = 'recruitment_manager'
  AND p.`slug` IN (
    'core.users.view',
    'recruit.organisations.view','recruit.organisations.create','recruit.organisations.edit',
    'recruit.contacts.view','recruit.contacts.create','recruit.contacts.edit',
    'recruit.engagements.view','recruit.engagements.create','recruit.engagements.edit',
    'recruit.vacancies.view','recruit.vacancies.create','recruit.vacancies.edit','recruit.vacancies.close',
    'recruit.candidates.view','recruit.candidates.create','recruit.candidates.edit','recruit.candidates.export',
    'recruit.assessments.view','recruit.assessments.create','recruit.assessments.evaluate',
    'recruit.interviews.view','recruit.interviews.create','recruit.interviews.edit',
    'recruit.reports.view','recruit.reports.export',
    'core.documents.view','core.documents.upload'
  );

-- Recruiter: day-to-day work
INSERT IGNORE INTO `core_role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id` FROM `core_roles` r CROSS JOIN `core_permissions` p
WHERE r.`slug` = 'recruiter'
  AND p.`slug` IN (
    'recruit.organisations.view',
    'recruit.contacts.view',
    'recruit.engagements.view',
    'recruit.vacancies.view','recruit.vacancies.create','recruit.vacancies.edit',
    'recruit.candidates.view','recruit.candidates.create','recruit.candidates.edit',
    'recruit.assessments.view','recruit.assessments.create','recruit.assessments.evaluate',
    'recruit.interviews.view','recruit.interviews.create','recruit.interviews.edit',
    'recruit.reports.view',
    'core.documents.view','core.documents.upload'
  );

-- Researcher: read-heavy with candidate creation
INSERT IGNORE INTO `core_role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id` FROM `core_roles` r CROSS JOIN `core_permissions` p
WHERE r.`slug` = 'researcher'
  AND p.`slug` IN (
    'recruit.organisations.view',
    'recruit.contacts.view',
    'recruit.engagements.view',
    'recruit.vacancies.view',
    'recruit.candidates.view','recruit.candidates.create','recruit.candidates.edit',
    'core.documents.view','core.documents.upload'
  );

-- Client Administrator
INSERT IGNORE INTO `core_role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id` FROM `core_roles` r CROSS JOIN `core_permissions` p
WHERE r.`slug` = 'client_admin'
  AND p.`slug` IN (
    'recruit.engagements.view',
    'recruit.vacancies.view',
    'recruit.candidates.view',
    'recruit.interviews.view',
    'recruit.reports.view',
    'core.documents.view'
  );

-- Client User
INSERT IGNORE INTO `core_role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id` FROM `core_roles` r CROSS JOIN `core_permissions` p
WHERE r.`slug` = 'client_user'
  AND p.`slug` IN (
    'recruit.engagements.view',
    'recruit.vacancies.view',
    'recruit.candidates.view',
    'recruit.interviews.view'
  );

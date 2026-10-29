-- 0006_seed_lookups.sql
--
-- Seeds the three default lookup groups that were previously hardcoded
-- in VacancyValidator and vacancies/form.php.
--
-- Existing data (rows in recruit_vacancies with work_mode/employment_type/
-- seniority set) stays untouched — the slugs match the old hardcoded
-- values, so existing data continues to work.

INSERT IGNORE INTO `core_lookups` (`group_slug`, `slug`, `label`, `sort_order`, `is_system`) VALUES
  -- Work mode
  ('work_mode',     'onsite',     'On-site',      1, 1),
  ('work_mode',     'hybrid',     'Hybrid',       2, 1),
  ('work_mode',     'remote',     'Remote',       3, 1),

  -- Employment type
  ('employment_type','permanent',  'Permanent',    1, 1),
  ('employment_type','contract',   'Contract',     2, 1),
  ('employment_type','fixed_term', 'Fixed Term',   3, 1),
  ('employment_type','internship', 'Internship',   4, 1),
  ('employment_type','consultancy','Consultancy',  5, 1),

  -- Seniority
  ('seniority',     'junior',     'Junior',       1, 1),
  ('seniority',     'mid',        'Mid-level',    2, 1),
  ('seniority',     'senior',     'Senior',       3, 1),
  ('seniority',     'lead',       'Lead',         4, 1),
  ('seniority',     'manager',    'Manager',      5, 1),
  ('seniority',     'director',   'Director',     6, 1),
  ('seniority',     'executive',  'Executive',    7, 1);

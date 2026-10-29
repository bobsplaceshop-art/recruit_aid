-- 0028_add_delete_permissions.sql
--
-- The seeder only defined view, create, edit for organisations/contacts/
-- engagements/vacancies. The views reference `can('...delete')` but the
-- permission never existed in core_permissions, so the Delete button
-- was always hidden (except for super_admin which bypasses all checks).
--
-- This migration adds the four missing delete permissions.

INSERT IGNORE INTO `core_permissions` (`slug`, `module`, `description`) VALUES
  ('recruit.organisations.delete', 'recruit.organisations', 'Delete organisations'),
  ('recruit.contacts.delete',      'recruit.contacts',      'Delete contacts'),
  ('recruit.engagements.delete',   'recruit.engagements',   'Delete engagements'),
  ('recruit.vacancies.delete',     'recruit.vacancies',     'Delete vacancies');

-- Grant the new delete permissions to the seeded admin and
-- recruitment_manager roles. Super admin bypasses all checks
-- via User::isSuperAdmin() so doesn't need explicit grants.

-- Admin role: all 4 delete permissions
INSERT IGNORE INTO `core_role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id` FROM `core_roles` r CROSS JOIN `core_permissions` p
WHERE r.`slug` = 'admin'
  AND p.`slug` IN (
    'recruit.organisations.delete',
    'recruit.contacts.delete',
    'recruit.engagements.delete',
    'recruit.vacancies.delete'
  );

-- Recruitment manager: all 4 delete permissions (they manage operational data)
INSERT IGNORE INTO `core_role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id` FROM `core_roles` r CROSS JOIN `core_permissions` p
WHERE r.`slug` = 'recruitment_manager'
  AND p.`slug` IN (
    'recruit.organisations.delete',
    'recruit.contacts.delete',
    'recruit.engagements.delete',
    'recruit.vacancies.delete'
  );

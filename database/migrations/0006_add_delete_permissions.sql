-- ============================================================
-- Recruit Aid — add delete permissions for every module
-- ============================================================
-- Delete permissions are separate from the standard CRUD set
-- because deletion is a destructive operation. Each module
-- declares its own .delete permission so role assignments can
-- be granular.
--
-- Slug naming follows docs/NAMING-CONVENTION.md section 6:
--   {module}.{entity}.{action}
-- ============================================================

INSERT INTO `core_permissions` (`slug`, `module`, `description`) VALUES
    ('recruit.organisations.delete', 'recruit.organisations', 'Permanently delete organisations'),
    ('recruit.contacts.delete',      'recruit.contacts',      'Permanently delete contacts'),
    ('recruit.engagements.delete',   'recruit.engagements',   'Permanently delete engagements'),
    ('recruit.vacancies.delete',     'recruit.vacancies',     'Permanently delete vacancies'),
    ('recruit.applications.delete',  'recruit.applications',  'Permanently delete applications'),
    ('recruit.interviews.delete',    'recruit.interviews',    'Permanently delete interviews'),
    ('recruit.offers.delete',        'recruit.offers',        'Permanently delete offers'),
    ('recruit.pipeline.delete',      'recruit.pipeline',      'Permanently delete pipeline stages'),
    ('core.documents.delete',        'core.documents',        'Permanently delete documents'),
    ('core.custom_fields.delete',    'core.custom_fields',    'Permanently delete custom fields')
ON DUPLICATE KEY UPDATE `module` = VALUES(`module`), `description` = VALUES(`description`);

-- Grant every delete permission to super_admin
INSERT IGNORE INTO `core_role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id` FROM `core_roles` r CROSS JOIN `core_permissions` p
WHERE r.`slug` = 'super_admin' AND p.`slug` LIKE '%.delete';

-- Grant all delete permissions to admin EXCEPT custom_fields and pipeline (settings scope)
INSERT IGNORE INTO `core_role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id` FROM `core_roles` r CROSS JOIN `core_permissions` p
WHERE r.`slug` = 'admin'
  AND p.`slug` LIKE '%.delete'
  AND p.`slug` NOT IN ('core.custom_fields.delete', 'recruit.pipeline.delete', 'core.users.delete');

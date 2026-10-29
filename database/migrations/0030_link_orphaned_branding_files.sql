-- 0030_link_orphaned_branding_files.sql
--
-- The initial branding upload didn't call FileService::link() after
-- store(), so the two files (logo + favicon) are orphaned in core_files
-- with no core_entity_files row. That made FileController::serve()
-- return 404, breaking the branding preview images.
--
-- This migration reads the branding file UUIDs from core_system_settings
-- and links each one to the synthetic 'branding' entity. That lets
-- FileController::serve() return the file via the PUBLIC_ENTITY_TYPES
-- bypass (added in the same batch).

INSERT IGNORE INTO `core_entity_files`
  (`file_id`, `entity_type`, `entity_id`, `file_category`, `linked_by`, `created_at`)
SELECT
  f.`id`,
  'branding',
  0,
  'branding_logo',
  NULL,
  NOW()
FROM `core_files` f
JOIN `core_system_settings` s ON s.`value` = f.`uuid`
WHERE s.`key_slug` = 'branding.logo_path'
  AND s.`value` != ''
  AND f.`deleted_at` IS NULL;

INSERT IGNORE INTO `core_entity_files`
  (`file_id`, `entity_type`, `entity_id`, `file_category`, `linked_by`, `created_at`)
SELECT
  f.`id`,
  'branding',
  0,
  'branding_favicon',
  NULL,
  NOW()
FROM `core_files` f
JOIN `core_system_settings` s ON s.`value` = f.`uuid`
WHERE s.`key_slug` = 'branding.favicon_path'
  AND s.`value` != ''
  AND f.`deleted_at` IS NULL;

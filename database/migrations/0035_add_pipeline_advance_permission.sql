-- =====================================================================
-- 0035_add_pipeline_advance_permission.sql
-- Separate "can advance candidates through stages" from "can edit the
-- vacancy description." This matches the industry pattern: hiring
-- managers may advance candidates without being able to edit the job
-- posting, and interviewers cannot advance at all.
-- =====================================================================

INSERT IGNORE INTO `core_permissions` (`slug`, `module`, `description`) VALUES
('recruit.pipeline.advance', 'recruit.pipeline', 'Move candidates through pipeline stages');

-- Super admin: full control
INSERT IGNORE INTO `core_role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id` FROM `core_roles` r
CROSS JOIN `core_permissions` p
WHERE r.`slug` = 'super_admin' AND p.`slug` = 'recruit.pipeline.advance';

-- Admin: same as super_admin for this permission
INSERT IGNORE INTO `core_role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id` FROM `core_roles` r
CROSS JOIN `core_permissions` p
WHERE r.`slug` = 'admin' AND p.`slug` = 'recruit.pipeline.advance';

-- Recruiter: runs the pipeline day-to-day
INSERT IGNORE INTO `core_role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id` FROM `core_roles` r
CROSS JOIN `core_permissions` p
WHERE r.`slug` = 'recruiter' AND p.`slug` = 'recruit.pipeline.advance';

-- Researcher: no pipeline.advance (read + candidate creation only)
-- Client users: no pipeline.advance

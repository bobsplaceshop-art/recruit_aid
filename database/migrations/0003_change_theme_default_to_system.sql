-- ============================================================
-- Recruit Aid - change default theme from 'dark' to 'system'
-- ============================================================

ALTER TABLE `core_users`
  MODIFY COLUMN `theme_preference` ENUM('dark','light','system') NOT NULL DEFAULT 'system';

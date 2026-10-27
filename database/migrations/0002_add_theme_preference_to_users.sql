-- ============================================================
-- Recruit Aid - add theme preference to users
-- ============================================================

ALTER TABLE `core_users`
  ADD COLUMN `theme_preference` ENUM('dark','light','system') NOT NULL DEFAULT 'dark'
  AFTER `locale`;

-- ============================================================
-- Recruit Aid — Notification digest tracking
-- ============================================================
-- Tracks when the daily email digest was last sent per user.
-- A cron job will look for users with unread notifications
-- newer than their last digest time and send a summary email.
-- ============================================================

ALTER TABLE `core_users`
  ADD COLUMN `notification_digest_sent_at` DATETIME NULL
  AFTER `last_login_at`;

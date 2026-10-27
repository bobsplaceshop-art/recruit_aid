-- ============================================================
-- Recruit Aid — cleanup stale sequence counter rows
-- ============================================================
-- Migrations 0009, 0010, 0011 originally inserted counter rows
-- using the pre-rename keys (contact_code, engagement_code,
-- vacancy_code). Those migrations were fixed to use the new
-- prefixed keys, but this install already has the old rows.
--
-- This migration removes the stale rows. The new keys
-- (rec_client_contact_code, rec_engagement_code, rec_vacancy_code)
-- are unaffected.
--
-- Idempotent — safe to run multiple times.
-- ============================================================

DELETE FROM `core_sequence_counters`
WHERE `name` IN (
    'contact_code',
    'engagement_code',
    'vacancy_code',
    'organisation_code'
);

-- ============================================================
-- Recruit Aid — Seed default notification rules
-- ============================================================
-- These are the out-of-the-box rules. Admins can edit or
-- disable them from Admin → Settings → Notifications.
-- Rows with is_system = 1 cannot be deleted, only toggled.
-- ============================================================

INSERT INTO `core_notification_rules`
    (`event_type`, `recipient_type`, `recipient_value`, `channel_inapp`, `channel_email`, `is_system`, `is_active`, `notes`)
VALUES
    -- Settings changes
    ('settings.changed', 'role', 'super_admin', 1, 1, 1, 1, 'Notify other super admins when a security setting changes'),

    -- Rate limit hits
    ('rate_limit.hit', 'role', 'super_admin', 1, 1, 1, 1, 'Notify super admins when a rate limit is triggered'),

    -- Candidate lifecycle (placeholders — will activate when Candidates module ships)
    ('candidate.created', 'permission', 'candidates.view', 1, 0, 1, 1, 'Notify users who can view candidates'),
    ('candidate.created', 'engagement_member', NULL, 1, 0, 1, 1, 'Notify engagement team members'),

    ('candidate.submitted_to_client', 'organisation_client_admin', NULL, 1, 1, 1, 1, 'Notify client admins of new submissions'),
    ('candidate.submitted_to_client', 'assigned_recruiter', NULL, 1, 0, 1, 1, 'Notify the recruiter who submitted'),

    ('client.feedback_received', 'assigned_recruiter', NULL, 1, 1, 1, 1, 'Notify the recruiter who submitted'),
    ('client.feedback_received', 'engagement_member', NULL, 1, 0, 1, 1, 'Notify engagement team'),

    -- Interviews
    ('interview.scheduled', 'assigned_recruiter', NULL, 1, 1, 1, 1, 'Notify the assigned recruiter'),
    ('interview.scheduled', 'hiring_manager', NULL, 1, 1, 1, 1, 'Notify the hiring manager'),
    ('interview.scheduled', 'organisation_client_admin', NULL, 1, 1, 1, 1, 'Notify client admins'),

    -- Offers
    ('offer.issued', 'assigned_recruiter', NULL, 1, 1, 1, 1, 'Notify the recruiter who managed the offer'),
    ('offer.issued', 'hiring_manager', NULL, 1, 1, 1, 1, 'Notify the hiring manager'),
    ('offer.issued', 'organisation_client_admin', NULL, 1, 1, 1, 1, 'Notify client admins'),

    -- Tasks
    ('task.assigned', 'owner', NULL, 1, 1, 1, 1, 'Notify the assignee'),

    -- Users
    ('user.created', 'role', 'super_admin', 1, 0, 1, 1, 'Notify super admins when a new user is created'),
    ('user.status_changed', 'role', 'super_admin', 1, 0, 1, 1, 'Notify super admins when a user status changes')
ON DUPLICATE KEY UPDATE
    `is_system` = VALUES(`is_system`),
    `notes` = VALUES(`notes`);

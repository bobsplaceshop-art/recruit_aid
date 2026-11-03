<?php

declare(strict_types=1);

/**
 * Database table ownership classifications for the security auditor.
 *
 * tenant_scoped:
 *   Records must be isolated using tenant_id.
 *
 * user_scoped:
 *   Records inherit ownership through a globally unique user_id.
 *   Writes and reads must validate the associated user's tenant.
 *
 * global:
 *   Shared platform definitions. Tenant filtering is not expected.
 *
 * review:
 *   Ownership model has not yet been fully established.
 */

return [
    'tenant_scoped' => [
        'core_permission_policies',
        'core_admin_delegations',
        'core_rbac_audit_logs',
        'core_users',

        'hr_employees',

        'recruit_client_organisations',
        'recruit_pipeline_stages',
        'recruit_client_contacts',
        'recruit_engagement_packages',
        'recruit_engagements',
        'recruit_engagement_recruiters',
        'recruit_vacancies',
        'recruit_vacancy_stages',
        'recruit_vacancy_requirements',
        'recruit_engagement_assignments',
        'recruit_candidates',
        'recruit_candidate_skills',
        'recruit_candidate_experience',
        'recruit_candidate_education',
        'recruit_candidate_tags',
        'recruit_applications',
        'recruit_application_stage_history',
        'recruit_interviews',
        'recruit_interview_participants',
        'recruit_scorecards',
        'recruit_scorecard_attributes',
        'recruit_contact_roles',
        'recruit_offers',
        'recruit_offer_history',
        'recruit_vacancy_recruiters',
        'recruit_vacancy_hiring_managers',
    ],

    'user_scoped' => [
        'core_user_applications',
        'core_user_roles',
        'core_user_permissions',
        'core_notifications',
    ],

    'global' => [
        'core_tenants',
        'core_roles',
        'core_role_permissions',
        'core_permissions',
        'core_permission_registrations',
        'core_modules',
        'core_module_events',
        'core_module_licenses',
        'core_module_manifests',
        'core_module_state',
        'core_scheduled_tasks',
        'core_scheduled_task_runs',
    ],

    'review' => [
        'core_sequence_counters',
        'core_persons',
        'core_activities',
        'core_notes',
        'core_notification_rules',
        'core_webhooks',
        'core_webhook_deliveries',
        'core_workflow_definitions',
        'core_workflow_states',
        'core_workflow_transitions',
        'core_update_manifest',
        'core_updates_available',
        'core_updates_history',
        'core_password_resets',
        'core_rate_limits',
        'core_system_settings',
    ],
];

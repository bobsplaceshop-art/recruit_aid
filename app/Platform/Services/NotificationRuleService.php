<?php
declare(strict_types=1);

namespace CorporateAid\Platform\Services;

use CorporateAid\Core\Auth;
use CorporateAid\Core\Database;
use CorporateAid\Core\PDOParams;
use CorporateAid\Core\Row;
use CorporateAid\Core\TenantContext;
use CorporateAid\Recruit\Repositories\EngagementAssignmentRepository;

final class NotificationRuleService
{
    public function __construct(
        private Database $db,
        private EngagementAssignmentRepository $engagementAssignments,
        private TenantContext $tenant,
        private ?Auth $auth = null
    ) {
    }

    /**
     * @param array<string, mixed> $context
     * @return int[]
     */
    public function resolveRecipients(string $eventType, array $context = [], bool $excludeActor = true): array
    {
        $rules = $this->activeRulesFor($eventType);
        if (empty($rules)) return [];

        $userIds = [];

        foreach ($rules as $rule) {
            $resolved = $this->resolveOne($rule, $context);
            foreach ($resolved as $id) {
                $userIds[$id] = true;
            }
        }

        $ids = array_keys($userIds);

        if ($excludeActor && $this->auth !== null) {
            $actorId = $this->auth->user()?->id;
            if ($actorId !== null) {
                $ids = array_filter($ids, fn ($id) => $id !== $actorId);
            }
        }

        return array_values($ids);
    }

    /** @return array{inapp: bool, email: bool} */
    public function channelsFor(string $eventType): array
    {
        $rules = $this->activeRulesFor($eventType);
        $inapp = false;
        $email = false;
        foreach ($rules as $r) {
            if (Row::bool($r, 'channel_inapp')) $inapp = true;
            if (Row::bool($r, 'channel_email')) $email = true;
        }
        return ['inapp' => $inapp, 'email' => $email];
    }

    /** @return array<int, array<string,mixed>> */
    private function activeRulesFor(string $eventType): array
    {
        return $this->db->select(
            "SELECT * FROM `core_notification_rules`
             WHERE `event_type` = ?
               AND `is_active` = 1
               AND `deleted_at` IS NULL",
            PDOParams::positional([$eventType])
        );
    }

    /**
     * @param array<string, mixed> $rule
     * @param array<string, mixed> $context
     * @return int[]
     */
    private function resolveOne(array $rule, array $context): array
    {
        $type = Row::str($rule, 'recipient_type');
        $value = Row::nullableStr($rule, 'recipient_value') ?? '';

        return match ($type) {
            'role'                          => $this->usersWithRole($value),
            'permission'                    => $this->usersWithPermission($value),
            'user'                          => $this->explicitUser($value),
            'assigned_recruiter'            => $this->assignedRecruiter($context),
            'engagement_member'             => $this->engagementMembers($context),
            'organisation_client_admin'     => $this->organisationClientUsers($context, ['client_admin']),
            'organisation_client_user'      => $this->organisationClientUsers($context, ['client_admin','client_user']),
            'hiring_manager'                => $this->hiringManager($context),
            'owner'                         => $this->owner($context),
            'actor_except'                  => [],
            default                         => [],
        };
    }

    /** @return int[] */
    private function usersWithRole(string $roleSlug): array
    {
        if ($roleSlug === '') return [];
        $rows = $this->db->select(
            "SELECT DISTINCT u.`id`
             FROM `core_users` u
             JOIN `core_user_roles` ur ON ur.`user_id` = u.`id`
             JOIN `core_roles` r ON r.`id` = ur.`role_id`
             WHERE u.`deleted_at` IS NULL AND u.`status` = 'active'
               AND r.`slug` = ?",
            PDOParams::positional([$roleSlug])
        );
        return array_map(fn ($r) => Row::int($r, 'id'), $rows);
    }

    /** @return int[] */
    private function usersWithPermission(string $permissionSlug): array
    {
        if ($permissionSlug === '') return [];
        $rows = $this->db->select(
            "SELECT DISTINCT u.`id`
             FROM `core_users` u
             JOIN `core_user_roles` ur ON ur.`user_id` = u.`id`
             JOIN `core_role_permissions` rp ON rp.`role_id` = ur.`role_id`
             JOIN `core_permissions` p ON p.`id` = rp.`permission_id`
             WHERE u.`deleted_at` IS NULL AND u.`status` = 'active'
               AND p.`slug` = ?",
            PDOParams::positional([$permissionSlug])
        );
        return array_map(fn ($r) => Row::int($r, 'id'), $rows);
    }

    /** @return int[] */
    private function explicitUser(string $userId): array
    {
        $id = (int) $userId;
        if ($id <= 0) return [];

        $active = $this->db->scalarInt(
            "SELECT `id` FROM `core_users`
             WHERE `id` = ? AND `deleted_at` IS NULL AND `status` = 'active'",
            PDOParams::positional([$id])
        );
        return $active > 0 ? [$active] : [];
    }

    /** @param array<string, mixed> $context
     *  @return int[] */
    private function assignedRecruiter(array $context): array
    {
        $vacancyId = Row::int($context, 'vacancy_id');
        if ($vacancyId === 0) return [];

        $recruiterId = $this->db->scalarInt(
            "SELECT `recruiter_id` FROM `recruit_vacancies` WHERE `id` = ? AND `tenant_id` = ?",
            PDOParams::positional([$vacancyId, $this->tenant->id()])
        );
        return $recruiterId > 0 ? [$recruiterId] : [];
    }

    /** @param array<string, mixed> $context
     *  @return int[] */
    private function engagementMembers(array $context): array
    {
        $engagementId = Row::int($context, 'engagement_id');
        if ($engagementId === 0) return [];

        return $this->engagementAssignments->resolveUserIds($engagementId);
    }

    /**
     * @param array<string, mixed> $context
     * @param string[] $roleSlugs
     * @return int[]
     */
    private function organisationClientUsers(array $context, array $roleSlugs): array
    {
        $orgId = Row::int($context, 'organisation_id'); // Tenant org ID for core_users
        if ($orgId === 0) return [];

        $placeholders = implode(',', array_fill(0, count($roleSlugs), '?'));
        $params = array_merge([$orgId], $roleSlugs);

        $rows = $this->db->select(
            "SELECT DISTINCT u.`id`
             FROM `core_users` u
             JOIN `core_user_roles` ur ON ur.`user_id` = u.`id`
             JOIN `core_roles` r ON r.`id` = ur.`role_id`
             WHERE u.`organisation_id` = ?
               AND u.`is_client_user` = 1
               AND u.`deleted_at` IS NULL
               AND u.`status` = 'active'
               AND r.`slug` IN ({$placeholders})",
            PDOParams::positional($params)
        );
        return array_map(fn ($r) => Row::int($r, 'id'), $rows);
    }

    /** @param array<string, mixed> $context
     *  @return int[] */
    private function hiringManager(array $context): array
    {
        $contactId = Row::int($context, 'hiring_manager_contact_id');
        if ($contactId === 0) return [];

        $contact = $this->db->selectOne(
            "SELECT `email` FROM `recruit_client_contacts` WHERE `id` = ? AND `tenant_id` = ?",
            PDOParams::positional([$contactId, $this->tenant->id()])
        );
        if ($contact === null) return [];

        $email = Row::str($contact, 'email');
        if ($email === '') return [];

        $userId = $this->db->scalarInt(
            "SELECT `id` FROM `core_users`
             WHERE `email` = ? AND `deleted_at` IS NULL AND `status` = 'active'",
            PDOParams::positional([$email])
        );
        return $userId > 0 ? [$userId] : [];
    }

    /** @param array<string, mixed> $context
     *  @return int[] */
    private function owner(array $context): array
    {
        foreach (['owner_id', 'assigned_to', 'assignee_id'] as $key) {
            $id = Row::int($context, $key);
            if ($id > 0) {
                return [$id];
            }
        }
        return [];
    }
}

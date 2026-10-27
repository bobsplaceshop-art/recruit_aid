<?php
declare(strict_types=1);

namespace CorporateAid\Platform\Services;

use CorporateAid\Core\Auth;
use CorporateAid\Core\Database;
use CorporateAid\Recruit\Repositories\EngagementAssignmentRepository;

/**
 * Resolves notification rules into concrete user IDs.
 *
 * Rules are stored in `core_notification_rules`. Each rule has a
 * recipient_type and optional recipient_value. For context-based
 * rules (assigned_recruiter, engagement_member, etc.), the caller
 * passes a context array with the relevant IDs.
 *
 * The service handles rule lookup, recipient resolution, and
 * actor exclusion (so the person who triggered an event is not
 * notified about their own action unless a rule explicitly allows it).
 */
final class NotificationRuleService
{

    // ---------- scalar coercion helper (PHPStan Level 9) ----------

    private static function toInt(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    public function __construct(
        private Database $db,
        private EngagementAssignmentRepository $engagementAssignments,
        private ?Auth $auth = null
    ) {
    }

    /**
     * Resolve the recipient user IDs for a given event.
     *
     * @param string $eventType  e.g. 'interview.scheduled'
     * @param array  $context    Context data — see docs below
     * @param bool   $excludeActor  If true, remove the current user from the list
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
                $userIds[$id] = true; // dedupe via associative array
            }
        }

        $ids = array_keys($userIds);

        // Exclude actor
        if ($excludeActor && $this->auth !== null) {
            $actorId = $this->auth->user()?->id;
            if ($actorId !== null) {
                $ids = array_values(array_filter($ids, fn ($id) => $id !== $actorId));
            }
        }

        return array_values($ids);
    }

    /**
     * Resolve the channel config for a given event.
     * Returns ['inapp' => bool, 'email' => bool].
     * If any active rule enables the channel, the channel is enabled.
     */
    public function channelsFor(string $eventType): array
    {
        $rules = $this->activeRulesFor($eventType);
        $inapp = false;
        $email = false;
        foreach ($rules as $r) {
            if (!empty($r['channel_inapp'])) $inapp = true;
            if (!empty($r['channel_email'])) $email = true;
        }
        return ['inapp' => $inapp, 'email' => $email];
    }

    // ---------- Rule lookup ----------

    /**
     * @return array<int, array<string,mixed>>
     */
    private function activeRulesFor(string $eventType): array
    {
        return $this->db->select(
            "SELECT * FROM `core_notification_rules`
             WHERE `event_type` = ?
               AND `is_active` = 1
               AND `deleted_at` IS NULL",
            [$eventType]
        );
    }

    // ---------- Per-rule resolution ----------

    /**
     * @return int[]
     */
    private function resolveOne(array $rule, array $context): array
    {
        $type = (string) $rule['recipient_type'];
        $value = $rule['recipient_value'] !== null ? (string) $rule['recipient_value'] : '';

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
            'actor_except'                  => [], // no-op; actor exclusion is handled globally
            default                         => [],
        };
    }

    // ---------- Recipient resolvers ----------

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
            [$roleSlug]
        );
        return array_map(fn ($r) => (int) $r['id'], $rows);
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
            [$permissionSlug]
        );
        return array_map(fn ($r) => (int) $r['id'], $rows);
    }

    /** @return int[] */
    private function explicitUser(string $userId): array
    {
        $id = (int) $userId;
        if ($id <= 0) return [];

        $active = $this->db->scalar(
            "SELECT `id` FROM `core_users`
             WHERE `id` = ? AND `deleted_at` IS NULL AND `status` = 'active'",
            [$id]
        );
        return $active ? [self::toInt($active)] : [];
    }

    /**
     * The recruiter assigned to the vacancy.
     * @return int[]
     */
    private function assignedRecruiter(array $context): array
    {
        $vacancyId = (int) ($context['vacancy_id'] ?? 0);
        if ($vacancyId === 0) return [];

        $recruiterId = $this->db->scalar(
            "SELECT `recruiter_id` FROM `recruit_vacancies` WHERE `id` = ?",
            [$vacancyId]
        );
        return $recruiterId ? [self::toInt($recruiterId)] : [];
    }

    /**
     * Users assigned to the parent engagement — individually or via role/permission.
     * @return int[]
     */
    private function engagementMembers(array $context): array
    {
        $engagementId = (int) ($context['engagement_id'] ?? 0);
        if ($engagementId === 0) return [];

        return $this->engagementAssignments->resolveUserIds($engagementId);
    }

    /**
     * Active client users for the organisation of the triggering record.
     * @param string[] $roleSlugs
     * @return int[]
     */
    private function organisationClientUsers(array $context, array $roleSlugs): array
    {
        $orgId = (int) ($context['organisation_id'] ?? 0);
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
            $params
        );
        return array_map(fn ($r) => (int) $r['id'], $rows);
    }

    /**
     * The hiring manager contact on the vacancy — resolved to a user if that
     * contact has a linked user account. Most contacts don't, so this often
     * returns an empty list. Later we may add "email the contact" as a
     * separate channel (out of scope for in-app).
     * @return int[]
     */
    private function hiringManager(array $context): array
    {
        $contactId = (int) ($context['hiring_manager_contact_id'] ?? 0);
        if ($contactId === 0) return [];

        $contact = $this->db->selectOne(
            "SELECT `email` FROM `recruit_client_contacts` WHERE `id` = ?",
            [$contactId]
        );
        if (!$contact) return [];

        // Match contact email to a user account
        $userId = $this->db->scalar(
            "SELECT `id` FROM `core_users`
             WHERE `email` = ? AND `deleted_at` IS NULL AND `status` = 'active'",
            [$contact['email']]
        );
        return $userId ? [(int) $userId] : [];
    }

    /**
     * The owner of the triggering record (e.g., task assignee, application recruiter).
     * @return int[]
     */
    private function owner(array $context): array
    {
        // Check known owner keys in the context
        foreach (['owner_id', 'assigned_to', 'assignee_id'] as $key) {
            $id = (int) ($context[$key] ?? 0);
            if ($id > 0) {
                return [$id];
            }
        }
        return [];
    }
}

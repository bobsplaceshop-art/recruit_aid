<?php
declare(strict_types=1);

namespace CorporateAid\Platform\Services;

use CorporateAid\Core\Auth;
use CorporateAid\Platform\Repositories\NotificationRepository;
use CorporateAid\Platform\Repositories\UserRepository;
use CorporateAid\Platform\Support\Logger;

/**
 * Notification service.
 *
 * Public API:
 *   - dispatch($eventType, $context, $title, $body, $link) — rule-driven
 *   - notify($userId, ...) — direct, bypasses rules (used sparingly)
 *   - Read helpers for the current user
 *
 * Recipient targeting comes from notification_rules via
 * NotificationRuleService. Do not hardcode recipient lists here.
 */
final class NotificationService
{
    public function __construct(
        private NotificationRepository $repo,
        private NotificationRuleService $rules,
        private ?Auth $auth = null
    ) {
    }

    // ============================================================
    // Rule-driven dispatch (preferred)
    // ============================================================

    /**
     * Send a notification for an event type.
     * Recipient list and channel config come from notification_rules.
     *
     * @param string $eventType  e.g. 'candidate.created'
     * @param array  $context    Context data used to resolve context-based
     *                           recipient types (assigned_recruiter,
     *                           engagement_member, owner, hiring_manager,
     *                           organisation_client_admin, etc.)
     * @param string $title      Short headline shown in the bell
     * @param string $body       Optional secondary line
     * @param string $link       Optional click-through URL
     */
    /** @param array<string, mixed> $context */
    public function dispatch(
        string $eventType,
        array $context = [],
        string $title = '',
        string $body = '',
        string $link = ''
    ): void {
        try {
            $recipientIds = $this->rules->resolveRecipients($eventType, $context, true);
            if (empty($recipientIds)) {
                return; // no rules matched, no one to notify
            }

            $channels = $this->rules->channelsFor($eventType);
            if (empty($channels['inapp'])) {
                return; // in-app channel disabled for this event
            }

            foreach ($recipientIds as $userId) {
                $this->repo->create($userId, $eventType, $title, $body ?: null, $link ?: null);
            }
        } catch (\Throwable $e) {
            Logger::warning('Notification dispatch failed: ' . $e->getMessage(), [
                'event' => $eventType,
            ]);
        }
    }

    // ============================================================
    // Direct notify (bypasses rules) — use sparingly
    // ============================================================

    public function notify(int $userId, string $type, string $title, ?string $body = null, ?string $link = null): void
    {
        try {
            $this->repo->create($userId, $type, $title, $body, $link);
        } catch (\Throwable $e) {
            Logger::warning('Notification create failed: ' . $e->getMessage(), [
                'user_id' => $userId, 'type' => $type,
            ]);
        }
    }

    /** @param int[] $userIds */
    public function notifyMany(array $userIds, string $type, string $title, ?string $body = null, ?string $link = null): void
    {
        $unique = array_unique(array_map('intval', $userIds));
        foreach ($unique as $uid) {
            if ($uid > 0) $this->notify($uid, $type, $title, $body, $link);
        }
    }

    // ============================================================
    // Convenience events
    // ============================================================

    /**
     * Fired by SystemSettingService when a security setting changes.
     * Recipients resolved from rules (default: other super admins).
     */
    public function settingsChanged(string $key, mixed $oldValue, mixed $newValue): void
    {
        $actor = $this->auth?->user();
        $actorName = $actor !== null ? $actor->name : 'Unknown';

        $label = SystemSettingService::getHumanLabel($key);
        $oldDisplay = SystemSettingService::formatValue($key, $oldValue);
        $newDisplay = SystemSettingService::formatValue($key, $newValue);

        $this->dispatch(
            'settings.changed',
            [],
            'Security setting changed',
            "{$label}: {$oldDisplay} → {$newDisplay} (changed by {$actorName})",
            '/admin/settings/security'
        );
    }

    /**
     * Fired by RateLimitMiddleware when an IP hits a rate limit.
     * Recipients resolved from rules (default: all super admins).
     */
    public function rateLimitHit(string $action, string $ip): void
    {
        $this->dispatch(
            'rate_limit.hit',
            [],
            'Rate limit triggered',
            "Action \"{$action}\" was rate-limited from IP {$ip}",
            '/admin/settings/security'
        );
    }

    // ============================================================
    // Read helpers for the current user
    // ============================================================

    /** @return \CorporateAid\Platform\Models\Notification[] */
    public function recentForCurrentUser(int $limit = 10): array
    {
        $user = $this->auth?->user();
        if ($user === null) return [];
        return $this->repo->forUser($user->id, $limit);
    }

    public function unreadCountForCurrentUser(): int
    {
        $user = $this->auth?->user();
        if ($user === null) return 0;
        return $this->repo->unreadCount($user->id);
    }

    public function markRead(int $id): void
    {
        $user = $this->auth?->user();
        if ($user === null) return;
        $this->repo->markRead($id, $user->id);
    }

    public function markAllRead(): void
    {
        $user = $this->auth?->user();
        if ($user === null) return;
        $this->repo->markAllRead($user->id);
    }
}

<?php
declare(strict_types=1);

namespace CorporateAid\Recruit\Controllers\Admin;

use CorporateAid\Core\Auth;
use CorporateAid\Core\Container;
use CorporateAid\Core\Database;
use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Core\Session;
use CorporateAid\Core\View;

final class AdminNotificationRuleController
{
    public function __construct(private Container $container) {}

    /**
     * GET /admin/settings/notifications
     *
     * Lists all notification rules grouped by event type. Each rule is
     * enriched with a human-readable `event_label` and `recipient_label`
     * before being passed to the view — the view never sees raw slugs.
     */
    public function index(Request $request, array $params = []): Response
    {
        /** @var View $view */
        $view = $this->container->get(View::class);
        /** @var Auth $auth */
        $auth = $this->container->get(Auth::class);
        /** @var Database $db */
        $db = $this->container->get(Database::class);
        /** @var Session $session */
        $session = $this->container->get(Session::class);

        $rules = $db->select(
            "SELECT * FROM `core_notification_rules`
             WHERE `deleted_at` IS NULL
             ORDER BY `event_type` ASC, `recipient_type` ASC, `recipient_value` ASC"
        );

        $eventTypes = $this->eventTypeCatalog();
        $recipientTypes = $this->recipientTypeCatalog();

        // Enrich each rule with human-readable labels. Group by event_type.
        $grouped = [];
        foreach ($rules as $r) {
            $r['event_label'] = $eventTypes[$r['event_type']]
                ?? $this->humanizeSlug((string) $r['event_type']);

            $r['recipient_label'] = $this->resolveRecipientLabel(
                (string) $r['recipient_type'],
                $r['recipient_value'] !== null ? (string) $r['recipient_value'] : null
            );

            $grouped[$r['event_type']][] = $r;
        }

        $success = $session->getFlash('success');
        $error = $session->getFlash('error');

        $html = $view->render('admin/settings/notifications', [
            'title'          => 'Notification Rules',
            'user'           => $auth->user(),
            'grouped'        => $grouped,
            'eventTypes'     => $eventTypes,
            'recipientTypes' => $recipientTypes,
            'success'        => is_string($success) ? $success : null,
            'error'          => is_string($error) ? $error : null,
            'isSuper'        => $auth->user()?->isSuperAdmin() ?? false,
        ], 'layouts/app');

        return Response::html($html);
    }

    /**
     * POST /admin/settings/notifications
     * Create a new rule.
     */
    public function store(Request $request, array $params = []): Response
    {
        /** @var Database $db */
        $db = $this->container->get(Database::class);
        /** @var Auth $auth */
        $auth = $this->container->get(Auth::class);
        /** @var Session $session */
        $session = $this->container->get(Session::class);

        if (!$auth->user()?->isSuperAdmin()) {
            $session->flash('error', 'Only super administrators can manage notification rules.');
            return Response::redirect('/admin/settings/notifications');
        }

        $event = trim((string) $request->input('event_type', ''));
        $type = trim((string) $request->input('recipient_type', ''));
        $value = trim((string) $request->input('recipient_value', ''));
        $inapp = (bool) $request->input('channel_inapp', false);
        $email = (bool) $request->input('channel_email', false);

        $errors = [];
        if ($event === '' || !isset($this->eventTypeCatalog()[$event])) {
            $errors[] = 'Please choose a valid event type.';
        }
        if ($type === '' || !isset($this->recipientTypeCatalog()[$type])) {
            $errors[] = 'Please choose a valid recipient type.';
        }
        if ($this->recipientNeedsValue($type) && $value === '') {
            $errors[] = 'This recipient type requires a value (role slug, permission slug, or user ID).';
        }
        if (!$inapp && !$email) {
            $errors[] = 'Enable at least one channel (in-app or email).';
        }

        if (!empty($errors)) {
            $session->flash('error', implode(' ', $errors));
            return Response::redirect('/admin/settings/notifications');
        }

        try {
            $db->execute(
                "INSERT INTO `core_notification_rules`
                    (`event_type`, `recipient_type`, `recipient_value`,
                     `channel_inapp`, `channel_email`, `is_system`, `is_active`, `created_by`)
                 VALUES (?, ?, ?, ?, ?, 0, 1, ?)",
                [$event, $type, $value ?: null, (int) $inapp, (int) $email, $auth->user()->id]
            );
            $session->flash('success', 'Notification rule added.');
        } catch (\Throwable $e) {
            $session->flash('error', 'Could not add rule: ' . $e->getMessage());
        }

        return Response::redirect('/admin/settings/notifications');
    }

    /**
     * POST /admin/settings/notifications/{id}/toggle
     * Toggle a channel or active state.
     */
    public function toggle(Request $request, array $params = []): Response
    {
        /** @var Database $db */
        $db = $this->container->get(Database::class);
        /** @var Auth $auth */
        $auth = $this->container->get(Auth::class);
        /** @var Session $session */
        $session = $this->container->get(Session::class);

        if (!$auth->user()?->isSuperAdmin()) {
            $session->flash('error', 'Only super administrators can manage notification rules.');
            return Response::redirect('/admin/settings/notifications');
        }

        $id = (int) ($params['id'] ?? 0);
        $column = (string) $request->input('column', '');
        $value = (int) (bool) $request->input('value', false);

        $allowedColumns = ['channel_inapp', 'channel_email', 'is_active'];
        if ($id === 0 || !in_array($column, $allowedColumns, true)) {
            $session->flash('error', 'Invalid toggle request.');
            return Response::redirect('/admin/settings/notifications');
        }

        try {
            $db->execute(
                "UPDATE `core_notification_rules` SET `{$column}` = ? WHERE `id` = ?",
                [$value, $id]
            );
            $session->flash('success', 'Rule updated.');
        } catch (\Throwable $e) {
            $session->flash('error', 'Update failed: ' . $e->getMessage());
        }

        return Response::redirect('/admin/settings/notifications');
    }

    /**
     * POST /admin/settings/notifications/{id}/delete
     * Soft-delete a non-system rule.
     */
    public function destroy(Request $request, array $params = []): Response
    {
        /** @var Database $db */
        $db = $this->container->get(Database::class);
        /** @var Auth $auth */
        $auth = $this->container->get(Auth::class);
        /** @var Session $session */
        $session = $this->container->get(Session::class);

        if (!$auth->user()?->isSuperAdmin()) {
            $session->flash('error', 'Only super administrators can manage notification rules.');
            return Response::redirect('/admin/settings/notifications');
        }

        $id = (int) ($params['id'] ?? 0);
        if ($id === 0) {
            return Response::redirect('/admin/settings/notifications');
        }

        try {
            $rule = $db->selectOne("SELECT `is_system` FROM `core_notification_rules` WHERE `id` = ?", [$id]);
            if (!$rule) {
                $session->flash('error', 'Rule not found.');
            } elseif (!empty($rule['is_system'])) {
                $session->flash('error', 'System rules cannot be deleted — disable them instead.');
            } else {
                $db->execute(
                    "UPDATE `core_notification_rules` SET `deleted_at` = NOW() WHERE `id` = ?",
                    [$id]
                );
                $session->flash('success', 'Rule deleted.');
            }
        } catch (\Throwable $e) {
            $session->flash('error', 'Delete failed: ' . $e->getMessage());
        }

        return Response::redirect('/admin/settings/notifications');
    }

    // ============================================================
    // Human-friendly label resolution
    // ============================================================

    /**
     * Turn an internal slug into a human label. Fallback used only
     * when we can't resolve a value against the DB.
     * "candidate.created" → "Candidate Created"
     * "some-slug_value"   → "Some Slug Value"
     */
    private function humanizeSlug(string $slug): string
    {
        $parts = preg_split('/[._\-]+/', $slug) ?: [$slug];
        $parts = array_filter($parts, 'strlen');
        if (empty($parts)) return $slug;
        return implode(' ', array_map('ucfirst', $parts));
    }

    /**
     * Resolve a recipient rule's value into a natural human-readable
     * label. Value-bearing types (role, permission, user) get a
     * sentence. Context-based types use the catalog directly.
     */
    private function resolveRecipientLabel(string $type, ?string $value): string
    {
        /** @var Database $db */
        $db = $this->container->get(Database::class);

        // ---- Value-bearing types ----

        if ($type === 'role') {
            if ($value === null || $value === '') {
                return 'Anyone with a role';
            }
            $name = $db->scalar("SELECT `name` FROM `core_roles` WHERE `slug` = ?", [$value]);
            $name = ($name !== null && $name !== false)
                ? (string) $name
                : $this->humanizeSlug($value);
            return "Anyone with the {$name} role";
        }

        if ($type === 'permission') {
            if ($value === null || $value === '') {
                return 'Anyone with a permission';
            }
            $desc = $db->scalar("SELECT `description` FROM `core_permissions` WHERE `slug` = ?", [$value]);
            if ($desc !== null && $desc !== false && $desc !== '') {
                // Descriptions read like "View candidates" → "Anyone who can view candidates"
                return 'Anyone who can ' . lcfirst(trim((string) $desc));
            }
            // Fallback: humanize the slug parts
            $fallback = str_replace('.', ' ', $value);
            return 'Anyone who can ' . strtolower($this->humanizeSlug($fallback));
        }

        if ($type === 'user') {
            if ($value === null || $value === '') {
                return 'A specific user';
            }
            $id = (int) $value;
            if ($id > 0) {
                $name = $db->scalar("SELECT `name` FROM `core_users` WHERE `id` = ?", [$id]);
                if ($name !== null && $name !== false) {
                    return "A specific user ({$name})";
                }
            }
            return "A specific user (#{$value})";
        }

        // ---- Context-based types: use the catalog directly ----
        $catalog = $this->recipientTypeCatalog();
        if (isset($catalog[$type])) {
            return $catalog[$type];
        }

        // Unknown type — humanize as a last resort
        return $this->humanizeSlug($type);
    }

    // ---------- catalogs ----------

    /** @return array<string,string> slug => label */
    private function eventTypeCatalog(): array
    {
        return [
            'settings.changed'              => 'A security setting was changed',
            'rate_limit.hit'                => 'A rate limit was triggered',
            'candidate.created'             => 'A new candidate was created',
            'candidate.submitted_to_client' => 'A candidate was submitted to a client',
            'client.feedback_received'      => 'A client submitted feedback',
            'interview.scheduled'           => 'An interview was scheduled',
            'offer.issued'                  => 'An offer was issued',
            'task.assigned'                 => 'A task was assigned',
            'user.created'                  => 'A user account was created',
            'user.status_changed'           => 'A user status was changed',
        ];
    }

    /** @return array<string,string> slug => label */
    private function recipientTypeCatalog(): array
    {
        return [
            'role'                      => 'Everyone with a role',
            'permission'                => 'Everyone with a permission',
            'user'                      => 'A specific user (by ID)',
            'assigned_recruiter'        => 'The recruiter assigned to the vacancy',
            'engagement_member'         => 'Everyone assigned to the engagement',
            'organisation_client_admin' => 'Client admins of the organisation',
            'organisation_client_user'  => 'All client users of the organisation',
            'hiring_manager'            => 'The hiring manager on the vacancy',
            'owner'                     => 'The owner of the triggering record',
        ];
    }

    private function recipientNeedsValue(string $type): bool
    {
        return in_array($type, ['role', 'permission', 'user'], true);
    }
}

<?php
declare(strict_types=1);

namespace CorporateAid\Recruit\Controllers\Admin;

use CorporateAid\Core\Auth;
use CorporateAid\Core\Container;
use CorporateAid\Core\Database;
use CorporateAid\Core\PDOParams;
use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Core\Row;
use CorporateAid\Core\Session;
use CorporateAid\Core\View;

final class AdminNotificationRuleController
{
    public function __construct(private Container $container) {}

    /** @param array<string, string> $params */
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

        $grouped = [];
        foreach ($rules as $r) {
            $eventType = Row::str($r, 'event_type');
            $recipientType = Row::str($r, 'recipient_type');
            $recipientValue = Row::nullableStr($r, 'recipient_value');

            $r['event_label'] = $eventTypes[$eventType] ?? $this->humanizeSlug($eventType);
            $r['recipient_label'] = $this->resolveRecipientLabel($recipientType, $recipientValue);
            $grouped[$eventType][] = $r;
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

    /** @param array<string, string> $params */
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

        $event = trim($request->inputString('event_type', ''));
        $type  = trim($request->inputString('recipient_type', ''));
        $value = trim($request->inputString('recipient_value', ''));
        $inapp = $request->inputBool('channel_inapp', false);
        $email = $request->inputBool('channel_email', false);

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
                PDOParams::positional([
                    $event, $type, $value !== '' ? $value : null,
                    $inapp ? 1 : 0, $email ? 1 : 0, $auth->user()?->id ?? 0,
                ])
            );
            $session->flash('success', 'Notification rule added.');
        } catch (\Throwable $e) {
            $session->flash('error', 'Could not add rule: ' . $e->getMessage());
        }

        return Response::redirect('/admin/settings/notifications');
    }

    /** @param array<string, string> $params */
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

        $id = Row::int($params, 'id');
        $column = $request->inputString('column', '');
        $value = $request->inputBool('value', false) ? 1 : 0;

        $allowedColumns = ['channel_inapp', 'channel_email', 'is_active'];
        if ($id === 0 || !in_array($column, $allowedColumns, true)) {
            $session->flash('error', 'Invalid toggle request.');
            return Response::redirect('/admin/settings/notifications');
        }

        try {
            $db->execute(
                "UPDATE `core_notification_rules` SET `{$column}` = ? WHERE `id` = ?",
                PDOParams::positional([$value, $id])
            );
            $session->flash('success', 'Rule updated.');
        } catch (\Throwable $e) {
            $session->flash('error', 'Update failed: ' . $e->getMessage());
        }

        return Response::redirect('/admin/settings/notifications');
    }

    /** @param array<string, string> $params */
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

        $id = Row::int($params, 'id');
        if ($id === 0) {
            return Response::redirect('/admin/settings/notifications');
        }

        try {
            $rule = $db->selectOne(
                "SELECT `is_system` FROM `core_notification_rules` WHERE `id` = ?",
                PDOParams::positional([$id])
            );
            if ($rule === null) {
                $session->flash('error', 'Rule not found.');
            } elseif (Row::bool($rule, 'is_system')) {
                $session->flash('error', 'System rules cannot be deleted — disable them instead.');
            } else {
                $db->execute(
                    "UPDATE `core_notification_rules` SET `deleted_at` = NOW() WHERE `id` = ?",
                    PDOParams::positional([$id])
                );
                $session->flash('success', 'Rule deleted.');
            }
        } catch (\Throwable $e) {
            $session->flash('error', 'Delete failed: ' . $e->getMessage());
        }

        return Response::redirect('/admin/settings/notifications');
    }

    private function humanizeSlug(string $slug): string
    {
        $parts = preg_split('/[._\-]+/', $slug) ?: [$slug];
        $parts = array_filter($parts, static fn ($v) => is_string($v) && $v !== '');
        if (empty($parts)) return $slug;
        return implode(' ', array_map('ucfirst', $parts));
    }

    private function resolveRecipientLabel(string $type, ?string $value): string
    {
        /** @var Database $db */
        $db = $this->container->get(Database::class);

        if ($type === 'role') {
            if ($value === null || $value === '') {
                return 'Anyone with a role';
            }
            $name = $db->scalarString(
                "SELECT `name` FROM `core_roles` WHERE `slug` = ?",
                PDOParams::positional([$value])
            );
            if ($name === '') $name = $this->humanizeSlug($value);
            return "Anyone with the {$name} role";
        }

        if ($type === 'permission') {
            if ($value === null || $value === '') {
                return 'Anyone with a permission';
            }
            $desc = $db->scalarString(
                "SELECT `description` FROM `core_permissions` WHERE `slug` = ?",
                PDOParams::positional([$value])
            );
            if ($desc !== '') {
                return 'Anyone who can ' . lcfirst(trim($desc));
            }
            $fallback = str_replace('.', ' ', $value);
            return 'Anyone who can ' . strtolower($this->humanizeSlug($fallback));
        }

        if ($type === 'user') {
            if ($value === null || $value === '') {
                return 'A specific user';
            }
            $id = (int) $value;
            if ($id > 0) {
                $name = $db->scalarString(
                    "SELECT `name` FROM `core_users` WHERE `id` = ?",
                    PDOParams::positional([$id])
                );
                if ($name !== '') {
                    return "A specific user ({$name})";
                }
            }
            return "A specific user (#{$value})";
        }

        $catalog = $this->recipientTypeCatalog();
        if (isset($catalog[$type])) {
            return $catalog[$type];
        }

        return $this->humanizeSlug($type);
    }

    /** @return array<string,string> */
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

    /** @return array<string,string> */
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

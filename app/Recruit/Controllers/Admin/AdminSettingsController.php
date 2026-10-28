<?php
declare(strict_types=1);

namespace CorporateAid\Recruit\Controllers\Admin;

use CorporateAid\Core\Auth;
use CorporateAid\Core\Container;
use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Core\Session;
use CorporateAid\Core\View;
use CorporateAid\Platform\Services\SystemSettingService;

final class AdminSettingsController
{
    public function __construct(private Container $container) {}

    /**
     * GET /admin/settings — hub with tiles
     */
    /** @param array<string, string> $params */
    public function index(Request $request, array $params = []): Response
    {
        /** @var View $view */
        $view = $this->container->get(View::class);
        /** @var Auth $auth */
        $auth = $this->container->get(Auth::class);

        $html = $view->render('admin/settings/index', [
            'title' => 'System Settings',
            'user'  => $auth->user(),
        ], 'layouts/app');

        return Response::html($html);
    }

    /**
     * GET /admin/settings/security — the security settings form
     *
     * Consumes any pending save left by saveSecurity() when re-auth was
     * required. Without this, a user who saves, gets asked to re-auth,
     * and lands back here would see the old values and no confirmation.
     */
    /** @param array<string, string> $params */
    public function showSecurity(Request $request, array $params = []): Response
    {
        /** @var View $view */
        $view = $this->container->get(View::class);
        /** @var Auth $auth */
        $auth = $this->container->get(Auth::class);
        /** @var SystemSettingService $settings */
        $settings = $this->container->get(SystemSettingService::class);
        /** @var Session $session */
        $session = $this->container->get(Session::class);

        // If a pending save is waiting (from a re-auth round trip), apply it now.
        if ($session->has('_pending_security_save')) {
            $pending = $session->get('_pending_security_save', []);
            $session->forget('_pending_security_save');

            if (is_array($pending) && !empty($pending)) {
                $normalized = [];
                foreach ($pending as $k => $v) {
                    $normalized[(string) $k] = $v;
                }
                [$changes, $errors] = $this->validateAndCollectChanges($normalized);

                if (!empty($errors)) {
                    $session->flash('error', implode(' ', $errors));
                } elseif (!empty($changes)) {
                    $saved = $settings->setMany($changes, 'int');
                    $session->flash('success', "Security settings updated ({$saved} values saved). Changes take effect immediately.");
                }
            }
        }

        $values = $settings->all('security.');
        $success = $session->getFlash('success');
        $error = $session->getFlash('error');

        $html = $view->render('admin/settings/security', [
            'title'    => 'Security Settings',
            'user'     => $auth->user(),
            'settings' => $values,
            'success'  => is_string($success) ? $success : null,
            'error'    => is_string($error) ? $error : null,
            'isSuper'  => $auth->user()?->isSuperAdmin() ?? false,
        ], 'layouts/app');

        return Response::html($html);
    }

    /**
     * POST /admin/settings/security — save
     * Requires super_admin role and re-authentication within the configured window.
     */
    /** @param array<string, string> $params */
    public function saveSecurity(Request $request, array $params = []): Response
    {
        /** @var Auth $auth */
        $auth = $this->container->get(Auth::class);
        /** @var Session $session */
        $session = $this->container->get(Session::class);
        /** @var SystemSettingService $settings */
        $settings = $this->container->get(SystemSettingService::class);

        $user = $auth->user();
        if ($user === null) {
            return Response::redirect('/login');
        }

        // ---- Gate 1: super_admin only ----
        if (!$user->isSuperAdmin()) {
            $session->flash('error', 'Only super administrators can change security settings.');
            return Response::redirect('/admin/settings/security');
        }

        // ---- Gate 2: re-auth within window ----
        $reauthWindow = $settings->getInt('security.session.reauth_window_minutes', 15);
        if (!$session->hasRecentReauth($reauthWindow)) {
            // Preserve ALL submitted form values in session so they can be
            // applied automatically after the user completes re-auth.
            //
            // NOTE: use all(), not input(''). input('') looks up the key ""
            // in the body array, which never exists — it silently returns
            // the default instead of the full payload.
            $session->set('_pending_security_save', $request->all());

            // Redirect to re-auth. After password confirmation the browser
            // is sent back to the GET page for the settings form, which
            // picks up and applies the pending save.
            return Response::redirect(
                '/account/confirm-password?next=' . urlencode('/admin/settings/security')
            );
        }

        // ---- Apply directly ----
        return $this->applySecuritySettings($request);
    }

    /**
     * POST /admin/settings/security/apply
     *
     * Legacy path used when the re-auth flow explicitly re-POSTs the form
     * data. Kept for API/automation cases; the browser flow no longer uses
     * it because the pending-save mechanism on the GET page is cleaner.
     */
    /** @param array<string, string> $params */
    public function applySecurity(Request $request, array $params = []): Response
    {
        /** @var Auth $auth */
        $auth = $this->container->get(Auth::class);

        $user = $auth->user();
        if ($user === null || !$user->isSuperAdmin()) {
            return Response::redirect('/login');
        }

        return $this->applySecuritySettings($request);
    }

    /**
     * Validation rules: [form field => [setting key, min, max, label]]
     */
    private const SECURITY_FIELDS = [
        'login_max_attempts'          => ['security.login.max_attempts',                 1,   100,   'Max login attempts'],
        'login_lockout_minutes'       => ['security.login.lockout_minutes',              1,   1440,  'Lockout duration'],
        'reset_max_attempts'          => ['security.password_reset.max_attempts',        1,   100,   'Reset request max'],
        'reset_window_minutes'        => ['security.password_reset.window_minutes',      1,   10080, 'Reset request window'],
        'reset_submit_max_attempts'   => ['security.password_reset.submit_max_attempts', 1,   100,   'Reset submit max'],
        'reset_submit_window_minutes' => ['security.password_reset.submit_window_minutes', 1, 10080, 'Reset submit window'],
        'password_min_length'         => ['security.password.min_length',                8,   64,    'Password full length'],
        'password_min_length_relaxed' => ['security.password.min_length_relaxed',        8,   64,    'Password relaxed length'],
        'password_require_classes'    => ['security.password.require_classes',           1,   4,     'Required character classes'],
        'session_idle_timeout'        => ['security.session.idle_timeout',               60,  86400, 'Session idle timeout'],
        'session_reauth_window'       => ['security.session.reauth_window_minutes',      1,   120,   'Re-auth window'],
    ];

    /**
     * Validate a submitted payload and return [changes, errors].
     *
     * Shared by the direct save path (applySecuritySettings) and the
     * pending-save path (showSecurity) so both validate identically.
     *
     * @param  array<string, mixed>  $input
     * @return array{0: array<string,int>, 1: array<int,string>}
     */
    /** @param array<string, mixed> $input
     *  @return array{0: array<string, mixed>, 1: string[]} */
    private function validateAndCollectChanges(array $input): array
    {
        $changes = [];
        $errors = [];

        foreach (self::SECURITY_FIELDS as $field => $rule) {
            [$settingKey, $min, $max, $label] = $rule;

            $raw = $input[$field] ?? null;
            if ($raw === null || $raw === '') {
                $errors[] = "{$label}: a value is required.";
                continue;
            }

            if (!is_numeric($raw)) {
                $errors[] = "{$label}: must be a number.";
                continue;
            }

            $value = (int) $raw;

            if ($value < $min) {
                $errors[] = "{$label} must be at least {$min}.";
                continue;
            }
            if ($value > $max) {
                $errors[] = "{$label} must be at most {$max}.";
                continue;
            }

            $changes[$settingKey] = $value;
        }

        return [$changes, $errors];
    }

    /**
     * Shared save logic — validates, then persists.
     */
    private function applySecuritySettings(Request $request): Response
    {
        /** @var SystemSettingService $settings */
        $settings = $this->container->get(SystemSettingService::class);
        /** @var Session $session */
        $session = $this->container->get(Session::class);

        [$changes, $errors] = $this->validateAndCollectChanges($request->all());

        if (!empty($errors)) {
            $session->flash('error', implode(' ', $errors));
            return Response::redirect('/admin/settings/security');
        }

        $saved = $settings->setMany($changes, 'int');
        $session->flash('success', "Security settings updated ({$saved} values saved). Changes take effect immediately.");
        return Response::redirect('/admin/settings/security');
    }
}

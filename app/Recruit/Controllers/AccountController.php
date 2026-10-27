<?php
declare(strict_types=1);

namespace CorporateAid\Recruit\Controllers;

use CorporateAid\Core\Auth;
use CorporateAid\Core\Container;
use CorporateAid\Core\Database;
use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Core\Session;
use CorporateAid\Core\View;
use CorporateAid\Platform\Repositories\UserRepository;
use CorporateAid\Platform\Support\Hasher;
use CorporateAid\Platform\Support\PasswordPolicy;

final class AccountController
{
    private Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function index(Request $request, array $params = []): Response
    {
        /** @var View $view */
        $view = $this->container->get(View::class);
        /** @var Auth $auth */
        $auth = $this->container->get(Auth::class);

        $html = $view->render('account/index', [
            'title' => 'Account',
            'user'  => $auth->user(),
        ], 'layouts/app');

        return Response::html($html);
    }

    public function showChangePassword(Request $request, array $params = []): Response
    {
        /** @var View $view */
        $view = $this->container->get(View::class);
        /** @var Auth $auth */
        $auth = $this->container->get(Auth::class);
        /** @var Session $session */
        $session = $this->container->get(Session::class);

        $error = $session->getFlash('error');
        $success = $session->getFlash('success');

        $html = $view->render('account/password', [
            'title'   => 'Change password',
            'user'    => $auth->user(),
            'error'   => is_string($error) ? $error : null,
            'success' => is_string($success) ? $success : null,
        ], 'layouts/app');

        return Response::html($html);
    }

    public function changePassword(Request $request, array $params = []): Response
    {
        /** @var Auth $auth */
        $auth = $this->container->get(Auth::class);
        /** @var Session $session */
        $session = $this->container->get(Session::class);
        /** @var UserRepository $users */
        $users = $this->container->get(UserRepository::class);

        $current = $request->inputString('current_password', '');
        $new     = $request->inputString('new_password', '');
        $confirm = $request->inputString('confirm_password', '');

        $user = $auth->user();
        if ($user === null) {
            return Response::redirect('/login');
        }

        if ($current === '' || $new === '' || $confirm === '') {
            $session->flash('error', 'Please fill in every field.');
            return Response::redirect('/account/password');
        }

        if (!Hasher::verify($current, $user->passwordHash)) {
            $session->flash('error', 'Your current password is incorrect.');
            return Response::redirect('/account/password');
        }

        $policyErrors = PasswordPolicy::validate($new, $user->email, $user->name);
        if (!empty($policyErrors)) {
            $session->flash('error', implode(' ', $policyErrors));
            return Response::redirect('/account/password');
        }

        if ($new !== $confirm) {
            $session->flash('error', 'New password and confirmation do not match.');
            return Response::redirect('/account/password');
        }

        if (Hasher::verify($new, $user->passwordHash)) {
            $session->flash('error', 'New password must be different from your current password.');
            return Response::redirect('/account/password');
        }

        $users->updatePassword($user->id, $new);

        $session->flash('success', 'Your password has been updated.');
        return Response::redirect('/account');
    }

    /**
     * Save the user's theme preference (dark/light/system).
     * Called via POST /account/theme from the topbar toggle.
     * Returns JSON for AJAX callers, redirects for form-posters.
     */
    public function setTheme(Request $request, array $params = []): Response
    {
        /** @var Auth $auth */
        $auth = $this->container->get(Auth::class);
        /** @var Database $db */
        $db = $this->container->get(Database::class);

        $user = $auth->user();
        if ($user === null) {
            return Response::json(['ok' => false, 'error' => 'not_authenticated'], 401);
        }

        $theme = $request->inputString('theme', 'dark');
        if (!in_array($theme, ['dark', 'light', 'system'], true)) {
            return Response::json(['ok' => false, 'error' => 'invalid_theme'], 422);
        }

        $db->execute(
            "UPDATE `core_users` SET `theme_preference` = ? WHERE `id` = ?",
            [$theme, $user->id]
        );

        // If the request came from JS, return JSON.
        if ($request->wantsJson()) {
            return Response::json(['ok' => true, 'theme' => $theme]);
        }

        return Response::redirect('/account');
    }

    /**
     * GET /account/confirm-password?next=<url>
     * Ask the user to re-enter their password before a sensitive action.
     */
    public function showConfirmPassword(Request $request, array $params = []): Response
    {
        /** @var View $view */
        $view = $this->container->get(View::class);
        /** @var Auth $auth */
        $auth = $this->container->get(Auth::class);
        /** @var Session $session */
        $session = $this->container->get(Session::class);

        $next = (string) $request->query('next', '/dashboard');
        // Only allow relative paths (prevent open redirect)
        if (!str_starts_with($next, '/') || str_starts_with($next, '//')) {
            $next = '/dashboard';
        }

        $error = $session->getFlash('error');

        $html = $view->render('account/confirm-password', [
            'title' => 'Confirm your password',
            'user'  => $auth->user(),
            'next'  => $next,
            'error' => is_string($error) ? $error : null,
        ], 'layouts/app');

        return Response::html($html);
    }

    /**
     * POST /account/confirm-password
     * Verify the password and mark the session as recently re-authenticated.
     */
    public function confirmPassword(Request $request, array $params = []): Response
    {
        /** @var Auth $auth */
        $auth = $this->container->get(Auth::class);
        /** @var Session $session */
        $session = $this->container->get(Session::class);

        $user = $auth->user();
        if ($user === null) {
            return Response::redirect('/login');
        }

        $password = $request->inputString('password', '');
        $next = $request->inputString('next', '/dashboard');

        // Only allow relative paths
        if (!str_starts_with($next, '/') || str_starts_with($next, '//')) {
            $next = '/dashboard';
        }

        if ($password === '') {
            $session->flash('error', 'Please enter your password.');
            return Response::redirect('/account/confirm-password?next=' . urlencode($next));
        }

        if (!\CorporateAid\Platform\Support\Hasher::verify($password, $user->passwordHash)) {
            // Rate limit this too — 3 attempts per 15 min
            /** @var \CorporateAid\Platform\Support\RateLimiter $limiter */
            $limiter = $this->container->get(\CorporateAid\Platform\Support\RateLimiter::class);
            $limiter->hit('confirm_password', $request->ip(), 15);

            $session->flash('error', 'Incorrect password.');
            return Response::redirect('/account/confirm-password?next=' . urlencode($next));
        }

        // Success — clear limiter, mark re-auth
        /** @var \CorporateAid\Platform\Support\RateLimiter $limiter */
        $limiter = $this->container->get(\CorporateAid\Platform\Support\RateLimiter::class);
        $limiter->reset('confirm_password', $request->ip());

        $session->markReauth();

        return Response::redirect($next);
    }
}

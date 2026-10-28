<?php
declare(strict_types=1);

namespace CorporateAid\Recruit\Controllers;

use CorporateAid\Core\Auth;
use CorporateAid\Core\Container;
use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Core\Session;
use CorporateAid\Core\View;
use CorporateAid\Platform\Repositories\PasswordResetRepository;
use CorporateAid\Platform\Repositories\UserRepository;
use CorporateAid\Platform\Support\EmailService;
use CorporateAid\Platform\Support\Hasher;
use CorporateAid\Platform\Support\PasswordPolicy;
use CorporateAid\Platform\Support\RateLimiter;
use CorporateAid\Platform\Support\Logger;

final class PasswordResetController
{

    public function __construct(private Container $container) {}

    /**
     * GET /password/forgot — show the "enter your email" form
     */
    /** @param array<string, string> $params */
    public function showForgot(Request $request, array $params = []): Response
    {
        /** @var View $view */
        $view = $this->container->get(View::class);
        /** @var Session $session */
        $session = $this->container->get(Session::class);

        $html = $view->render('auth/forgot-password', [
            'title'   => 'Forgot password',
            'email'   => $session->old('email', ''),
            'error'   => null,
            'success' => null,
        ], 'layouts/auth');

        return Response::html($html);
    }

    /**
     * POST /password/forgot — validate email, send reset link
     * Always says "check your email" (never reveals whether email exists)
     */
    /** @param array<string, string> $params */
    public function sendResetLink(Request $request, array $params = []): Response
    {
        // Record this attempt (success or failure — prevents enumeration spam)
        /** @var RateLimiter $limiter */
        $limiter = $this->container->get(RateLimiter::class);
        $limiter->hit('password_forgot', $request->ip(), 60);

        /** @var UserRepository $users */
        $users = $this->container->get(UserRepository::class);
        /** @var PasswordResetRepository $resets */
        $resets = $this->container->get(PasswordResetRepository::class);
        /** @var EmailService $mailer */
        $mailer = $this->container->get(EmailService::class);
        /** @var Session $session */
        $session = $this->container->get(Session::class);
        /** @var View $view */
        $view = $this->container->get(View::class);

        $email = strtolower(trim($request->inputString('email', '')));

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $session->flashOldInput(['email' => $email]);
            $html = $view->render('auth/forgot-password', [
                'title'   => 'Forgot password',
                'email'   => $email,
                'error'   => 'Please enter a valid email address.',
                'success' => null,
            ], 'layouts/auth');
            return Response::html($html)->withHeader('X-Form-Error', '1');
        }

        $user = $users->findByEmail($email);

        // Always show success (prevent email enumeration)
        // But only send the email if the user exists and is active
        if ($user !== null && $user->isActive()) {
            try {
                $token = $resets->createToken($email);
                $resetUrl = $this->absoluteUrl('/password/reset/' . $token);

                $sent = $mailer->sendTemplate(
                    $email,
                    'Reset your ' . (is_scalar(config('branding.product_name', 'Recruit Aid')) ? (string) config('branding.product_name', 'Recruit Aid') : 'Recruit Aid') . ' password',
                    'password-reset',
                    [
                        'user_name'  => $user->firstName() ?: $user->name,
                        'reset_url'  => $resetUrl,
                        'app_name'   => config('branding.product_name', 'Recruit Aid'),
                        'expires_in' => '1 hour',
                    ]
                );

                if (!$sent) {
                    Logger::warning('Password reset email failed', ['email' => $email]);
                }
            } catch (\Throwable $e) {
                Logger::error('Password reset creation failed: ' . $e->getMessage(), ['email' => $email]);
            }
        }

        // Flash the submitted email so the view can show a confirmation state
        $session->flash('reset_sent_to', $email);
        return Response::redirect('/password/forgot');
    }

    /**
     * GET /password/reset/{token} — show the reset form
     */
    /** @param array<string, string> $params */
    public function showReset(Request $request, array $params = []): Response
    {
        $token = (string) ($params['token'] ?? '');

        /** @var PasswordResetRepository $resets */
        $resets = $this->container->get(PasswordResetRepository::class);
        /** @var View $view */
        $view = $this->container->get(View::class);
        /** @var Session $session */
        $session = $this->container->get(Session::class);

        $email = $token !== '' ? $resets->findEmailByToken($token) : null;

        if ($email === null) {
            $html = $view->render('auth/reset-password', [
                'title' => 'Reset password',
                'token' => '',
                'email' => '',
                'error' => 'That reset link is invalid or has expired. Please request a new one.',
                'success' => null,
                'invalidToken' => true,
            ], 'layouts/auth');
            return Response::html($html)->withHeader('X-Form-Error', '1');
        }

        $html = $view->render('auth/reset-password', [
            'title' => 'Reset password',
            'token' => $token,
            'email' => $email,
            'error' => null,
            'success' => null,
            'invalidToken' => false,
        ], 'layouts/auth');

        return Response::html($html);
    }

    /**
     * POST /password/reset — change the password
     */
    /** @param array<string, string> $params */
    public function processReset(Request $request, array $params = []): Response
    {
        /** @var RateLimiter $limiter */
        $limiter = $this->container->get(RateLimiter::class);
        $limiter->hit('password_reset', $request->ip(), 60);

        /** @var PasswordResetRepository $resets */
        $resets = $this->container->get(PasswordResetRepository::class);
        /** @var UserRepository $users */
        $users = $this->container->get(UserRepository::class);
        /** @var Session $session */
        $session = $this->container->get(Session::class);
        /** @var View $view */
        $view = $this->container->get(View::class);

        $token    = trim($request->inputString('token', ''));
        $password = $request->inputString('password', '');
        $confirm  = $request->inputString('password_confirm', '');

        $email = $token !== '' ? $resets->findEmailByToken($token) : null;

        if ($email === null) {
            $html = $view->render('auth/reset-password', [
                'title' => 'Reset password',
                'token' => $token,
                'email' => '',
                'error' => 'That reset link is invalid or has expired. Please request a new one.',
                'success' => null,
                'invalidToken' => true,
            ], 'layouts/auth');
            return Response::html($html)->withHeader('X-Form-Error', '1');
        }

        // Validation using central PasswordPolicy
        $user = $users->findByEmail($email);
        $errors = PasswordPolicy::validate($password, $email, $user?->name);

        if ($password !== $confirm) {
            $errors[] = 'Password and confirmation do not match.';
        }

        if (!empty($errors)) {
            $html = $view->render('auth/reset-password', [
                'title' => 'Reset password',
                'token' => $token,
                'email' => $email,
                'error' => implode(' ', $errors),
                'success' => null,
                'invalidToken' => false,
            ], 'layouts/auth');
            return Response::html($html)->withHeader('X-Form-Error', '1');
        }

        // Update password
        if ($user === null) {
            $session->flash('error', 'We could not find your account. Please contact support.');
            return Response::redirect('/password/forgot');
        }

        $users->updatePassword($user->id, $password);
        $resets->consumeToken($token);

        Logger::info('Password reset completed', ['user_id' => $user->id, 'email' => $email]);

        $session->flash('success', 'Your password has been reset. You can now sign in with your new password.');
        return Response::redirect('/login');
    }

    /**
     * Build an absolute URL from a path (uses app.url config).
     */
    private function absoluteUrl(string $path): string
    {
        $base = rtrim(is_scalar(config('app.url', '')) ? (string) config('app.url', '') : '', '/');
        if ($base === '') {
            // Fall back to current request host
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $base = $scheme . '://' . $host;
        }
        return $base . '/' . ltrim($path, '/');
    }
}

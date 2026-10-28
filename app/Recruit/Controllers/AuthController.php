<?php
declare(strict_types=1);

namespace CorporateAid\Recruit\Controllers;

use CorporateAid\Core\Auth;
use CorporateAid\Core\Container;
use CorporateAid\Core\Csrf;
use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Core\Session;
use CorporateAid\Core\View;
use CorporateAid\Platform\Support\RateLimiter;

/**
 * Handles authentication: show login form, process login, log out.
 */
final class AuthController
{
    private Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function showLogin(Request $request, array $params = []): Response
    {
        /** @var View $view */
        $view = $this->container->get(View::class);
        /** @var Session $session */
        $session = $this->container->get(Session::class);

        $intended = $request->queryStringValue('intended', '');
        $oldEmail = is_scalar($session->old('email', '')) ? (string) $session->old('email', '') : '';
        $error = $session->getFlash('error');

        $html = $view->render('auth/login', [
            'title'    => 'Sign in',
            'error'    => is_string($error) ? $error : null,
            'intended' => $intended,
            'email'    => $oldEmail,
        ], 'layouts/auth');

        return Response::html($html);
    }

    public function login(Request $request, array $params = []): Response
    {
        /** @var Auth $auth */
        $auth = $this->container->get(Auth::class);
        /** @var Session $session */
        $session = $this->container->get(Session::class);
        /** @var Csrf $csrf */
        $csrf = $this->container->get(Csrf::class);

        $email    = trim($request->inputString('email', ''));
        $password = $request->inputString('password', '');
        $intended = $request->inputString('intended', '');

        $redirectBack = '/login';
        if ($intended !== '' && str_starts_with($intended, '/')) {
            $redirectBack .= '?intended=' . rawurlencode($intended);
        }

        if ($email === '' || $password === '') {
            $session->flashOldInput(['email' => $email]);
            $session->flash('error', 'Please enter your email and password.');
            return Response::redirect($redirectBack);
        }

        $user = $auth->attempt($email, $password);

        if ($user === null) {
            /** @var RateLimiter $limiter */
            $limiter = $this->container->get(RateLimiter::class);
            $limiter->hit('login', $request->ip(), 15);

            $session->flashOldInput(['email' => $email]);
            $session->flash('error', 'Those credentials do not match our records.');
            return Response::redirect($redirectBack);
        }

        // Successful login — clear the login rate limit bucket
        /** @var RateLimiter $limiter */
        $limiter = $this->container->get(RateLimiter::class);
        $limiter->reset('login', $request->ip());

        $csrf->rotate();
        $session->clearOldInput();

        $target = '/dashboard';
        if ($intended !== '' && str_starts_with($intended, '/')) {
            $target = $intended;
        }

        return Response::redirect($target);
    }

    public function logout(Request $request, array $params = []): Response
    {
        /** @var Auth $auth */
        $auth = $this->container->get(Auth::class);
        /** @var Csrf $csrf */
        $csrf = $this->container->get(Csrf::class);
        /** @var Session $session */
        $session = $this->container->get(Session::class);

        $session->clearReauth();
        $auth->logout();
        $csrf->rotate();

        return Response::redirect('/login');
    }
}

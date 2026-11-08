<?php
declare(strict_types=1);

namespace CorporateAid\Platform\Controllers\Cas;

use CorporateAid\Core\Auth;
use CorporateAid\Core\Container;
use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Core\Session;
use CorporateAid\Core\TenantContext;
use CorporateAid\Core\View;
use CorporateAid\Platform\Services\SystemSettingService;
use CorporateAid\Platform\Support\Hasher;
use CorporateAid\Platform\Support\RateLimiter;

/**
 * Developer context activation.
 *
 * v2: context switching requires a recent password confirmation.
 *
 * The effective-tenant override is a privileged state change — it lets a
 * cas_admin see another tenant's data. Per RBAC-ARCHITECTURE.md §14.5,
 * activation must be an explicit, audited event. This controller enforces
 * step-up re-authentication within the configured window.
 *
 * The reset route does NOT require re-auth — returning to the home tenant
 * is a de-escalation, not a privileged action.
 */
final class CasContextController
{
    public function __construct(private Container $container) {}

    /** @param array<string, string> $params */
    public function switch(Request $request, array $params = []): Response
    {
        /** @var Auth $auth */
        $auth = $this->container->get(Auth::class);
        /** @var Session $session */
        $session = $this->container->get(Session::class);
        /** @var SystemSettingService $settings */
        $settings = $this->container->get(SystemSettingService::class);

        if ($auth->user() === null) {
            return Response::redirect('/login');
        }

        $tenantId = (int) $request->input('tenant_id', 0);
        if ($tenantId <= 0) {
            $session->flash('error', 'Invalid tenant.');
            return Response::redirect('/cas/dashboard');
        }

        // Step-up gate: require recent re-auth before flipping effective tenant.
        $reauthWindow = $settings->getInt('security.session.reauth_window_minutes', 15);
        if (!$session->hasRecentReauth($reauthWindow)) {
            return Response::redirect('/cas/context/confirm?tenant_id=' . $tenantId);
        }

        /** @var TenantContext $tenant */
        $tenant = $this->container->get(TenantContext::class);
        $tenant->setEffective($tenantId);
        $session->flash('success', "Acting as tenant #{$tenantId}.");
        return Response::redirect('/cas/dashboard');
    }

    /** @param array<string, string> $params */
    public function showConfirm(Request $request, array $params = []): Response
    {
        /** @var Auth $auth */
        $auth = $this->container->get(Auth::class);
        /** @var View $view */
        $view = $this->container->get(View::class);

        $user = $auth->user();
        if ($user === null) {
            return Response::redirect('/login');
        }

        $tenantId = (int) $request->query('tenant_id', 0);
        if ($tenantId <= 0) {
            return Response::redirect('/cas/dashboard');
        }

        $html = $view->render('cas/context/confirm', [
            'title'     => 'Confirm password',
            'user'      => $user,
            'tenant_id' => $tenantId,
        ], 'layouts/app');

        return Response::html($html);
    }

    /** @param array<string, string> $params */
    public function confirm(Request $request, array $params = []): Response
    {
        /** @var Auth $auth */
        $auth = $this->container->get(Auth::class);
        /** @var Session $session */
        $session = $this->container->get(Session::class);
        /** @var RateLimiter $limiter */
        $limiter = $this->container->get(RateLimiter::class);

        $user = $auth->user();
        if ($user === null) {
            return Response::redirect('/login');
        }

        $tenantId = (int) $request->input('tenant_id', 0);
        $password = $request->inputString('password', '');

        if ($tenantId <= 0) {
            $session->flash('error', 'Invalid tenant.');
            return Response::redirect('/cas/dashboard');
        }

        if ($password === '') {
            $session->flash('error', 'Please enter your password.');
            return Response::redirect('/cas/context/confirm?tenant_id=' . $tenantId);
        }

        if (!Hasher::verify($password, $user->passwordHash)) {
            $limiter->hit('cas_context_confirm', $request->ip(), 15);
            $session->flash('error', 'Incorrect password.');
            return Response::redirect('/cas/context/confirm?tenant_id=' . $tenantId);
        }

        $limiter->reset('cas_context_confirm', $request->ip());
        $session->markReauth();

        /** @var TenantContext $tenant */
        $tenant = $this->container->get(TenantContext::class);
        $tenant->setEffective($tenantId);
        $session->flash('success', "Acting as tenant #{$tenantId}.");
        return Response::redirect('/cas/dashboard');
    }

    /** @param array<string, string> $params */
    public function reset(Request $request, array $params = []): Response
    {
        /** @var TenantContext $tenant */
        $tenant = $this->container->get(TenantContext::class);
        /** @var Session $session */
        $session = $this->container->get(Session::class);

        $tenant->setEffective($tenant->homeId());
        $session->flash('success', 'Returned to home tenant.');
        return Response::redirect('/cas/dashboard');
    }
}

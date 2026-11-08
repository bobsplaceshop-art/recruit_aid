<?php
declare(strict_types=1);

namespace CorporateAid\Platform\Controllers\Cas;

use CorporateAid\Core\Auth;
use CorporateAid\Core\Container;
use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Core\Session;
use CorporateAid\Core\TenantContext;

/**
 * Developer context activation. Sets the effective tenant for the session.
 *
 * v1 (this file): session-level override, no step-up re-authentication.
 * v2 (future): require password + TOTP, time-bounded, audited per action.
 */
final class CasContextController
{
    public function __construct(private Container $container) {}

    /** @param array<string, string> $params */
    public function switch(Request $request, array $params = []): Response
    {
        /** @var Auth $auth */
        $auth = $this->container->get(Auth::class);
        /** @var TenantContext $tenant */
        $tenant = $this->container->get(TenantContext::class);
        /** @var Session $session */
        $session = $this->container->get(Session::class);

        if ($auth->user() === null) {
            return Response::redirect('/login');
        }

        $tenantId = (int) $request->input('tenant_id', 0);
        if ($tenantId <= 0) {
            $session->flash('error', 'Invalid tenant.');
            return Response::redirect('/cas/dashboard');
        }

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

<?php
declare(strict_types=1);

namespace CorporateAid\Platform\Controllers\Cas;

use CorporateAid\Core\Auth;
use CorporateAid\Core\Container;
use CorporateAid\Core\Database;
use CorporateAid\Core\PDOParams;
use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Core\Row;
use CorporateAid\Core\Session;
use CorporateAid\Core\View;
use CorporateAid\Platform\Authorization\SupportDelegationService;
use InvalidArgumentException;

/**
 * Manage support delegations. Requires cas.rbac.manage_global.
 *
 * The AuthorizationService enforces the delegation grant on every decision.
 * This controller is the CRUD wrapper.
 */
final class CasDelegationController
{
    public function __construct(private Container $container) {}

    /** @param array<string, string> $params */
    public function index(Request $request, array $params = []): Response
    {
        /** @var View $view */
        $view = $this->container->get(View::class);
        /** @var Database $db */
        $db = $this->container->get(Database::class);
        /** @var Auth $auth */
        $auth = $this->container->get(Auth::class);

        $delegations = $db->select(
            "SELECT d.`id`, d.`user_id`, d.`tenant_id`, d.`module_key`,
                    d.`granted_by_type`, d.`scope`, d.`revocation_state`,
                    d.`expires_at`, u.`name` AS user_name, t.`name` AS tenant_name
             FROM `core_support_delegations` d
             JOIN `core_users` u ON u.`id` = d.`user_id`
             JOIN `core_tenants` t ON t.`id` = d.`tenant_id`
             ORDER BY d.`created_at` DESC
             LIMIT 100"
        );

        $html = $view->render('cas/delegations/index', [
            'title'       => 'Support Delegations',
            'user'        => $auth->user(),
            'delegations' => $delegations,
        ], 'layouts/app');

        return Response::html($html);
    }

    /** @param array<string, string> $params */
    public function grantForm(Request $request, array $params = []): Response
    {
        /** @var View $view */
        $view = $this->container->get(View::class);
        /** @var Database $db */
        $db = $this->container->get(Database::class);
        /** @var Auth $auth */
        $auth = $this->container->get(Auth::class);

        $users   = $db->select(
            "SELECT u.`id`, u.`name` FROM `core_users` u
             JOIN `core_user_platform_roles` upr ON upr.`user_id` = u.`id`
             JOIN `core_platform_roles` pr ON pr.`id` = upr.`role_id`
             WHERE pr.`slug` = 'cas_admin' AND u.`status` = 'active'
             ORDER BY u.`name` ASC"
        );
        $tenants = $db->select(
            "SELECT `id`, `name` FROM `core_tenants` WHERE `status` = 'active' ORDER BY `name` ASC"
        );

        $html = $view->render('cas/delegations/grant', [
            'title'   => 'Grant Support Delegation',
            'user'    => $auth->user(),
            'users'   => $users,
            'tenants' => $tenants,
        ], 'layouts/app');

        return Response::html($html);
    }

    /** @param array<string, string> $params */
    public function grant(Request $request, array $params = []): Response
    {
        /** @var Auth $auth */
        $auth = $this->container->get(Auth::class);
        /** @var Session $session */
        $session = $this->container->get(Session::class);
        /** @var SupportDelegationService $delegations */
        $delegations = $this->container->get(SupportDelegationService::class);

        $user = $auth->user();
        if ($user === null) {
            return Response::redirect('/login');
        }

        try {
            $delegations->grant([
                'user_id'            => (int) $request->input('user_id', 0),
                'tenant_id'          => (int) $request->input('tenant_id', 0),
                'module_key'         => (string) $request->input('module_key', 'recruit'),
                'granted_by_user_id' => $user->id,
                'granted_by_type'    => $user->tenantId === 1 ? 'cas_developer' : 'tenant_admin',
                'scope'              => (string) $request->input('scope', 'read_only'),
                'reason'             => (string) $request->input('reason', ''),
                'expires_at'         => (string) $request->input('expires_at', ''),
                'denied_permissions' => $this->parseList($request->input('denied_permissions', '')),
            ]);
            $session->flash('success', 'Delegation created.');
        } catch (InvalidArgumentException $e) {
            $session->flash('error', $e->getMessage());
            return Response::redirect('/cas/delegations/grant');
        }

        return Response::redirect('/cas/delegations');
    }

    /** @param array<string, string> $params */
    public function revoke(Request $request, array $params = []): Response
    {
        /** @var Auth $auth */
        $auth = $this->container->get(Auth::class);
        /** @var Session $session */
        $session = $this->container->get(Session::class);
        /** @var SupportDelegationService $delegations */
        $delegations = $this->container->get(SupportDelegationService::class);

        $user = $auth->user();
        if ($user === null) {
            return Response::redirect('/login');
        }

        try {
            $delegations->revoke((int) ($params['id'] ?? 0), $user->id);
            $session->flash('success', 'Revocation recorded.');
        } catch (InvalidArgumentException $e) {
            $session->flash('error', $e->getMessage());
        }

        return Response::redirect('/cas/delegations');
    }

    /** @param array<string, string> $params */
    public function cancelRevocation(Request $request, array $params = []): Response
    {
        /** @var Auth $auth */
        $auth = $this->container->get(Auth::class);
        /** @var Session $session */
        $session = $this->container->get(Session::class);
        /** @var SupportDelegationService $delegations */
        $delegations = $this->container->get(SupportDelegationService::class);

        $user = $auth->user();
        if ($user === null) {
            return Response::redirect('/login');
        }

        try {
            $delegations->cancelPendingRevocation((int) ($params['id'] ?? 0), $user->id);
            $session->flash('success', 'Pending revocation cancelled.');
        } catch (InvalidArgumentException $e) {
            $session->flash('error', $e->getMessage());
        }

        return Response::redirect('/cas/delegations');
    }

    private function parseList(mixed $raw): array
    {
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }
        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }
}

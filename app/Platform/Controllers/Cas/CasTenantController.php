<?php
declare(strict_types=1);

namespace CorporateAid\Platform\Controllers\Cas;

use CorporateAid\Core\Auth;
use CorporateAid\Core\Container;
use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Core\Row;
use CorporateAid\Core\Session;
use CorporateAid\Core\View;
use CorporateAid\Platform\Repositories\TenantRepository;
use CorporateAid\Platform\Support\RbacAuditLogger;
use InvalidArgumentException;

/**
 * Tenant lifecycle management. Requires cas.tenants.manage.
 *
 * Every mutation writes to core_rbac_audit_logs with:
 *   tenant_id = the tenant being modified
 *   actor_id  = the cas_admin
 *   action    = 'tenant.create' | 'tenant.update' | 'tenant.suspend'
 *             | 'tenant.archive' | 'tenant.restore'
 *   target_type = 'tenant'
 *   target_id   = the tenant id
 */
final class CasTenantController
{
    public function __construct(private Container $container) {}

    /** @param array<string, string> $params */
    public function index(Request $request, array $params = []): Response
    {
        /** @var View $view */
        $view = $this->container->get(View::class);
        /** @var TenantRepository $tenants */
        $tenants = $this->container->get(TenantRepository::class);
        /** @var Auth $auth */
        $auth = $this->container->get(Auth::class);

        $status = (string) $request->query('status', '');
        $validStatus = in_array($status, ['active', 'suspended', 'archived'], true) ? $status : null;
        $rows = $tenants->findAll($validStatus);
        $counts = $tenants->countByStatus();

        $html = $view->render('cas/tenants/index', [
            'title'         => 'Tenants',
            'user'          => $auth->user(),
            'tenants'       => $rows,
            'counts'        => $counts,
            'current_status' => $validStatus,
        ], 'layouts/app');

        return Response::html($html);
    }

    /** @param array<string, string> $params */
    public function createForm(Request $request, array $params = []): Response
    {
        /** @var View $view */
        $view = $this->container->get(View::class);
        /** @var Auth $auth */
        $auth = $this->container->get(Auth::class);
        /** @var Session $session */
        $session = $this->container->get(Session::class);

        $html = $view->render('cas/tenants/form', [
            'title'  => 'Create Tenant',
            'user'   => $auth->user(),
            'tenant' => null,
            'error'  => $session->get('error'),
            'old'    => $session->get('old') ?? [],
        ], 'layouts/app');

        $session->forget('error');
        $session->forget('old');

        return Response::html($html);
    }

    /** @param array<string, string> $params */
    public function create(Request $request, array $params = []): Response
    {
        /** @var TenantRepository $tenants */
        $tenants = $this->container->get(TenantRepository::class);
        /** @var Session $session */
        $session = $this->container->get(Session::class);
        /** @var Auth $auth */
        $auth = $this->container->get(Auth::class);
        /** @var RbacAuditLogger $audit */
        $audit = $this->container->get(RbacAuditLogger::class);

        $user = $auth->user();
        if ($user === null) {
            return Response::redirect('/login');
        }

        try {
            $newId = $tenants->create([
                'code'            => (string) $request->input('code', ''),
                'name'            => (string) $request->input('name', ''),
                'primary_color'   => $this->nullableString($request->input('primary_color')),
                'secondary_color' => $this->nullableString($request->input('secondary_color')),
                'login_message'   => $this->nullableString($request->input('login_message')),
            ]);

            $audit->log(
                tenantId: $newId,
                action: 'tenant.create',
                actorId: $user->id,
                targetType: 'tenant',
                targetId: $newId,
                after: ['code' => (string) $request->input('code', ''), 'name' => (string) $request->input('name', '')]
            );

            $session->flash('success', 'Tenant created.');
            return Response::redirect('/cas/tenants/' . $newId);
        } catch (InvalidArgumentException $e) {
            $session->flash('error', $e->getMessage());
            $session->set('old', [
                'code'            => (string) $request->input('code', ''),
                'name'            => (string) $request->input('name', ''),
                'primary_color'   => (string) $request->input('primary_color', ''),
                'secondary_color' => (string) $request->input('secondary_color', ''),
                'login_message'   => (string) $request->input('login_message', ''),
            ]);
            return Response::redirect('/cas/tenants/create');
        }
    }

    /** @param array<string, string> $params */
    public function show(Request $request, array $params = []): Response
    {
        /** @var View $view */
        $view = $this->container->get(View::class);
        /** @var TenantRepository $tenants */
        $tenants = $this->container->get(TenantRepository::class);
        /** @var Auth $auth */
        $auth = $this->container->get(Auth::class);

        $tenantId = (int) ($params['id'] ?? 0);
        $tenant = $tenants->findById($tenantId);
        if ($tenant === null) {
            return Response::redirect('/cas/tenants');
        }

        $html = $view->render('cas/tenants/show', [
            'title'  => 'Tenant: ' . Row::str($tenant, 'name'),
            'user'   => $auth->user(),
            'tenant' => $tenant,
        ], 'layouts/app');

        return Response::html($html);
    }

    /** @param array<string, string> $params */
    public function editForm(Request $request, array $params = []): Response
    {
        /** @var View $view */
        $view = $this->container->get(View::class);
        /** @var TenantRepository $tenants */
        $tenants = $this->container->get(TenantRepository::class);
        /** @var Auth $auth */
        $auth = $this->container->get(Auth::class);
        /** @var Session $session */
        $session = $this->container->get(Session::class);

        $tenantId = (int) ($params['id'] ?? 0);
        $tenant = $tenants->findById($tenantId);
        if ($tenant === null) {
            return Response::redirect('/cas/tenants');
        }

        $html = $view->render('cas/tenants/form', [
            'title'  => 'Edit Tenant: ' . Row::str($tenant, 'name'),
            'user'   => $auth->user(),
            'tenant' => $tenant,
            'error'  => $session->get('error'),
            'old'    => $session->get('old') ?? [],
        ], 'layouts/app');

        $session->forget('error');
        $session->forget('old');

        return Response::html($html);
    }

    /** @param array<string, string> $params */
    public function update(Request $request, array $params = []): Response
    {
        /** @var TenantRepository $tenants */
        $tenants = $this->container->get(TenantRepository::class);
        /** @var Session $session */
        $session = $this->container->get(Session::class);
        /** @var Auth $auth */
        $auth = $this->container->get(Auth::class);
        /** @var RbacAuditLogger $audit */
        $audit = $this->container->get(RbacAuditLogger::class);

        $user = $auth->user();
        if ($user === null) {
            return Response::redirect('/login');
        }

        $tenantId = (int) ($params['id'] ?? 0);
        $existing = $tenants->findById($tenantId);
        if ($existing === null) {
            return Response::redirect('/cas/tenants');
        }

        try {
            $tenants->update($tenantId, [
                'name'            => (string) $request->input('name', ''),
                'primary_color'   => $this->nullableString($request->input('primary_color')),
                'secondary_color' => $this->nullableString($request->input('secondary_color')),
                'login_message'   => $this->nullableString($request->input('login_message')),
            ]);

            $audit->log(
                tenantId: $tenantId,
                action: 'tenant.update',
                actorId: $user->id,
                targetType: 'tenant',
                targetId: $tenantId,
                before: ['name' => Row::str($existing, 'name')],
                after: ['name' => (string) $request->input('name', '')]
            );

            $session->flash('success', 'Tenant updated.');
            return Response::redirect('/cas/tenants/' . $tenantId);
        } catch (InvalidArgumentException $e) {
            $session->flash('error', $e->getMessage());
            return Response::redirect('/cas/tenants/' . $tenantId . '/edit');
        }
    }

    /** @param array<string, string> $params */
    public function suspend(Request $request, array $params = []): Response
    {
        return $this->transitionStatus($params, 'suspend', 'suspended', 'Tenant suspended.');
    }

    /** @param array<string, string> $params */
    public function archive(Request $request, array $params = []): Response
    {
        return $this->transitionStatus($params, 'archive', 'archived', 'Tenant archived.');
    }

    /** @param array<string, string> $params */
    public function restore(Request $request, array $params = []): Response
    {
        return $this->transitionStatus($params, 'restore', 'active', 'Tenant restored.');
    }

    /**
     * @param array<string, string> $params
     */
    private function transitionStatus(array $params, string $method, string $newStatus, string $successMessage): Response
    {
        /** @var TenantRepository $tenants */
        $tenants = $this->container->get(TenantRepository::class);
        /** @var Session $session */
        $session = $this->container->get(Session::class);
        /** @var Auth $auth */
        $auth = $this->container->get(Auth::class);
        /** @var RbacAuditLogger $audit */
        $audit = $this->container->get(RbacAuditLogger::class);

        $user = $auth->user();
        if ($user === null) {
            return Response::redirect('/login');
        }

        $tenantId = (int) ($params['id'] ?? 0);
        $existing = $tenants->findById($tenantId);
        if ($existing === null) {
            return Response::redirect('/cas/tenants');
        }

        try {
            $tenants->{$method}($tenantId);

            $audit->log(
                tenantId: $tenantId,
                action: 'tenant.' . $method,
                actorId: $user->id,
                targetType: 'tenant',
                targetId: $tenantId,
                before: ['status' => Row::str($existing, 'status')],
                after: ['status' => $newStatus]
            );

            $session->flash('success', $successMessage);
        } catch (InvalidArgumentException $e) {
            $session->flash('error', $e->getMessage());
        }

        return Response::redirect('/cas/tenants/' . $tenantId);
    }

    private function nullableString(mixed $raw): ?string
    {
        if (!is_string($raw)) {
            return null;
        }
        $trim = trim($raw);
        return $trim === '' ? null : $trim;
    }
}

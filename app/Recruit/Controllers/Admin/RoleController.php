<?php
declare(strict_types=1);

namespace CorporateAid\Recruit\Controllers\Admin;

use CorporateAid\Core\Auth;
use CorporateAid\Core\Container;
use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Core\View;
use CorporateAid\Platform\Repositories\RoleRepository;

final class RoleController
{
    private Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * /admin/roles-perm — landing page with shortcut tiles.
     */
    /** @param array<string, string> $params */
    public function home(Request $request, array $params = []): Response
    {
        /** @var View $view */
        $view = $this->container->get(View::class);
        /** @var Auth $auth */
        $auth = $this->container->get(Auth::class);
        /** @var RoleRepository $roles */
        $roles = $this->container->get(RoleRepository::class);

        $allRoles = $roles->all();
        $permissions = $roles->permissionsGroupedByModule();

        $permissionCount = 0;
        foreach ($permissions as $items) {
            $permissionCount += count($items);
        }

        $html = $view->render('admin/roles/landing', [
            'title'           => 'Roles & Permissions',
            'user'            => $auth->user(),
            'roleCount'       => count($allRoles),
            'permissionCount' => $permissionCount,
        ], 'layouts/app');

        return Response::html($html);
    }

    /**
     * /admin/roles-perm/roles — list of roles.
     */
    /** @param array<string, string> $params */
    public function index(Request $request, array $params = []): Response
    {
        /** @var View $view */
        $view = $this->container->get(View::class);
        /** @var Auth $auth */
        $auth = $this->container->get(Auth::class);
        /** @var RoleRepository $roles */
        $roles = $this->container->get(RoleRepository::class);

        $allRoles = $roles->all();

        $html = $view->render('admin/roles/index', [
            'title' => 'Roles',
            'user'  => $auth->user(),
            'roles' => $allRoles,
        ], 'layouts/app');

        return Response::html($html);
    }

    /**
     * /admin/roles-perm/permissions — permission matrix.
     * Placeholder for now; real matrix in a later chunk.
     */
    /** @param array<string, string> $params */
    public function permissions(Request $request, array $params = []): Response
    {
        /** @var View $view */
        $view = $this->container->get(View::class);
        /** @var Auth $auth */
        $auth = $this->container->get(Auth::class);
        /** @var RoleRepository $roles */
        $roles = $this->container->get(RoleRepository::class);

        $allRoles = $roles->all();
        $permissions = $roles->permissionsGroupedByModule();

        $html = $view->render('admin/roles/permissions', [
            'title'       => 'Permissions',
            'user'        => $auth->user(),
            'roles'       => $allRoles,
            'permissions' => $permissions,
        ], 'layouts/app');

        return Response::html($html);
    }
}

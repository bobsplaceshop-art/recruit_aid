<?php
declare(strict_types=1);

namespace CorporateAid\Recruit\Controllers\Admin;

use CorporateAid\Core\Auth;
use CorporateAid\Core\Container;
use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Core\Session;
use CorporateAid\Core\View;
use CorporateAid\Core\Row;
use CorporateAid\Platform\Repositories\RoleRepository;
use CorporateAid\Platform\Repositories\UserRepository;

final class RoleController
{
    private Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

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

    /** @param array<string, string> $params */
    public function index(Request $request, array $params = []): Response
    {
        /** @var View $view */
        $view = $this->container->get(View::class);
        /** @var Auth $auth */
        $auth = $this->container->get(Auth::class);
        /** @var RoleRepository $roles */
        $roles = $this->container->get(RoleRepository::class);

        $html = $view->render('admin/roles/index', [
            'title' => 'Roles',
            'user'  => $auth->user(),
            'roles' => $roles->all(),
        ], 'layouts/app');

        return Response::html($html);
    }

    /**
     * GET /admin/roles-perm/permissions
     * GET /admin/roles-perm/permissions?role_id=X
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
        $roleIdRaw = $request->query('role_id', 0);
        $selectedRoleId = is_numeric($roleIdRaw) ? (int) $roleIdRaw : 0;

        // Default to first role if none selected
        if ($selectedRoleId === 0 && !empty($allRoles)) {
            $selectedRoleId = Row::int($allRoles[0], 'id');
        }

        $selectedPermissions = [];
        if ($selectedRoleId > 0) {
            $selectedPermissions = $roles->permissionIdsForRole($selectedRoleId);
        }

        $html = $view->render('admin/roles/permissions', [
            'title'               => 'Permissions Matrix',
            'user'                => $auth->user(),
            'roles'               => $allRoles,
            'selectedRoleId'      => $selectedRoleId,
            'selectedPermissions' => $selectedPermissions,
            'permissions'         => $roles->permissionsGroupedByModule(),
        ], 'layouts/app');

        return Response::html($html);
    }

    /**
     * POST /admin/roles-perm/permissions
     */
    /** @param array<string, string> $params */
    public function savePermissions(Request $request, array $params = []): Response
    {
        /** @var RoleRepository $roles */
        $roles = $this->container->get(RoleRepository::class);

        $roleId = $request->inputInt('role_id', 0);
        if ($roleId === 0) {
            throw new \RuntimeException('Role ID is required.');
        }

        /** @var int[] $permissionIds */
        $permissionIds = [];
        foreach ((array) ($request->input('permissions') ?? []) as $v) {
            if (is_scalar($v) && (int) $v > 0) {
                $permissionIds[] = (int) $v;
            }
        }

        $roles->syncPermissions($roleId, $permissionIds);

        /** @var Session $session */
        $session = $this->container->get(Session::class);
        $role = $roles->findById($roleId);
        $roleName = $role !== null ? Row::str($role, 'name') : 'role';
        $session->flash('success', 'Permissions saved for "' . $roleName . '".');

        return Response::redirect('/admin/roles-perm/permissions?role_id=' . $roleId);
    }

    /** @param array<string, string> $params */
    public function create(Request $request, array $params = []): Response
    {
        /** @var View $view */
        $view = $this->container->get(View::class);
        /** @var Auth $auth */
        $auth = $this->container->get(Auth::class);

        $html = $view->render('admin/roles/form', [
            'title'  => 'Create Role',
            'user'   => $auth->user(),
            'role'   => null,
            'errors' => [],
        ], 'layouts/app');

        return Response::html($html);
    }

    /** @param array<string, string> $params */
    public function store(Request $request, array $params = []): Response
    {
        /** @var View $view */
        $view = $this->container->get(View::class);
        /** @var Auth $auth */
        $auth = $this->container->get(Auth::class);
        /** @var RoleRepository $roles */
        $roles = $this->container->get(RoleRepository::class);

        $name = trim($request->inputString('name'));
        $slug = preg_replace('/[^a-z0-9_]+/', '_', strtolower($name)) ?? '';
        $description = trim($request->inputString('description'));

        $errors = [];
        if ($name === '') $errors[] = 'Name is required.';
        if ($slug === '') $errors[] = 'Name must produce a valid slug.';
        if ($slug !== '' && $roles->findBySlug($slug) !== null) {
            $errors[] = 'A role with a similar name already exists.';
        }

        if (!empty($errors)) {
            return Response::html($view->render('admin/roles/form', [
                'title'  => 'Create Role',
                'user'   => $auth->user(),
                'role'   => null,
                'errors' => $errors,
            ], 'layouts/app'))->withHeader('X-Form-Error', '1');
        }

        $roles->create([
            'name'        => $name,
            'slug'        => $slug,
            'description' => $description,
        ]);

        /** @var Session $session */
        $session = $this->container->get(Session::class);
        $session->flash('success', 'Role "' . $name . '" created.');

        return Response::redirect('/admin/roles-perm/roles');
    }

    /** @param array<string, string> $params */
    public function edit(Request $request, array $params = []): Response
    {
        /** @var View $view */
        $view = $this->container->get(View::class);
        /** @var Auth $auth */
        $auth = $this->container->get(Auth::class);
        /** @var RoleRepository $roles */
        $roles = $this->container->get(RoleRepository::class);

        $id = (int) ($params['id'] ?? 0);
        $role = $roles->findById($id);
        if ($role === null) {
            throw new \RuntimeException('Role not found.');
        }

        /** @var UserRepository $userRepo */
        $userRepo = $this->container->get(UserRepository::class);
        $assignedUsers = $userRepo->findUsersByRoleId($id);

        $html = $view->render('admin/roles/form', [
            'title'         => 'Edit Role: ' . Row::str($role, 'name'),
            'user'          => $auth->user(),
            'role'          => $role,
            'assignedUsers' => $assignedUsers,
            'errors'        => [],
        ], 'layouts/app');

        return Response::html($html);
    }

    /** @param array<string, string> $params */
    public function update(Request $request, array $params = []): Response
    {
        /** @var View $view */
        $view = $this->container->get(View::class);
        /** @var Auth $auth */
        $auth = $this->container->get(Auth::class);
        /** @var RoleRepository $roles */
        $roles = $this->container->get(RoleRepository::class);

        $id = (int) ($params['id'] ?? 0);
        $role = $roles->findById($id);
        if ($role === null) {
            throw new \RuntimeException('Role not found.');
        }

        $name = trim($request->inputString('name'));
        $description = trim($request->inputString('description'));

        $errors = [];
        if ($name === '') $errors[] = 'Name is required.';

        if (!empty($errors)) {
            return Response::html($view->render('admin/roles/form', [
                'title'  => 'Edit Role: ' . Row::str($role, 'name'),
                'user'   => $auth->user(),
                'role'   => array_merge($role, ['name' => $name, 'description' => $description]),
                'errors' => $errors,
            ], 'layouts/app'))->withHeader('X-Form-Error', '1');
        }

        $roles->update($id, ['name' => $name, 'description' => $description]);

        /** @var Session $session */
        $session = $this->container->get(Session::class);
        $session->flash('success', 'Role "' . $name . '" updated.');

        return Response::redirect('/admin/roles-perm/roles');
    }

    /**
     * Remove a user from a role.
     * POST /admin/roles-perm/roles/{id}/users/{userId}/remove
     */
    /** @param array<string, string> $params */
    public function removeUser(Request $request, array $params = []): Response
    {
        /** @var RoleRepository $roles */
        $roles = $this->container->get(RoleRepository::class);
        /** @var UserRepository $users */
        $users = $this->container->get(UserRepository::class);
        /** @var Session $session */
        $session = $this->container->get(Session::class);

        $roleId = (int) ($params['id'] ?? 0);
        $userId = (int) ($params['userId'] ?? 0);

        $role = $roles->findById($roleId);
        $user = $users->findById($userId);

        if ($role === null || $user === null) {
            $session->flash('error', 'Role or user not found.');
            return Response::redirect('/admin/roles-perm/roles');
        }

        // Remove just this role from the user's assignments (keep other roles)
        $currentRoleIds = $users->roleIdsForUser($userId);
        $newRoleIds = array_values(array_filter(
            $currentRoleIds,
            fn(int $rid) => $rid !== $roleId
        ));
        $users->syncRoles($userId, $newRoleIds);

        $session->flash('success',
            'Removed "' . $user->name . '" from role "' . Row::str($role, 'name') . '".'
        );

        return Response::redirect('/admin/roles-perm/roles/' . $roleId . '/edit');
    }

    /** @param array<string, string> $params */
    public function destroy(Request $request, array $params = []): Response
    {
        /** @var RoleRepository $roles */
        $roles = $this->container->get(RoleRepository::class);
        /** @var Session $session */
        $session = $this->container->get(Session::class);

        $id = (int) ($params['id'] ?? 0);
        $role = $roles->findById($id);
        if ($role === null) {
            $session->flash('error', 'Role not found.');
            return Response::redirect('/admin/roles-perm/roles');
        }
        if (Row::bool($role, 'is_system')) {
            $session->flash('error', 'Cannot delete a system role.');
            return Response::redirect('/admin/roles-perm/roles');
        }

        $userCount = $roles->userCountForRole($id);
        if ($userCount > 0) {
            $session->flash('error',
                'Cannot delete "' . Row::str($role, 'name') . '" — it is assigned to '
                . $userCount . ' user' . ($userCount === 1 ? '' : 's')
                . '. Remove it from those users first.'
            );
            return Response::redirect('/admin/roles-perm/roles');
        }

        $roleName = Row::str($role, 'name');
        $roles->delete($id);
        $session->flash('success', 'Role "' . $roleName . '" deleted.');

        return Response::redirect('/admin/roles-perm/roles');
    }
}

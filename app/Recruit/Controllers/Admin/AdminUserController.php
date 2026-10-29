<?php
declare(strict_types=1);

namespace CorporateAid\Recruit\Controllers\Admin;

use CorporateAid\Core\Auth;
use CorporateAid\Core\Container;
use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Core\Row;
use CorporateAid\Core\Session;
use CorporateAid\Core\View;
use CorporateAid\Platform\Repositories\RoleRepository;
use CorporateAid\Platform\Repositories\UserRepository;

final class AdminUserController
{
    public function __construct(private Container $container) {}

    /** @param array<string, string> $params */
    public function index(Request $request, array $params = []): Response
    {
        /** @var View $view */
        $view = $this->container->get(View::class);
        /** @var Auth $auth */
        $auth = $this->container->get(Auth::class);
        /** @var UserRepository $users */
        $users = $this->container->get(UserRepository::class);

        $html = $view->render('admin/users/index', [
            'title' => 'Users',
            'user'  => $auth->user(),
            'users' => $users->listAll(),
        ], 'layouts/app');

        return Response::html($html);
    }

    /** @param array<string, string> $params */
    public function create(Request $request, array $params = []): Response
    {
        /** @var View $view */
        $view = $this->container->get(View::class);
        /** @var Auth $auth */
        $auth = $this->container->get(Auth::class);
        /** @var RoleRepository $roles */
        $roles = $this->container->get(RoleRepository::class);

        $html = $view->render('admin/users/form', [
            'title'         => 'Create User',
            'user'          => $auth->user(),
            'editing'       => null,
            'allRoles'      => $roles->all(),
            'selectedRoles' => [],
            'errors'        => [],
        ], 'layouts/app');

        return Response::html($html);
    }

    /** @param array<string, string> $params */
    public function store(Request $request, array $params = []): Response
    {
        /** @var UserRepository $users */
        $users = $this->container->get(UserRepository::class);
        /** @var RoleRepository $roles */
        $roles = $this->container->get(RoleRepository::class);
        /** @var Session $session */
        $session = $this->container->get(Session::class);
        /** @var View $view */
        $view = $this->container->get(View::class);
        /** @var Auth $auth */
        $auth = $this->container->get(Auth::class);

        $name     = trim($request->inputString('name'));
        $email    = strtolower(trim($request->inputString('email')));
        $password = $request->inputString('password');
        $status   = $request->inputString('status', 'active');
        $roleIds  = $this->parseRoleIds($request);

        $errors = [];
        if ($name === '') $errors[] = 'Name is required.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';
        if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';
        if ($users->findByEmail($email) !== null) $errors[] = 'A user with that email already exists.';
        if (!in_array($status, ['active', 'pending', 'suspended', 'disabled'], true)) {
            $errors[] = 'Invalid status.';
        }

        if (!empty($errors)) {
            return Response::html($view->render('admin/users/form', [
                'title'         => 'Create User',
                'user'          => $auth->user(),
                'editing'       => null,
                'allRoles'      => $roles->all(),
                'selectedRoles' => $roleIds,
                'errors'        => $errors,
            ], 'layouts/app'))->withHeader('X-Form-Error', '1');
        }

        $userId = $users->create([
            'name'     => $name,
            'email'    => $email,
            'password' => $password,
            'status'   => $status,
        ]);
        $users->syncRoles($userId, $roleIds);

        $session->flash('success', 'User "' . $name . '" created.');
        return Response::redirect('/admin/users');
    }

    /** @param array<string, string> $params */
    public function edit(Request $request, array $params = []): Response
    {
        /** @var View $view */
        $view = $this->container->get(View::class);
        /** @var Auth $auth */
        $auth = $this->container->get(Auth::class);
        /** @var UserRepository $users */
        $users = $this->container->get(UserRepository::class);
        /** @var RoleRepository $roles */
        $roles = $this->container->get(RoleRepository::class);

        $id = (int) ($params['id'] ?? 0);
        $editing = $users->findById($id);
        if ($editing === null) {
            throw new \RuntimeException('User not found.');
        }

        $html = $view->render('admin/users/form', [
            'title'         => 'Edit User: ' . $editing->name,
            'user'          => $auth->user(),
            'editing'       => $editing,
            'allRoles'      => $roles->all(),
            'selectedRoles' => $users->roleIdsForUser($id),
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
        /** @var UserRepository $users */
        $users = $this->container->get(UserRepository::class);
        /** @var RoleRepository $roles */
        $roles = $this->container->get(RoleRepository::class);
        /** @var Session $session */
        $session = $this->container->get(Session::class);

        $id = (int) ($params['id'] ?? 0);
        $editing = $users->findById($id);
        if ($editing === null) {
            throw new \RuntimeException('User not found.');
        }

        $name   = trim($request->inputString('name'));
        $email  = strtolower(trim($request->inputString('email')));
        $status = $request->inputString('status', 'active');
        $roleIds = $this->parseRoleIds($request);

        $errors = [];
        if ($name === '') $errors[] = 'Name is required.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';
        $existing = $users->findByEmail($email);
        if ($existing !== null && $existing->id !== $id) $errors[] = 'Another user already has that email.';
        if (!in_array($status, ['active', 'pending', 'suspended', 'disabled'], true)) {
            $errors[] = 'Invalid status.';
        }

        // Optional password change
        $password = $request->inputString('password');
        if ($password !== '' && strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters (or leave blank to keep current).';
        }

        if (!empty($errors)) {
            return Response::html($view->render('admin/users/form', [
                'title'         => 'Edit User: ' . $editing->name,
                'user'          => $auth->user(),
                'editing'       => $editing,
                'allRoles'      => $roles->all(),
                'selectedRoles' => $roleIds,
                'errors'        => $errors,
            ], 'layouts/app'))->withHeader('X-Form-Error', '1');
        }

        $users->update($id, ['name' => $name, 'email' => $email, 'status' => $status]);
        $users->syncRoles($id, $roleIds);
        if ($password !== '') {
            $users->updatePassword($id, $password);
        }

        $session->flash('success', 'User "' . $name . '" updated.');
        return Response::redirect('/admin/users');
    }

    /**
     * @return int[]
     */
    private function parseRoleIds(Request $request): array
    {
        $ids = [];
        foreach ((array) ($request->input('roles') ?? []) as $v) {
            if (is_scalar($v) && (int) $v > 0) {
                $ids[] = (int) $v;
            }
        }
        return $ids;
    }
}

<?php
declare(strict_types=1);

namespace CorporateAid\Recruit\Controllers\Admin;

use CorporateAid\Core\Auth;
use CorporateAid\Core\Container;
use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Core\Session;
use CorporateAid\Core\View;
use CorporateAid\Platform\Repositories\LookupRepository;
use CorporateAid\Platform\Services\LookupService;

final class LookupController
{
    public function __construct(private Container $container) {}

    /** @param array<string, string> $params */
    public function index(Request $request, array $params = []): Response
    {
        /** @var View $view */
        $view = $this->container->get(View::class);
        /** @var Auth $auth */
        $auth = $this->container->get(Auth::class);
        /** @var LookupService $lookups */
        $lookups = $this->container->get(LookupService::class);

        $html = $view->render('admin/lookups/index', [
            'title'  => 'Lookups',
            'user'   => $auth->user(),
            'groups' => $lookups->allGroups(),
        ], 'layouts/app');

        return Response::html($html);
    }

    /** @param array<string, string> $params */
    public function show(Request $request, array $params = []): Response
    {
        /** @var View $view */
        $view = $this->container->get(View::class);
        /** @var Auth $auth */
        $auth = $this->container->get(Auth::class);
        /** @var LookupService $lookups */
        $lookups = $this->container->get(LookupService::class);

        $group = (string) ($params['group'] ?? '');
        $items = $lookups->itemsFor($group);
        $groupLabel = $lookups->groupLabel($group);

        $html = $view->render('admin/lookups/group', [
            'title'      => $groupLabel,
            'user'       => $auth->user(),
            'group'      => $group,
            'groupLabel' => $groupLabel,
            'items'      => $items,
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
        /** @var LookupService $lookups */
        $lookups = $this->container->get(LookupService::class);

        $group = (string) ($params['group'] ?? '');

        $html = $view->render('admin/lookups/form', [
            'title'      => 'Create ' . $lookups->groupLabel($group),
            'user'       => $auth->user(),
            'group'      => $group,
            'groupLabel' => $lookups->groupLabel($group),
            'item'       => null,
            'errors'     => [],
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
        /** @var LookupService $lookups */
        $lookups = $this->container->get(LookupService::class);
        /** @var LookupRepository $repo */
        $repo = $this->container->get(LookupRepository::class);
        /** @var Session $session */
        $session = $this->container->get(Session::class);

        $group = (string) ($params['group'] ?? '');
        $slug        = preg_replace('/[^a-z0-9_]+/', '_', strtolower(trim($request->inputString('slug')))) ?? '';
        $label       = trim($request->inputString('label'));
        $description = trim($request->inputString('description'));
        $sortOrder   = $request->inputInt('sort_order', 0);
        $isActive    = $request->inputBool('is_active', true);

        $errors = [];
        if ($label === '') $errors[] = 'Label is required.';
        if ($slug === '') $errors[] = 'Slug is required (letters, numbers, underscore).';
        if ($slug !== '' && $repo->slugExistsInGroup($group, $slug)) {
            $errors[] = 'A value with this slug already exists in this group.';
        }

        if (!empty($errors)) {
            return Response::html($view->render('admin/lookups/form', [
                'title'      => 'Create ' . $lookups->groupLabel($group),
                'user'       => $auth->user(),
                'group'      => $group,
                'groupLabel' => $lookups->groupLabel($group),
                'item'       => [
                    'slug'        => $slug,
                    'label'       => $label,
                    'description' => $description,
                    'sort_order'  => $sortOrder,
                    'is_active'   => $isActive,
                ],
                'errors' => $errors,
            ], 'layouts/app'))->withHeader('X-Form-Error', '1');
        }

        $repo->create([
            'group_slug'  => $group,
            'slug'        => $slug,
            'label'       => $label,
            'description' => $description !== '' ? $description : null,
            'sort_order'  => $sortOrder,
            'is_active'   => $isActive,
            'is_system'   => false,
        ]);

        $session->flash('success', $lookups->groupLabel($group) . ' value "' . $label . '" created.');
        return Response::redirect('/admin/lookups/' . $group);
    }

    /** @param array<string, string> $params */
    public function edit(Request $request, array $params = []): Response
    {
        /** @var View $view */
        $view = $this->container->get(View::class);
        /** @var Auth $auth */
        $auth = $this->container->get(Auth::class);
        /** @var LookupService $lookups */
        $lookups = $this->container->get(LookupService::class);
        /** @var LookupRepository $repo */
        $repo = $this->container->get(LookupRepository::class);

        $group = (string) ($params['group'] ?? '');
        $id = (int) ($params['id'] ?? 0);
        $item = $repo->findById($id);

        if ($item === null || $item['group_slug'] !== $group) {
            throw new \RuntimeException('Lookup value not found.');
        }

        $html = $view->render('admin/lookups/form', [
            'title'      => 'Edit ' . $lookups->groupLabel($group),
            'user'       => $auth->user(),
            'group'      => $group,
            'groupLabel' => $lookups->groupLabel($group),
            'item'       => $item,
            'errors'     => [],
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
        /** @var LookupService $lookups */
        $lookups = $this->container->get(LookupService::class);
        /** @var LookupRepository $repo */
        $repo = $this->container->get(LookupRepository::class);
        /** @var Session $session */
        $session = $this->container->get(Session::class);

        $group = (string) ($params['group'] ?? '');
        $id = (int) ($params['id'] ?? 0);
        $item = $repo->findById($id);

        if ($item === null || $item['group_slug'] !== $group) {
            throw new \RuntimeException('Lookup value not found.');
        }

        $label       = trim($request->inputString('label'));
        $description = trim($request->inputString('description'));
        $sortOrder   = $request->inputInt('sort_order', 0);
        $isActive    = $request->inputBool('is_active', true);

        $errors = [];
        if ($label === '') $errors[] = 'Label is required.';

        if (!empty($errors)) {
            return Response::html($view->render('admin/lookups/form', [
                'title'      => 'Edit ' . $lookups->groupLabel($group),
                'user'       => $auth->user(),
                'group'      => $group,
                'groupLabel' => $lookups->groupLabel($group),
                'item'       => array_merge($item, ['label' => $label, 'description' => $description, 'sort_order' => $sortOrder, 'is_active' => $isActive]),
                'errors'     => $errors,
            ], 'layouts/app'))->withHeader('X-Form-Error', '1');
        }

        $repo->update($id, [
            'label'       => $label,
            'description' => $description !== '' ? $description : null,
            'sort_order'  => $sortOrder,
            'is_active'   => $isActive,
        ]);

        $session->flash('success', $lookups->groupLabel($group) . ' value "' . $label . '" updated.');
        return Response::redirect('/admin/lookups/' . $group);
    }

    /** @param array<string, string> $params */
    public function destroy(Request $request, array $params = []): Response
    {
        /** @var LookupRepository $repo */
        $repo = $this->container->get(LookupRepository::class);
        /** @var LookupService $lookups */
        $lookups = $this->container->get(LookupService::class);
        /** @var Session $session */
        $session = $this->container->get(Session::class);

        $group = (string) ($params['group'] ?? '');
        $id = (int) ($params['id'] ?? 0);
        $item = $repo->findById($id);

        if ($item === null || $item['group_slug'] !== $group) {
            $session->flash('error', 'Lookup value not found.');
            return Response::redirect('/admin/lookups/' . $group);
        }

        if ($item['is_system']) {
            $session->flash('error', 'Cannot delete a system-provided value. You can deactivate it instead.');
            return Response::redirect('/admin/lookups/' . $group);
        }

        $repo->softDelete($id);
        $session->flash('success', 'Value "' . $item['label'] . '" deleted.');
        return Response::redirect('/admin/lookups/' . $group);
    }
}

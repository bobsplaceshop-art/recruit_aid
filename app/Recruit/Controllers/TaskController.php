<?php
declare(strict_types=1);

namespace CorporateAid\Recruit\Controllers;

use CorporateAid\Core\Auth;
use CorporateAid\Core\Container;
use CorporateAid\Core\Exceptions\HttpException;
use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Core\Session;
use CorporateAid\Core\View;
use CorporateAid\Recruit\Repositories\TaskRepository;
use CorporateAid\Recruit\Services\TaskService;
use InvalidArgumentException;

/**
 * Task CRUD controller.
 *
 * Every method delegates to TaskService for validation + business rules.
 * The controller is a thin HTTP adapter: collect input, call service,
 * render or redirect based on the result.
 */
final class TaskController
{
    public function __construct(private Container $container) {}

    /** @param array<string, string> $params */
    public function index(Request $request, array $params = []): Response
    {
        /** @var View $view */
        $view = $this->container->get(View::class);
        /** @var Auth $auth */
        $auth = $this->container->get(Auth::class);
        /** @var TaskRepository $tasks */
        $tasks = $this->container->get(TaskRepository::class);

        $user = $auth->user();
        if ($user === null) {
            return Response::redirect('/login');
        }

        $filters = [
            'status'       => (string) $request->queryStringValue('status', ''),
            'priority'     => (string) $request->queryStringValue('priority', ''),
            'assigned_to'  => (int) $request->queryInt('assigned_to', 0),
            'entity_type'  => (string) $request->queryStringValue('entity_type', ''),
            'entity_id'    => (int) $request->queryInt('entity_id', 0),
            'due_filter'   => (string) $request->queryStringValue('due_filter', ''),
        ];
        $page = max(1, $request->queryInt('page', 1));

        // Clean empty-string filters so the repository's in_array checks work.
        $cleanFilters = [];
        foreach ($filters as $k => $v) {
            if ($v !== '' && $v !== 0) {
                $cleanFilters[$k] = $v;
            }
        }
        // Default to "assigned to me" if no assignee filter is given? No —
        // default to showing all open tasks for the tenant. Users can
        // filter to "assigned to me" explicitly.
        if (!isset($cleanFilters['status'])) {
            $cleanFilters['status'] = 'open';
        }

        $result = $tasks->findAll($cleanFilters, $page, 50);
        $overdue = $tasks->countOverdueForUser($user->id);
        $openMine = $tasks->countOpenForUser($user->id);

        $html = $view->render('tasks/index', [
            'title'       => 'Tasks',
            'user'        => $user,
            'result'      => $result,
            'filters'     => $cleanFilters,
            'overdue'     => $overdue,
            'open_mine'   => $openMine,
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

        $html = $view->render('tasks/form', [
            'title'  => 'New Task',
            'user'   => $auth->user(),
            'task'   => null,
            'old'    => [],
            'errors' => [],
        ], 'layouts/app');

        return Response::html($html);
    }

    /** @param array<string, string> $params */
    public function create(Request $request, array $params = []): Response
    {
        /** @var TaskService $service */
        $service = $this->container->get(TaskService::class);
        /** @var Session $session */
        $session = $this->container->get(Session::class);
        /** @var View $view */
        $view = $this->container->get(View::class);
        /** @var Auth $auth */
        $auth = $this->container->get(Auth::class);

        $user = $auth->user();
        if ($user === null) {
            return Response::redirect('/login');
        }

        $input = $this->collectInput($request);

        try {
            $result = $service->create($input, $user->id);
        } catch (InvalidArgumentException $e) {
            $result = ['id' => 0, 'errors' => [$e->getMessage()]];
        }

        if (!empty($result['errors'])) {
            $html = $view->render('tasks/form', [
                'title'  => 'New Task',
                'user'   => $user,
                'task'   => null,
                'old'    => $input,
                'errors' => $result['errors'],
            ], 'layouts/app');
            return Response::html($html);
        }

        $session->flash('success', 'Task created.');
        return Response::redirect('/recruit/tasks');
    }

    /** @param array<string, string> $params */
    public function complete(Request $request, array $params = []): Response
    {
        return $this->transition($params, 'complete', 'Task completed.');
    }

    /** @param array<string, string> $params */
    public function reopen(Request $request, array $params = []): Response
    {
        return $this->transition($params, 'reopen', 'Task reopened.');
    }

    /** @param array<string, string> $params */
    public function delete(Request $request, array $params = []): Response
    {
        return $this->transition($params, 'delete', 'Task deleted.');
    }

    /**
     * Shared implementation for complete/reopen/delete. Each is a state
     * transition that throws on invalid state; the controller catches and
     * flashes, then redirects back to the list.
     *
     * @param array<string, string> $params
     */
    private function transition(array $params, string $method, string $successMessage): Response
    {
        /** @var TaskService $service */
        $service = $this->container->get(TaskService::class);
        /** @var Session $session */
        $session = $this->container->get(Session::class);
        /** @var Auth $auth */
        $auth = $this->container->get(Auth::class);

        if ($auth->user() === null) {
            return Response::redirect('/login');
        }

        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            $session->flash('error', 'Invalid task.');
            return Response::redirect('/recruit/tasks');
        }

        try {
            $service->{$method}($id);
            $session->flash('success', $successMessage);
        } catch (InvalidArgumentException $e) {
            $session->flash('error', $e->getMessage());
        }

        return Response::redirect('/recruit/tasks');
    }

    /** @return array<string, mixed> */
    private function collectInput(Request $request): array
    {
        return [
            'title'       => $request->inputString('title'),
            'description' => $request->inputString('description'),
            'priority'    => $request->inputString('priority', 'normal'),
            'due_at'      => $request->inputString('due_at'),
            'assigned_to' => $request->inputInt('assigned_to', 0),
            'entity_type' => $request->inputString('entity_type'),
            'entity_id'   => $request->inputInt('entity_id', 0),
        ];
    }
}

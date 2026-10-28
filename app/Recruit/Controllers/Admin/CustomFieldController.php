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
use CorporateAid\Platform\Repositories\CustomFieldRepository;
use CorporateAid\Platform\Services\CustomFieldService;
use CorporateAid\Platform\CustomFields\InputTypeRegistry;

final class CustomFieldController
{
    public const ENTITIES = [
        'organisation' => 'Organisations',
        'contact'      => 'Contacts',
        'engagement'   => 'Engagements',
        'vacancy'      => 'Vacancies',
        'candidate'    => 'Candidates',
        'application'  => 'Applications',
    ];

    public function __construct(private Container $container) {}

    public function index(Request $request, array $params = []): Response
    {
        /** @var View $view */
        $view = $this->container->get(View::class);
        /** @var Auth $auth */
        $auth = $this->container->get(Auth::class);
        /** @var CustomFieldRepository $fields */
        $fields = $this->container->get(CustomFieldRepository::class);

        $counts = [];
        foreach (self::ENTITIES as $key => $label) {
            $all = $fields->allFor($key);
            $counts[$key] = [
                'label'  => $label,
                'active' => count(array_filter($all, fn ($f) => $f->isActive)),
                'total'  => count($all),
            ];
        }

        $html = $view->render('admin/custom-fields/index', [
            'title'  => 'Custom Fields',
            'user'   => $auth->user(),
            'counts' => $counts,
        ], 'layouts/app');

        return Response::html($html);
    }

    public function listFor(Request $request, array $params = []): Response
    {
        $entityKey = Row::str($params, 'entity');
        if (!isset(self::ENTITIES[$entityKey])) {
            return Response::redirect('/admin/custom-fields');
        }

        /** @var View $view */
        $view = $this->container->get(View::class);
        /** @var Auth $auth */
        $auth = $this->container->get(Auth::class);
        /** @var CustomFieldRepository $fields */
        $fields = $this->container->get(CustomFieldRepository::class);

        $html = $view->render('admin/custom-fields/list', [
            'title'       => self::ENTITIES[$entityKey] . ' — Custom Fields',
            'user'        => $auth->user(),
            'entityKey'   => $entityKey,
            'entityLabel' => self::ENTITIES[$entityKey],
            'fields'      => $fields->allFor($entityKey),
            'typeLabels'  => array_map(fn ($t) => $t->label(), InputTypeRegistry::all()),
        ], 'layouts/app');

        return Response::html($html);
    }

    public function showCreate(Request $request, array $params = []): Response
    {
        $entityKey = Row::str($params, 'entity');
        if (!isset(self::ENTITIES[$entityKey])) {
            return Response::redirect('/admin/custom-fields');
        }

        /** @var View $view */
        $view = $this->container->get(View::class);
        /** @var Auth $auth */
        $auth = $this->container->get(Auth::class);

        $html = $view->render('admin/custom-fields/form', [
            'title'       => 'New Custom Field',
            'user'        => $auth->user(),
            'entityKey'   => $entityKey,
            'entityLabel' => self::ENTITIES[$entityKey],
            'field'       => null,
            'inputTypes'  => InputTypeRegistry::optionsForSelect(),
            'errors'      => [],
            'old'         => [],
            'pageScripts' => ['js/custom-fields.js'],
        ], 'layouts/app');

        return Response::html($html);
    }

    public function store(Request $request, array $params = []): Response
    {
        $entityKey = Row::str($params, 'entity');
        if (!isset(self::ENTITIES[$entityKey])) {
            return Response::redirect('/admin/custom-fields');
        }

        /** @var CustomFieldService $service */
        $service = $this->container->get(CustomFieldService::class);
        /** @var Session $session */
        $session = $this->container->get(Session::class);
        /** @var View $view */
        $view = $this->container->get(View::class);
        /** @var Auth $auth */
        $auth = $this->container->get(Auth::class);

        $input = $this->collectInput($request);

        try {
            $service->create($entityKey, $input);
            $session->flash('success', 'Custom field created successfully.');
            return Response::redirect('/admin/custom-fields/' . $entityKey);
        } catch (\RuntimeException $e) {
            $html = $view->render('admin/custom-fields/form', [
                'title'       => 'New Custom Field',
                'user'        => $auth->user(),
                'entityKey'   => $entityKey,
                'entityLabel' => self::ENTITIES[$entityKey],
                'field'       => null,
                'inputTypes'  => InputTypeRegistry::optionsForSelect(),
                'errors'      => [$e->getMessage()],
                'old'         => $input,
                'pageScripts' => ['js/custom-fields.js'],
            ], 'layouts/app');
            return Response::html($html)->withHeader('X-Form-Error', '1');
        }
    }

    public function showEdit(Request $request, array $params = []): Response
    {
        $entityKey = Row::str($params, 'entity');
        $fieldId   = Row::int($params, 'id');

        if (!isset(self::ENTITIES[$entityKey])) {
            return Response::redirect('/admin/custom-fields');
        }

        /** @var CustomFieldRepository $fields */
        $fields = $this->container->get(CustomFieldRepository::class);
        $field = $fields->findById($fieldId);

        if (!$field || $field->entityType !== $entityKey) {
            return Response::redirect('/admin/custom-fields/' . $entityKey);
        }

        /** @var View $view */
        $view = $this->container->get(View::class);
        /** @var Auth $auth */
        $auth = $this->container->get(Auth::class);

        $html = $view->render('admin/custom-fields/form', [
            'title'       => 'Edit Custom Field',
            'user'        => $auth->user(),
            'entityKey'   => $entityKey,
            'entityLabel' => self::ENTITIES[$entityKey],
            'field'       => $field,
            'inputTypes'  => InputTypeRegistry::optionsForSelect(),
            'errors'      => [],
            'old'         => [],
            'pageScripts' => ['js/custom-fields.js'],
        ], 'layouts/app');

        return Response::html($html);
    }

    public function update(Request $request, array $params = []): Response
    {
        $entityKey = Row::str($params, 'entity');
        $fieldId   = Row::int($params, 'id');

        /** @var CustomFieldService $service */
        $service = $this->container->get(CustomFieldService::class);
        /** @var Session $session */
        $session = $this->container->get(Session::class);

        $input = $this->collectInput($request);

        try {
            $service->update($fieldId, $input);
            $session->flash('success', 'Custom field updated.');
        } catch (\RuntimeException $e) {
            $session->flash('error', $e->getMessage());
        }

        return Response::redirect('/admin/custom-fields/' . $entityKey);
    }

    public function archive(Request $request, array $params = []): Response
    {
        return $this->simpleAction($request, $params, 'archive', 'Custom field archived.');
    }

    public function restore(Request $request, array $params = []): Response
    {
        return $this->simpleAction($request, $params, 'restore', 'Custom field restored.');
    }

    public function destroy(Request $request, array $params = []): Response
    {
        $fieldId   = Row::int($params, 'id');
        $entityKey = Row::str($params, 'entity');

        /** @var CustomFieldService $service */
        $service = $this->container->get(CustomFieldService::class);
        /** @var Session $session */
        $session = $this->container->get(Session::class);

        $phrase = $request->inputString('confirmation', '');

        try {
            $service->delete($fieldId, $phrase);
            $session->flash('success', 'Custom field deleted.');
        } catch (\RuntimeException $e) {
            $session->flash('error', $e->getMessage());
        }

        return Response::redirect('/admin/custom-fields/' . $entityKey);
    }

    public function toggleRequired(Request $request, array $params = []): Response
    {
        $fieldId   = Row::int($params, 'id');
        $entityKey = Row::str($params, 'entity');
        $required  = $request->inputBool('required', false);

        /** @var CustomFieldService $service */
        $service = $this->container->get(CustomFieldService::class);
        /** @var Session $session */
        $session = $this->container->get(Session::class);

        try {
            $service->toggleRequired($fieldId, $required);
            $session->flash('success', $required ? 'Field marked as required.' : 'Field marked as optional.');
        } catch (\RuntimeException $e) {
            $session->flash('error', $e->getMessage());
        }

        return Response::redirect('/admin/custom-fields/' . $entityKey);
    }

    public function moveUp(Request $request, array $params = []): Response
    {
        return $this->moveAction($request, $params, 'moveUp');
    }

    public function moveDown(Request $request, array $params = []): Response
    {
        return $this->moveAction($request, $params, 'moveDown');
    }

    private function moveAction(Request $request, array $params, string $method): Response
    {
        $fieldId   = Row::int($params, 'id');
        $entityKey = Row::str($params, 'entity');

        /** @var CustomFieldService $service */
        $service = $this->container->get(CustomFieldService::class);
        /** @var Session $session */
        $session = $this->container->get(Session::class);

        try {
            $moved = $service->{$method}($fieldId);
            if (!$moved) {
                $session->flash('error', 'Cannot move further in that direction.');
            }
        } catch (\RuntimeException $e) {
            $session->flash('error', $e->getMessage());
        }

        return Response::redirect('/admin/custom-fields/' . $entityKey);
    }

    private function simpleAction(Request $request, array $params, string $method, string $successMsg): Response
    {
        $fieldId   = Row::int($params, 'id');
        $entityKey = Row::str($params, 'entity');

        /** @var CustomFieldService $service */
        $service = $this->container->get(CustomFieldService::class);
        /** @var Session $session */
        $session = $this->container->get(Session::class);

        try {
            $service->{$method}($fieldId);
            $session->flash('success', $successMsg);
        } catch (\RuntimeException $e) {
            $session->flash('error', $e->getMessage());
        }

        return Response::redirect('/admin/custom-fields/' . $entityKey);
    }

    /** @return array<string, mixed> */
    private function collectInput(Request $request): array
    {
        $constraints = [];
        $rawConstraints = $request->inputArray('constraints');
        foreach ($rawConstraints as $k => $v) {
            $constraints[$k] = is_string($v) ? trim($v) : $v;
        }

        $options = [];
        $rawOptions = $request->inputArray('options');
        foreach ($rawOptions as $o) {
            $o = is_scalar($o) ? trim((string) $o) : '';
            if ($o !== '') $options[] = $o;
        }

        $sortOrderRaw = $request->input('sort_order', null);
        $sortOrder = is_numeric($sortOrderRaw) ? (int) $sortOrderRaw : null;

        return [
            'label'         => trim($request->inputString('label', '')),
            'input_type'    => trim($request->inputString('input_type', '')),
            'is_required'   => $request->inputBool('is_required', false),
            'options'       => $options,
            'constraints'   => $constraints,
            'default_value' => trim($request->inputString('default_value', '')),
            'help_text'     => trim($request->inputString('help_text', '')),
            'sort_order'    => $sortOrder,
        ];
    }
}

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
use CorporateAid\Recruit\Repositories\OrganisationRepository;
use CorporateAid\Recruit\Services\OrganisationService;
use CorporateAid\Core\Row;

final class OrganisationController
{
    public function __construct(private Container $container) {}

    /**
     * GET /organisations
     */
    /** @param array<string, string> $params */
    public function index(Request $request, array $params = []): Response
    {
        /** @var OrganisationRepository $orgs */
        $orgs = $this->container->get(OrganisationRepository::class);
        /** @var View $view */
        $view = $this->container->get(View::class);
        /** @var Auth $auth */
        $auth = $this->container->get(Auth::class);

        $search    = trim($request->queryStringValue('q', ''));
        $status    = trim($request->queryStringValue('status', ''));
        $page      = max(1, $request->queryInt('page', 1));
        $perPage   = 20;

        $filters = [];
        if ($search !== '') $filters['search'] = $search;
        if ($status !== '') $filters['status'] = $status;

        $result = $orgs->paginate($filters, $page, $perPage);
        $counts = $orgs->countsByStatus();

        // Add the "all" count
        $counts['all'] = array_sum($counts);

        $html = $view->render('organisations/index', [
            'title'        => 'Organisations',
            'user'         => $auth->user(),
            'organisations'=> $result['rows'],
            'total'        => $result['total'],
            'page'         => $result['page'],
            'per_page'     => $result['per_page'],
            'total_pages'  => $result['total_pages'],
            'counts'       => $counts,
            'search'       => $search,
            'status'       => $status,
        ], 'layouts/app');

        return Response::html($html);
    }

    /**
     * GET /organisations/create
     */
    /** @param array<string, string> $params */
    public function showCreate(Request $request, array $params = []): Response
    {
        /** @var View $view */
        $view = $this->container->get(View::class);
        /** @var Auth $auth */
        $auth = $this->container->get(Auth::class);

        $html = $view->render('organisations/form', [
            'title'        => 'New Organisation',
            'user'         => $auth->user(),
            'organisation' => null,
            'old'          => [],
            'errors'       => [],
        ], 'layouts/app');

        return Response::html($html);
    }

    /**
     * POST /organisations
     */
    /** @param array<string, string> $params */
    public function store(Request $request, array $params = []): Response
    {
        /** @var OrganisationService $service */
        $service = $this->container->get(OrganisationService::class);
        /** @var Session $session */
        $session = $this->container->get(Session::class);
        /** @var View $view */
        $view = $this->container->get(View::class);
        /** @var Auth $auth */
        $auth = $this->container->get(Auth::class);

        $input = $this->collectInput($request);
        $files = $this->collectFiles();

        $result = $service->create($input, $files);

        if (!empty($result['errors'])) {
            $html = $view->render('organisations/form', [
                'title'        => 'New Organisation',
                'user'         => $auth->user(),
                'organisation' => null,
                'old'          => $input,
                'errors'       => $result['errors'],
            ], 'layouts/app');
            return Response::html($html)->withHeader('X-Form-Error', '1');
        }

        $organisation = $result['organisation'];
        if ($organisation === null) {
            $session->flash('error', 'Organisation was created but could not be retrieved.');
            return Response::redirect('/organisations');
        }

        $session->flash('success', 'Organisation created: ' . $organisation->name);
        return Response::redirect('/organisations/' . $organisation->code);
    }

    /**
     * GET /organisations/{code}
     */
    /** @param array<string, string> $params */
    public function show(Request $request, array $params = []): Response
    {
        $code = ($params['code'] ?? '');

        /** @var OrganisationRepository $orgs */
        $orgs = $this->container->get(OrganisationRepository::class);
        $org = $orgs->findByCode($code);

        if (!$org) {
            throw HttpException::notFound('That organisation does not exist, or has been archived.');
        }

        /** @var View $view */
        $view = $this->container->get(View::class);
        /** @var Auth $auth */
        $auth = $this->container->get(Auth::class);

        $html = $view->render('organisations/show', [
            'title'        => $org->name,
            'user'         => $auth->user(),
            'organisation' => $org,
        ], 'layouts/app');

        return Response::html($html);
    }

    /**
     * GET /organisations/{code}/edit
     */
    /** @param array<string, string> $params */
    public function showEdit(Request $request, array $params = []): Response
    {
        $code = ($params['code'] ?? '');

        /** @var OrganisationRepository $orgs */
        $orgs = $this->container->get(OrganisationRepository::class);
        $org = $orgs->findByCode($code);

        if (!$org) {
            throw HttpException::notFound('That organisation does not exist, or has been archived.');
        }

        /** @var View $view */
        $view = $this->container->get(View::class);
        /** @var Auth $auth */
        $auth = $this->container->get(Auth::class);

        $html = $view->render('organisations/form', [
            'title'        => 'Edit ' . $org->name,
            'user'         => $auth->user(),
            'organisation' => $org,
            'old'          => [],
            'errors'       => [],
        ], 'layouts/app');

        return Response::html($html);
    }

    /**
     * POST /organisations/{code} (with _method=PUT for update)
     */
    /** @param array<string, string> $params */
    public function update(Request $request, array $params = []): Response
    {
        $code = ($params['code'] ?? '');

        /** @var OrganisationRepository $orgs */
        $orgs = $this->container->get(OrganisationRepository::class);
        $org = $orgs->findByCode($code);

        if (!$org) {
            return Response::redirect('/organisations');
        }

        /** @var OrganisationService $service */
        $service = $this->container->get(OrganisationService::class);
        /** @var Session $session */
        $session = $this->container->get(Session::class);
        /** @var View $view */
        $view = $this->container->get(View::class);
        /** @var Auth $auth */
        $auth = $this->container->get(Auth::class);

        $input = $this->collectInput($request);
        $files = $this->collectFiles();

        $result = $service->update($org->id, $input, $files);

        if (!$result['success']) {
            $html = $view->render('organisations/form', [
                'title'        => 'Edit ' . $org->name,
                'user'         => $auth->user(),
                'organisation' => $org,
                'old'          => $input,
                'errors'       => $result['errors'],
            ], 'layouts/app');
            return Response::html($html)->withHeader('X-Form-Error', '1');
        }

        $session->flash('success', 'Organisation updated.');
        return Response::redirect('/organisations/' . $org->code);
    }

    /**
     * POST /organisations/{code}/archive
     */
    /** @param array<string, string> $params */
    public function archive(Request $request, array $params = []): Response
    {
        return $this->lifecycleAction($request, $params, 'archive');
    }

    /**
     * POST /organisations/{code}/restore
     */
    /** @param array<string, string> $params */
    public function restore(Request $request, array $params = []): Response
    {
        return $this->lifecycleAction($request, $params, 'restore');
    }

    /**
     * POST /organisations/{code}/delete
     */
    /** @param array<string, string> $params */
    public function destroy(Request $request, array $params = []): Response
    {
        $code = ($params['code'] ?? '');

        /** @var OrganisationRepository $orgs */
        $orgs = $this->container->get(OrganisationRepository::class);
        $org = $orgs->findByCode($code, true);

        if (!$org) {
            return Response::redirect('/organisations');
        }

        /** @var OrganisationService $service */
        $service = $this->container->get(OrganisationService::class);
        /** @var Session $session */
        $session = $this->container->get(Session::class);

        $typedPhrase = $request->inputString('confirmation', '');
        $reason      = $request->inputString('reason', '');
        $reason      = $reason !== '' ? $reason : null;

        $result = $service->delete($org->id, $typedPhrase, $reason);

        if ($result->success) {
            $session->flash('success', $result->message);
            return Response::redirect('/organisations');
        }

        $session->flash('error', $result->message);
        return Response::redirect('/organisations/' . $code);
    }

    /**
     * GET /organisations/{code}/delete-info (JSON)
     */
    /** @param array<string, string> $params */
    public function deleteInfo(Request $request, array $params = []): Response
    {
        $code = ($params['code'] ?? '');

        /** @var OrganisationRepository $orgs */
        $orgs = $this->container->get(OrganisationRepository::class);
        $org = $orgs->findByCode($code, true);

        if (!$org) {
            return Response::json(['ok' => false, 'message' => 'Organisation not found.'], 404);
        }

        /** @var OrganisationService $service */
        $service = $this->container->get(OrganisationService::class);
        $result = $service->inspectDelete($org->id);

        if (!$result->success) {
            return Response::json(['ok' => false, 'message' => $result->message], 403);
        }

        return Response::json([
            'ok'              => true,
            'has_dependents'  => $result->hasDependents,
            'dependents'      => $result->dependents,
            'required_phrase' => $result->requiredPhrase,
        ]);
    }

    // ---------- helpers ----------

    private function lifecycleAction(Request $request, array $params, string $action): Response
    {
        $code = ($params['code'] ?? '');

        /** @var OrganisationRepository $orgs */
        $orgs = $this->container->get(OrganisationRepository::class);
        $org = $orgs->findByCode($code, true);

        if (!$org) {
            return Response::redirect('/organisations');
        }

        /** @var OrganisationService $service */
        $service = $this->container->get(OrganisationService::class);
        /** @var Session $session */
        $session = $this->container->get(Session::class);

        $reason = $request->inputString('reason', '');
        $reason = $reason !== '' ? $reason : null;

        $result = $action === 'archive'
            ? $service->archive($org->id, $reason)
            : $service->restore($org->id);

        if ($result->success) {
            $session->flash('success', $result->message);
        } else {
            $session->flash('error', $result->message);
        }

        return Response::redirect('/organisations/' . $code);
    }

    private function collectInput(Request $request): array
    {
        $countries = $request->input('countries_operation', []);
        if (is_string($countries)) {
            $countries = array_map('trim', explode(',', $countries));
        }
        if (!is_array($countries)) $countries = [];

        $cf = $request->input('cf', []);
        if (!is_array($cf)) $cf = [];

        return [
            'name'                => trim($request->inputString('name', '')),
            'legal_name'          => trim($request->inputString('legal_name', '')),
            'industry'            => trim($request->inputString('industry', '')),
            'website'             => trim($request->inputString('website', '')),
            'country_code'        => trim($request->inputString('country_code', '')),
            'countries_operation' => $countries,
            'address'             => trim($request->inputString('address', '')),
            'phone'               => trim($request->inputString('phone', '')),
            'email'               => trim($request->inputString('email', '')),
            'status'              => trim($request->inputString('status', 'prospect')),
            'notes'               => trim($request->inputString('notes', '')),
            'cf'                  => $cf,
        ];
    }

    /**
     * Extract uploaded files for custom fields.
     *
     * Looks for $_FILES entries named "cf_{fieldKey}_file" and returns
     * them keyed by fieldKey. Skips empty uploads (UPLOAD_ERR_NO_FILE).
     *
     * The field name pattern matches FileType::render(), which emits:
     *   <input type="file" name="cf_{fieldKey}_file">
     *
     * @return array<string, array>  fieldKey => $_FILES-shaped array
     */
    private function collectFiles(): array
    {
        $files = [];

        if (empty($_FILES) || !is_array($_FILES)) {
            return $files;
        }

        foreach ($_FILES as $inputName => $fileData) {
            if (!is_string($inputName)) {
                continue;
            }
            if (!preg_match('/^cf_(.+)_file$/', $inputName, $matches)) {
                continue;
            }

            $fieldKey = $matches[1];
            if (!is_array($fileData)) {
                continue;
            }

            $error = Row::int($fileData, 'error', UPLOAD_ERR_NO_FILE);
            if ($error === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            $files[$fieldKey] = $fileData;
        }

        return $files;
    }
}

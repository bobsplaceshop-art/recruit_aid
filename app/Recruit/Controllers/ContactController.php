<?php
declare(strict_types=1);

namespace CorporateAid\Recruit\Controllers;
use CorporateAid\Core\Row;

use CorporateAid\Core\Auth;
use CorporateAid\Core\Container;
use CorporateAid\Core\Exceptions\HttpException;
use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Core\Session;
use CorporateAid\Core\View;
use CorporateAid\Recruit\Repositories\ContactRepository;
use CorporateAid\Recruit\Repositories\OrganisationRepository;
use CorporateAid\Recruit\Services\ContactService;

final class ContactController
{
    public function __construct(private Container $container) {}

    /**
     * GET /organisations/{code}/contacts
     */
    /** @param array<string, string> $params */
    public function index(Request $request, array $params = []): Response
    {
        $orgCode = ($params['code'] ?? '');

        /** @var OrganisationRepository $orgs */
        $orgs = $this->container->get(OrganisationRepository::class);
        $org = $orgs->findByCode($orgCode);

        if (!$org) {
            throw HttpException::notFound('That organisation does not exist, or has been archived.');
        }

        /** @var View $view */
        $view = $this->container->get(View::class);
        /** @var Auth $auth */
        $auth = $this->container->get(Auth::class);
        /** @var ContactRepository $contacts */
        $contacts = $this->container->get(ContactRepository::class);

        $html = $view->render('contacts/index', [
            'title'        => 'Contacts · ' . $org->name,
            'user'         => $auth->user(),
            'organisation' => $org,
            'contacts'     => $contacts->forOrganisation($org->id),
        ], 'layouts/app');

        return Response::html($html);
    }

    /**
     * GET /contacts — global list across all organisations with filters
     */
    /** @param array<string, string> $params */
    public function showGlobalList(Request $request, array $params = []): Response
    {
        /** @var View $view */
        $view = $this->container->get(View::class);
        /** @var Auth $auth */
        $auth = $this->container->get(Auth::class);
        /** @var ContactRepository $contacts */
        $contacts = $this->container->get(ContactRepository::class);
        /** @var \CorporateAid\Recruit\Repositories\OrganisationRepository $orgs */
        $orgs = $this->container->get(\CorporateAid\Recruit\Repositories\OrganisationRepository::class);

        $q     = trim($request->queryStringValue('q', ''));
        $orgId = $request->queryInt('org', 0);
        $role  = trim($request->queryStringValue('role', ''));
        $page  = max(1, $request->queryInt('page', 1));

        $filters = [];
        if ($q !== '')     $filters['q'] = $q;
        if ($orgId > 0)    $filters['org_id'] = $orgId;
        if ($role !== '')  $filters['role'] = $role;

        $result = $contacts->paginateAll($filters, $page, 50);

        $html = $view->render('contacts/all', [
            'title'      => 'Contacts',
            'user'       => $auth->user(),
            'rows'       => $result['rows'],
            'total'      => $result['total'],
            'page'       => $result['page'],
            'perPage'    => $result['per_page'],
            'totalPages' => $result['total_pages'],
            'allOrgs'    => $orgs->allForFilter(),
            'q'          => $q,
            'orgId'      => $orgId,
            'role'       => $role,
        ], 'layouts/app');

        return Response::html($html);
    }

    /**
     * GET /organisations/{code}/contacts/create
     */
    /** @param array<string, string> $params */
    public function showCreate(Request $request, array $params = []): Response
    {
        $orgCode = ($params['code'] ?? '');

        /** @var OrganisationRepository $orgs */
        $orgs = $this->container->get(OrganisationRepository::class);
        $org = $orgs->findByCode($orgCode);

        if (!$org) {
            throw HttpException::notFound('That organisation does not exist, or has been archived.');
        }

        /** @var View $view */
        $view = $this->container->get(View::class);
        /** @var Auth $auth */
        $auth = $this->container->get(Auth::class);

        $html = $view->render('contacts/form', [
            'title'        => 'New Contact · ' . $org->name,
            'user'         => $auth->user(),
            'organisation' => $org,
            'contact'      => null,
            'old'          => [],
            'errors'       => [],
        ], 'layouts/app');

        return Response::html($html);
    }

    /**
     * POST /organisations/{code}/contacts
     */
    /** @param array<string, string> $params */
    public function store(Request $request, array $params = []): Response
    {
        $orgCode = ($params['code'] ?? '');

        /** @var OrganisationRepository $orgs */
        $orgs = $this->container->get(OrganisationRepository::class);
        $org = $orgs->findByCode($orgCode);

        if (!$org) {
            throw HttpException::notFound('That organisation does not exist, or has been archived.');
        }

        /** @var ContactService $service */
        $service = $this->container->get(ContactService::class);
        /** @var Session $session */
        $session = $this->container->get(Session::class);
        /** @var View $view */
        $view = $this->container->get(View::class);
        /** @var Auth $auth */
        $auth = $this->container->get(Auth::class);

        $input = $this->collectInput($request);
        $result = $service->create($org->id, $input);

        if (!empty($result['errors'])) {
            $html = $view->render('contacts/form', [
                'title'        => 'New Contact · ' . $org->name,
                'user'         => $auth->user(),
                'organisation' => $org,
                'contact'      => null,
                'old'          => $input,
                'errors'       => $result['errors'],
            ], 'layouts/app');
            return Response::html($html)->withHeader('X-Form-Error', '1');
        }

        $contact = $result['contact'];
        if ($contact === null) {
            $session->flash('error', 'Contact was created but could not be retrieved.');
            return Response::redirect('/organisations/' . $org->code . '/contacts');
        }

        $session->flash('success', 'Contact created: ' . $contact->fullName());
        return Response::redirect('/organisations/' . $org->code . '/contacts/' . $contact->code);
    }

    /**
     * GET /organisations/{code}/contacts/{contactCode}
     */
    /** @param array<string, string> $params */
    public function show(Request $request, array $params = []): Response
    {
        $orgCode = ($params['code'] ?? '');
        $contactCode = ($params['contactCode'] ?? '');

        /** @var OrganisationRepository $orgs */
        $orgs = $this->container->get(OrganisationRepository::class);
        $org = $orgs->findByCode($orgCode);
        if (!$org) {
            throw HttpException::notFound('That organisation does not exist, or has been archived.');
        }

        /** @var ContactRepository $contacts */
        $contacts = $this->container->get(ContactRepository::class);
        $contact = $contacts->findByCode($contactCode);

        if (!$contact || $contact->clientOrganisationId !== $org->id) {
            throw HttpException::notFound('That contact does not exist, or has been archived.');
        }

        /** @var View $view */
        $view = $this->container->get(View::class);
        /** @var Auth $auth */
        $auth = $this->container->get(Auth::class);

        $html = $view->render('contacts/show', [
            'title'        => $contact->fullName(),
            'user'         => $auth->user(),
            'organisation' => $org,
            'contact'      => $contact,
            'returnTo'     => trim($request->queryStringValue('return_to', '')),
        ], 'layouts/app');

        return Response::html($html);
    }

    /**
     * GET /organisations/{code}/contacts/{contactCode}/edit
     */
    /** @param array<string, string> $params */
    public function showEdit(Request $request, array $params = []): Response
    {
        $orgCode = ($params['code'] ?? '');
        $contactCode = ($params['contactCode'] ?? '');

        /** @var OrganisationRepository $orgs */
        $orgs = $this->container->get(OrganisationRepository::class);
        $org = $orgs->findByCode($orgCode);
        if (!$org) {
            throw HttpException::notFound('That organisation does not exist, or has been archived.');
        }

        /** @var ContactRepository $contacts */
        $contacts = $this->container->get(ContactRepository::class);
        $contact = $contacts->findByCode($contactCode);

        if (!$contact || $contact->clientOrganisationId !== $org->id) {
            throw HttpException::notFound('That contact does not exist, or has been archived.');
        }

        /** @var View $view */
        $view = $this->container->get(View::class);
        /** @var Auth $auth */
        $auth = $this->container->get(Auth::class);

        $html = $view->render('contacts/form', [
            'title'        => 'Edit Contact · ' . $contact->fullName(),
            'user'         => $auth->user(),
            'organisation' => $org,
            'contact'      => $contact,
            'old'          => [],
            'errors'       => [],
        ], 'layouts/app');

        return Response::html($html);
    }

    /**
     * POST /organisations/{code}/contacts/{contactCode}  (with _method=PUT)
     */
    /** @param array<string, string> $params */
    public function update(Request $request, array $params = []): Response
    {
        $orgCode = ($params['code'] ?? '');
        $contactCode = ($params['contactCode'] ?? '');

        /** @var OrganisationRepository $orgs */
        $orgs = $this->container->get(OrganisationRepository::class);
        $org = $orgs->findByCode($orgCode);
        if (!$org) {
            throw HttpException::notFound('That organisation does not exist, or has been archived.');
        }

        /** @var ContactRepository $contacts */
        $contacts = $this->container->get(ContactRepository::class);
        $contact = $contacts->findByCode($contactCode);

        if (!$contact || $contact->clientOrganisationId !== $org->id) {
            throw HttpException::notFound('That contact does not exist, or has been archived.');
        }

        /** @var ContactService $service */
        $service = $this->container->get(ContactService::class);
        /** @var Session $session */
        $session = $this->container->get(Session::class);
        /** @var View $view */
        $view = $this->container->get(View::class);
        /** @var Auth $auth */
        $auth = $this->container->get(Auth::class);

        $input = $this->collectInput($request);
        $result = $service->update($contact->id, $input);

        if (!$result['success']) {
            $html = $view->render('contacts/form', [
                'title'        => 'Edit Contact · ' . $contact->fullName(),
                'user'         => $auth->user(),
                'organisation' => $org,
                'contact'      => $contact,
                'old'          => $input,
                'errors'       => $result['errors'],
            ], 'layouts/app');
            return Response::html($html)->withHeader('X-Form-Error', '1');
        }

        $session->flash('success', 'Contact updated.');
        return Response::redirect('/organisations/' . $org->code . '/contacts/' . $contact->code);
    }

    /** @param array<string, string> $params */
    public function archive(Request $request, array $params = []): Response
    {
        return $this->lifecycleAction($request, $params, 'archive');
    }

    /** @param array<string, string> $params */
    public function restore(Request $request, array $params = []): Response
    {
        return $this->lifecycleAction($request, $params, 'restore');
    }

    /** @param array<string, string> $params */
    public function destroy(Request $request, array $params = []): Response
    {
        [$org, $contact] = $this->resolveOrgAndContact($params, true);

        /** @var ContactService $service */
        $service = $this->container->get(ContactService::class);
        /** @var Session $session */
        $session = $this->container->get(Session::class);

        $phrase = $request->inputString('confirmation', '');
        $reason = trim($request->inputString('reason', '')) ?: null;

        $result = $service->delete($contact->id, $phrase, $reason);

        if ($result->success) {
            $session->flash('success', $result->message);
            return Response::redirect('/organisations/' . $org->code . '/contacts');
        }

        $session->flash('error', $result->message);
        return Response::redirect('/organisations/' . $org->code . '/contacts/' . $contact->code);
    }

    /** @param array<string, string> $params */
    public function deleteInfo(Request $request, array $params = []): Response
    {
        [$org, $contact] = $this->resolveOrgAndContact($params, true);

        /** @var ContactService $service */
        $service = $this->container->get(ContactService::class);
        $result = $service->inspectDelete($contact->id);

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

    /** @param array<string, string> $params */
    private function lifecycleAction(Request $request, array $params, string $method): Response
    {
        [$org, $contact] = $this->resolveOrgAndContact($params, true);

        /** @var ContactService $service */
        $service = $this->container->get(ContactService::class);
        /** @var Session $session */
        $session = $this->container->get(Session::class);

        $reason = trim($request->inputString('reason', '')) ?: null;

        try {
            $result = $method === 'archive'
                ? $service->archive($contact->id, $reason)
                : $service->restore($contact->id);

            $session->flash($result->success ? 'success' : 'error', $result->message);
        } catch (\Throwable $e) {
            $session->flash('error', 'Action failed: ' . $e->getMessage());
        }

        return Response::redirect('/organisations/' . $org->code . '/contacts/' . $contact->code);
    }

    /**
     * @param array<string, string> $params
     * @return array{0:\CorporateAid\Recruit\Models\Organisation,1:\CorporateAid\Recruit\Models\Contact}
     */
    private function resolveOrgAndContact(array $params, bool $includeDeleted = false): array
    {
        $orgCode = ($params['code'] ?? '');
        $contactCode = ($params['contactCode'] ?? '');

        /** @var OrganisationRepository $orgs */
        $orgs = $this->container->get(OrganisationRepository::class);
        $org = $orgs->findByCode($orgCode, $includeDeleted);

        if (!$org) {
            throw HttpException::notFound('That organisation does not exist, or has been archived.');
        }

        /** @var ContactRepository $contacts */
        $contacts = $this->container->get(ContactRepository::class);
        $contact = $contacts->findByCode($contactCode, $includeDeleted);

        if (!$contact || $contact->clientOrganisationId !== $org->id) {
            throw HttpException::notFound('That contact does not exist, or has been archived.');
        }

        return [$org, $contact];
    }

    /** @return array<string, mixed> */
    private function collectInput(Request $request): array
    {
        $cf = $request->input('cf', []);
        if (!is_array($cf)) $cf = [];

        // Read roles[] array — comes from pivot-driven checkboxes.
        // Fall back to legacy boolean field names for backward compatibility
        // with any cached form still posting the old 5 checkboxes.
        $rolesInput = $request->input('roles', []);
        $roles = [];
        if (is_array($rolesInput)) {
            foreach ($rolesInput as $r) {
                if (is_scalar($r)) {
                    $slug = strtolower(trim((string) $r));
                    if ($slug !== '') $roles[$slug] = true;
                }
            }
        }
        $roles = array_keys($roles);

        // If roles[] was empty, check for legacy boolean fields and map
        // them to slugs (covers cached forms posting the old shape).
        if (empty($roles)) {
            $legacyMap = [
                'is_primary'        => 'is_primary',
                'is_hiring_manager' => 'is_hiring_manager',
                'is_finance'        => 'is_finance',
                'is_hr'             => 'is_hr',
                'is_decision_maker' => 'is_decision_maker',
            ];
            foreach ($legacyMap as $field => $slug) {
                if ($request->inputBool($field, false)) {
                    $roles[] = $slug;
                }
            }
        }

        return [
            'first_name'              => trim($request->inputString('first_name', '')),
            'last_name'               => trim($request->inputString('last_name', '')),
            'position'                => trim($request->inputString('position', '')),
            'department'              => trim($request->inputString('department', '')),
            'email'                   => trim($request->inputString('email', '')),
            'phone'                   => trim($request->inputString('phone', '')),
            'alt_phone'               => trim($request->inputString('alt_phone', '')),
            'country_code'            => trim($request->inputString('country_code', '')),
            'preferred_communication' => trim($request->inputString('preferred_communication', 'email')),
            'roles'                   => $roles,
            'is_primary'              => in_array('is_primary', $roles, true),
            'is_hiring_manager'       => in_array('is_hiring_manager', $roles, true),
            'is_finance'              => in_array('is_finance', $roles, true),
            'is_hr'                   => in_array('is_hr', $roles, true),
            'is_decision_maker'       => in_array('is_decision_maker', $roles, true),
            'notes'                   => trim($request->inputString('notes', '')),
            'cf'                      => $cf,
        ];
    }
}

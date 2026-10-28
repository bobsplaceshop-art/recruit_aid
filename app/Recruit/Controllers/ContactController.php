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
use CorporateAid\Recruit\Repositories\ContactRepository;
use CorporateAid\Recruit\Repositories\OrganisationRepository;
use CorporateAid\Recruit\Services\ContactService;

final class ContactController
{
    public function __construct(private Container $container) {}

    /**
     * GET /organisations/{code}/contacts
     */
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
     * GET /organisations/{code}/contacts/create
     */
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

        $session->flash('success', 'Contact created: ' . $result['contact']->fullName());
        return Response::redirect('/organisations/' . $org->code . '/contacts/' . $result['contact']->code);
    }

    /**
     * GET /organisations/{code}/contacts/{contactCode}
     */
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

        if (!$contact || $contact->organisationId !== $org->id) {
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
        ], 'layouts/app');

        return Response::html($html);
    }

    /**
     * GET /organisations/{code}/contacts/{contactCode}/edit
     */
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

        if (!$contact || $contact->organisationId !== $org->id) {
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

        if (!$contact || $contact->organisationId !== $org->id) {
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

    public function archive(Request $request, array $params = []): Response
    {
        return $this->lifecycleAction($request, $params, 'archive');
    }

    public function restore(Request $request, array $params = []): Response
    {
        return $this->lifecycleAction($request, $params, 'restore');
    }

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

    /** @return array{0:\CorporateAid\Recruit\Models\Organisation,1:\CorporateAid\Recruit\Models\Contact} */
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

        if (!$contact || $contact->organisationId !== $org->id) {
            throw HttpException::notFound('That contact does not exist, or has been archived.');
        }

        return [$org, $contact];
    }

    private function collectInput(Request $request): array
    {
        $cf = $request->input('cf', []);
        if (!is_array($cf)) $cf = [];

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
            'is_primary'              => $request->inputBool('is_primary', false),
            'is_hiring_manager'       => $request->inputBool('is_hiring_manager', false),
            'is_finance'              => $request->inputBool('is_finance', false),
            'is_hr'                   => $request->inputBool('is_hr', false),
            'is_decision_maker'       => $request->inputBool('is_decision_maker', false),
            'notes'                   => trim($request->inputString('notes', '')),
            'cf'                      => $cf,
        ];
    }
}

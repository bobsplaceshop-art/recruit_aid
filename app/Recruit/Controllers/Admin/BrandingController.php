<?php
declare(strict_types=1);

namespace CorporateAid\Recruit\Controllers\Admin;

use CorporateAid\Core\Auth;
use CorporateAid\Core\Container;
use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Core\Session;
use CorporateAid\Core\View;
use CorporateAid\Platform\Services\FileService;
use CorporateAid\Platform\Services\SystemSettingService;
use CorporateAid\Platform\Support\Logger;

final class BrandingController
{
    public function __construct(private Container $container) {}

    /** @param array<string, string> $params */
    public function show(Request $request, array $params = []): Response
    {
        /** @var View $view */
        $view = $this->container->get(View::class);
        /** @var Auth $auth */
        $auth = $this->container->get(Auth::class);
        /** @var SystemSettingService $settings */
        $settings = $this->container->get(SystemSettingService::class);

        $html = $view->render('admin/settings/branding', [
            'title'    => 'Branding',
            'user'     => $auth->user(),
            'branding' => [
                'app_name'           => $settings->getString('branding.app_name', 'Recruit Aid'),
                'tagline'            => $settings->getString('branding.tagline', 'Recruitment Operations Platform'),
                'logo_path'          => $settings->getString('branding.logo_path', ''),
                'favicon_path'       => $settings->getString('branding.favicon_path', ''),
                'primary_color'      => $settings->getString('branding.primary_color', '#0d6efd'),
                'client_org_name'    => $settings->getString('branding.client_org_name', ''),
                'client_org_address' => $settings->getString('branding.client_org_address', ''),
                'client_org_phone'   => $settings->getString('branding.client_org_phone', ''),
                'client_org_email'   => $settings->getString('branding.client_org_email', ''),
            ],
        ], 'layouts/app');

        return Response::html($html);
    }

    /** @param array<string, string> $params */
    public function update(Request $request, array $params = []): Response
    {
        /** @var SystemSettingService $settings */
        $settings = $this->container->get(SystemSettingService::class);
        /** @var FileService $files */
        $files = $this->container->get(FileService::class);
        /** @var Session $session */
        $session = $this->container->get(Session::class);
        /** @var Auth $auth */
        $auth = $this->container->get(Auth::class);

        // Text fields
        $settings->set('branding.app_name', trim($request->inputString('app_name', 'Recruit Aid')));
        $settings->set('branding.tagline', trim($request->inputString('tagline', '')));
        $settings->set('branding.primary_color', trim($request->inputString('primary_color', '#0d6efd')));
        $settings->set('branding.client_org_name', trim($request->inputString('client_org_name', '')));
        $settings->set('branding.client_org_address', trim($request->inputString('client_org_address', '')));
        $settings->set('branding.client_org_phone', trim($request->inputString('client_org_phone', '')));
        $settings->set('branding.client_org_email', trim($request->inputString('client_org_email', '')));

        // File uploads: logo
        $logo = $request->file('logo');
        if ($logo !== null) {
            $result = $files->store($logo, $auth->user()?->id, 'branding_logo', 2);
            if (is_array($result)) {
                foreach ($result as $err) {
                    $session->flash('error', 'Logo upload failed: ' . $err);
                }
            } else {
                // Link to a synthetic 'branding' entity so FileController can
                // serve it (with the PUBLIC_ENTITY_TYPES bypass).
                $files->link($result->id, 'branding', 0, 'branding_logo', $auth->user()?->id);
                $settings->set('branding.logo_path', $result->uuid);
                $session->flash('success', 'Logo updated.');
            }
        }

        // File uploads: favicon
        $favicon = $request->file('favicon');
        if ($favicon !== null) {
            $result = $files->store($favicon, $auth->user()?->id, 'branding_favicon', 1);
            if (is_array($result)) {
                foreach ($result as $err) {
                    $session->flash('error', 'Favicon upload failed: ' . $err);
                }
            } else {
                $files->link($result->id, 'branding', 0, 'branding_favicon', $auth->user()?->id);
                $settings->set('branding.favicon_path', $result->uuid);
                $session->flash('success', 'Favicon updated.');
            }
        }

        $session->flash('success', 'Branding settings saved.');
        return Response::redirect('/admin/settings/branding');
    }
}

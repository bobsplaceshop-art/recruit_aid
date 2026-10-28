<?php
declare(strict_types=1);

namespace CorporateAid\Recruit\Controllers\Admin;

use CorporateAid\Core\Auth;
use CorporateAid\Core\Container;
use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Core\View;
use CorporateAid\Platform\Repositories\UserRepository;

final class AdminHomeController
{
    private Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /** @param array<string, string> $params */
    public function index(Request $request, array $params = []): Response
    {
        /** @var View $view */
        $view = $this->container->get(View::class);
        /** @var Auth $auth */
        $auth = $this->container->get(Auth::class);
        /** @var UserRepository $users */
        $users = $this->container->get(UserRepository::class);

        $userCount = $users->count();

        $html = $view->render('admin/index', [
            'title'     => 'Administration',
            'user'      => $auth->user(),
            'userCount' => $userCount,
        ], 'layouts/app');

        return Response::html($html);
    }
}

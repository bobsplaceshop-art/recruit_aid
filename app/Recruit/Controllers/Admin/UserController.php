<?php
declare(strict_types=1);

namespace CorporateAid\Recruit\Controllers\Admin;

use CorporateAid\Core\Auth;
use CorporateAid\Core\Container;
use CorporateAid\Core\Database;
use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Core\View;

final class UserController
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
        /** @var Database $db */
        $db = $this->container->get(Database::class);

        $users = $db->select(
            "SELECT u.id, u.name, u.email, u.status, u.is_client_user,
                    u.last_login_at, u.created_at,
                    GROUP_CONCAT(r.slug ORDER BY r.slug SEPARATOR ',') AS role_slugs
             FROM users u
             LEFT JOIN user_roles ur ON ur.user_id = u.id
             LEFT JOIN roles r ON r.id = ur.role_id
             WHERE u.deleted_at IS NULL
             GROUP BY u.id
             ORDER BY u.created_at DESC
             LIMIT 100"
        );

        foreach ($users as &$u) {
            $u['roles'] = !empty($u['role_slugs']) && is_string($u['role_slugs']) ? explode(',', $u['role_slugs']) : [];
        }
        unset($u);

        $html = $view->render('admin/users/index', [
            'title' => 'Users',
            'user'  => $auth->user(),
            'users' => $users,
        ], 'layouts/app');

        return Response::html($html);
    }
}

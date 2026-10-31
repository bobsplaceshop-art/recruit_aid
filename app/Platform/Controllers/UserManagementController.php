<?php
declare(strict_types=1);

namespace CorporateAid\Platform\Controllers;

use CorporateAid\Core\Container;
use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Core\View;
use CorporateAid\Core\Database;
use CorporateAid\Core\PDOParams;
use CorporateAid\Core\Row;

final class UserManagementController
{
    public function __construct(private Container $container) {}

    /** @param array<string, string> $params */
    public function index(Request $request, array $params = []): Response
    {
        /** @var View $view */
        $view = $this->container->get(View::class);
        /** @var Database $db */
        $db = $this->container->get(Database::class);

        // Fetch all users
        $users = $db->select("SELECT id, name, email, status FROM `core_users` WHERE `tenant_id` = 1 ORDER BY `name` ASC");
        
        // Fetch all app assignments
        $assignments = $db->select("SELECT user_id, module_key, is_default FROM `core_user_applications`");
        
        // Group assignments by user_id
        $userApps = [];
        foreach ($assignments as $a) {
            $uid = Row::int($a, 'user_id');
            $userApps[$uid][Row::str($a, 'module_key')] = Row::int($a, 'is_default') === 1;
        }

        $html = $view->render('portal/users', [
            'title' => 'User Management',
            'users' => $users,
            'userApps' => $userApps,
        ], 'layouts/portal');

        return Response::html($html);
    }
    /** @param array<string, string> $params */
    public function update(Request $request, array $params = []): Response
    {
        /** @var Database $db */
        $db = $this->container->get(Database::class);

        $userId = (int) $request->postValue('user_id');
        $hasRecruit = $request->postValue('recruit') === '1';
        $hasHr = $request->postValue('hr') === '1';

        if ($userId <= 0) {
            return Response::redirect('/users');
        }

        // Clear existing assignments for this user
        $db->execute("DELETE FROM `core_user_applications` WHERE `user_id` = ?", PDOParams::positional([$userId]));

        // Re-assign based on checkboxes
        if ($hasRecruit) {
            $db->execute("INSERT INTO `core_user_applications` (`user_id`, `module_key`, `is_default`) VALUES (?, 'recruit', 0)", PDOParams::positional([$userId]));
        }
        if ($hasHr) {
            $db->execute("INSERT INTO `core_user_applications` (`user_id`, `module_key`, `is_default`) VALUES (?, 'hr', 0)", PDOParams::positional([$userId]));
        }

        return Response::redirect('/users');
    }

}

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
use CorporateAid\Platform\Services\NotificationService;

final class NotificationController
{
    public function __construct(private Container $container) {}

    /**
     * GET /notifications — full notifications page
     */
    /** @param array<string, string> $params */
    public function index(Request $request, array $params = []): Response
    {
        /** @var View $view */
        $view = $this->container->get(View::class);
        /** @var Auth $auth */
        $auth = $this->container->get(Auth::class);
        /** @var NotificationService $notifications */
        $notifications = $this->container->get(NotificationService::class);

        $recent = $notifications->recentForCurrentUser(50);

        $html = $view->render('notifications/index', [
            'title'         => 'Notifications',
            'user'          => $auth->user(),
            'notifications' => $recent,
        ], 'layouts/app');

        return Response::html($html);
    }

    /**
     * GET /api/notifications — JSON for the bell dropdown
     * Returns: { unread_count: N, items: [...] }
     */
    /** @param array<string, string> $params */
    public function api(Request $request, array $params = []): Response
    {
        /** @var NotificationService $notifications */
        $notifications = $this->container->get(NotificationService::class);
        /** @var Auth $auth */
        $auth = $this->container->get(Auth::class);

        $user = $auth->user();
        if ($user === null) {
            return Response::json(['ok' => false], 401);
        }

        $items = [];
        foreach ($notifications->recentForCurrentUser(10) as $n) {
            $items[] = [
                'id'         => $n->id,
                'type'       => $n->type,
                'icon'       => $n->icon(),
                'title'      => $n->title,
                'body'       => $n->body,
                'link'       => $n->link,
                'is_read'    => $n->isRead(),
                'relative'   => $n->relativeTime(),
            ];
        }

        return Response::json([
            'ok'           => true,
            'unread_count' => $notifications->unreadCountForCurrentUser(),
            'items'        => $items,
        ]);
    }

    /**
     * POST /notifications/{id}/read
     */
    /** @param array<string, string> $params */
    public function markRead(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);

        /** @var NotificationService $notifications */
        $notifications = $this->container->get(NotificationService::class);
        /** @var Session $session */
        $session = $this->container->get(Session::class);

        if ($id > 0) {
            $notifications->markRead($id);
        }

        // AJAX vs form post
        if ($request->wantsJson()) {
            return Response::json(['ok' => true]);
        }

        return Response::redirect('/notifications');
    }

    /**
     * POST /notifications/read-all
     */
    /** @param array<string, string> $params */
    public function markAllRead(Request $request, array $params = []): Response
    {
        /** @var NotificationService $notifications */
        $notifications = $this->container->get(NotificationService::class);
        /** @var Session $session */
        $session = $this->container->get(Session::class);

        $notifications->markAllRead();
        $session->flash('success', 'All notifications marked as read.');

        if ($request->wantsJson()) {
            return Response::json(['ok' => true]);
        }

        return Response::redirect('/notifications');
    }
}

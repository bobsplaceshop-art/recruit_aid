<?php
declare(strict_types=1);

namespace CorporateAid\Platform\Middleware;

use CorporateAid\Core\Auth;
use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Core\Container;

final class AppAccessMiddleware
{
    public function __construct(private Container $container) {}

    /** @param callable(Request): Response $next */
    public function handle(Request $request, callable $next, string $moduleKey): Response
    {
        /** @var Auth $auth */
        $auth = $this->container->get(Auth::class);
        $user = $auth->user();
        
        if ($user === null) {
            return Response::redirect('/login');
        }

        // Super admins bypass the assignment check
        if ($auth->can('cas.dashboard.view')) {
            return $next($request);
        }

        $assigned = $auth->users->getAssignedApplications($user->id);
        if (!in_array($moduleKey, $assigned, true)) {
            return Response::redirect('/no-access'); // Or 403
        }

        return $next($request);
    }
}

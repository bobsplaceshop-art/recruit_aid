<?php
declare(strict_types=1);

namespace CorporateAid\Platform\Middleware;

use CorporateAid\Core\Auth;
use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Core\Container;

final class AppAccessMiddleware
{
    public function __construct(
        private Container $container,
        private string $moduleKey
    ) {}

    /** @param callable(Request): Response $next */
    public function handle(Request $request, callable $next): Response
    {
        /** @var Auth $auth */
        $auth = $this->container->get(Auth::class);
        $user = $auth->user();
        
        if ($user === null) {
            return Response::redirect('/login');
        }

        // Super admins bypass the assignment check
        if ($auth->can('cas.dashboard.access')) {
            return $next($request);
        }

        $assigned = $auth->getAssignedApplications();
        if (!in_array($this->moduleKey, $assigned, true)) {
            return Response::redirect('/no-access');
        }

        return $next($request);
    }
}

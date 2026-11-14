<?php
declare(strict_types=1);

namespace CorporateAid\Platform\Middleware;

use CorporateAid\Core\Auth;
use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Core\Session;
use CorporateAid\Platform\Authorization\PlatformOwnerService;

/**
 * Guards user-admin routes against modification of platform owners.
 *
 * Attached to PUT /recruit/admin/users/{id}. If the target user is in
 * platform_owners AND the acting user is not themselves a platform owner,
 * the request is rejected with a flash + redirect (business-rule rejection
 * per Doctrine #42).
 *
 * The framework's middleware signature does not receive route params, so
 * the target user ID is extracted from the request path. This middleware
 * is intended for the user-admin routes specifically.
 */
final class ProtectPlatformOwnerMiddleware
{
    public function __construct(
        private Auth $auth,
        private PlatformOwnerService $platformOwners,
        private Session $session
    ) {}

    public function handle(Request $request, callable $next): Response
    {
        $actor = $this->auth->user();
        if ($actor === null) {
            return $next($request);
        }

        $path = $request->path();
        if (preg_match('#/recruit/admin/users/(\d+)(?:/edit)?$#', $path, $m) !== 1) {
            return $next($request);
        }
        $targetId = (int) $m[1];

        if (!$this->platformOwners->isOwner($targetId)) {
            return $next($request);
        }

        if ($this->platformOwners->isOwner($actor->id)) {
            return $next($request);
        }

        $this->session->flash(
            'error',
            'This user is a platform owner. Only a platform owner can modify their account.'
        );
        return Response::redirect('/recruit/admin/users');
    }
}

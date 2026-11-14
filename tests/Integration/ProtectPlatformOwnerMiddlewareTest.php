<?php
declare(strict_types=1);

use CorporateAid\Core\App;
use CorporateAid\Core\Auth;
use CorporateAid\Core\Container;
use CorporateAid\Core\Database;
use CorporateAid\Core\PDOParams;
use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Core\Row;
use CorporateAid\Core\Session;
use CorporateAid\Core\TenantContext;
use CorporateAid\Platform\Middleware\ProtectPlatformOwnerMiddleware;
use CorporateAid\Platform\Models\User;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance criteria for ProtectPlatformOwnerMiddleware:
 *   PM-01: Unauthenticated request passes through (no actor).
 *   PM-02: Non-matching path passes through (e.g. /recruit/candidates/42).
 *   PM-03: Non-owner target passes through.
 *   PM-04: Owner-to-owner passes through (both in platform_owners).
 *   PM-05: Non-owner actor modifying an owner is redirected + flashed.
 */
final class ProtectPlatformOwnerMiddlewareTest extends TestCase
{
    private static Database $db;
    private static Container $c;
    private static Auth $auth;
    private static Session $session;
    private static TenantContext $tenant;

    public static function setUpBeforeClass(): void
    {
        App::boot(dirname(__DIR__, 2));
        self::$c = App::instance()->container();
        self::$db = self::$c->get(Database::class);
        self::$auth = self::$c->get(Auth::class);
        self::$session = self::$c->get(Session::class);
        self::$tenant = self::$c->get(TenantContext::class);
    }

    protected function tearDown(): void
    {
        // Clear session-scoped state so tests are isolated.
        unset(
            $_SESSION['_flash'],
            $_SESSION['auth_user_id'],
            $_SESSION['auth_tenant_id'],
            $_SESSION['auth_effective_tenant_id']
        );
        // Reset Auth's internal cache (if any) by calling logout
        // which clears the cached user and regenerates the session.
        self::$auth->logout();
    }

    /** PM-01: unauthenticated request is passed through. */
    public function testPassesThroughWhenUnauthenticated(): void
    {
        // No session user set (tearDown ensures this).
        $request = $this->makeRequest('PUT', '/recruit/admin/users/42');
        $sentinel = Response::html('REACHED');

        $response = $this->middleware()->handle($request, fn() => $sentinel);

        self::assertSame($sentinel, $response, 'Unauthenticated request must pass through.');
    }

    /** PM-02: path not matching /recruit/admin/users/{id} passes through. */
    public function testPassesThroughOnNonMatchingPath(): void
    {
        $this->withFixtures(function (int $actorId, int $targetId) {
            $this->authenticateAs($actorId);
            $request = $this->makeRequest('PUT', "/recruit/candidates/{$targetId}");
            $sentinel = Response::html('REACHED');

            $response = $this->middleware()->handle($request, fn() => $sentinel);

            self::assertSame($sentinel, $response, 'Non-matching path must pass through.');
        });
    }

    /** PM-03: target user is not a platform owner -> pass through. */
    public function testPassesThroughWhenTargetIsNotAnOwner(): void
    {
        $this->withFixtures(function (int $actorId, int $targetId) {
            $this->authenticateAs($actorId);
            $request = $this->makeRequest('PUT', "/recruit/admin/users/{$targetId}");
            $sentinel = Response::html('REACHED');

            $response = $this->middleware()->handle($request, fn() => $sentinel);

            self::assertSame($sentinel, $response, 'Non-owner target must pass through.');
        });
    }

    /** PM-04: actor and target are both platform owners -> pass through. */
    public function testPassesThroughWhenBothActorAndTargetAreOwners(): void
    {
        $this->withFixtures(function (int $actorId, int $targetId) {
            // Both actor and target are owners.
            $this->markAsOwner($actorId);
            $this->markAsOwner($targetId);

            $this->authenticateAs($actorId);
            $request = $this->makeRequest('PUT', "/recruit/admin/users/{$targetId}");
            $sentinel = Response::html('REACHED');

            $response = $this->middleware()->handle($request, fn() => $sentinel);

            self::assertSame($sentinel, $response, 'Owner-to-owner must pass through.');
        });
    }

    /** PM-05: non-owner actor modifying an owner -> flash + redirect. */
    public function testBlocksWhenTargetIsOwnerAndActorIsNot(): void
    {
        $this->withFixtures(function (int $actorId, int $targetId) {
            // Target is an owner; actor is not.
            $this->markAsOwner($targetId);

            $this->authenticateAs($actorId);
            $request = $this->makeRequest('PUT', "/recruit/admin/users/{$targetId}");
            $sentinel = Response::html('REACHED');

            $response = $this->middleware()->handle($request, fn() => $sentinel);

            self::assertNotSame($sentinel, $response, 'Non-owner must NOT pass through.');
            self::assertSame(
                '/recruit/admin/users',
                $response->headers()['Location'] ?? null,
                'Must redirect to the users index.'
            );

            $flash = self::$session->getFlash('error');
            self::assertIsString($flash, 'Flash error must be a string.');
            self::assertStringContainsString('platform owner', $flash);
        });
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function middleware(): ProtectPlatformOwnerMiddleware
    {
        return self::$c->get(ProtectPlatformOwnerMiddleware::class);
    }

    /**
     * Construct a Request with the given method and path.
     * Other args are empty — we only exercise path matching.
     */
    private function makeRequest(string $method, string $path): Request
    {
        return new Request($method, $path, '', [], [], [], [], [], [], null);
    }

    /**
     * Set the session so Auth::user() returns the given user.
     * Clears the Auth cache first (via logout) so the next user() call
     * loads from the DB with the session user ID we just set.
     */
    private function authenticateAs(int $userId): void
    {
        $row = self::$db->selectOne(
            "SELECT `id`, `tenant_id` FROM `core_users` WHERE `id` = ?",
            PDOParams::positional([$userId])
        );
        if ($row === null) {
            self::fail("User {$userId} not found.");
        }
        self::$auth->logout();  // clear cached user + session
        $_SESSION['auth_user_id']   = Row::int($row, 'id');
        $_SESSION['auth_tenant_id'] = Row::int($row, 'tenant_id');
    }

    private function markAsOwner(int $userId): void
    {
        self::$db->execute(
            "INSERT INTO `platform_owners` (`user_id`, `designation`, `contract_reference`)
             VALUES (?, 'Test Owner', 'TEST-REF')",
            PDOParams::positional([$userId])
        );
    }

    /**
     * Creates two users in tenant 1 (actor + target), runs $body inside
     * a transaction, and rolls back after.
     */
    private function withFixtures(callable $body): void
    {
        self::$db->beginTransaction();
        try {
            $suffix = bin2hex(random_bytes(6));

            $actorId = self::$db->insert(
                "INSERT INTO `core_users`
                    (`tenant_id`, `name`, `email`, `password_hash`, `status`)
                 VALUES (1, ?, ?, 'test-hash', 'active')",
                PDOParams::positional([
                    "PM-Actor {$suffix}",
                    "pm-actor-{$suffix}@example.test",
                ])
            );

            $targetId = self::$db->insert(
                "INSERT INTO `core_users`
                    (`tenant_id`, `name`, `email`, `password_hash`, `status`)
                 VALUES (1, ?, ?, 'test-hash', 'active')",
                PDOParams::positional([
                    "PM-Target {$suffix}",
                    "pm-target-{$suffix}@example.test",
                ])
            );

            $body($actorId, $targetId);
        } finally {
            self::$db->rollBack();
        }
    }
}

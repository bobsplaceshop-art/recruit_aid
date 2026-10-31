<?php
declare(strict_types=1);

namespace CorporateAid\Core;

use CorporateAid\Platform\Models\User;
use CorporateAid\Platform\Repositories\UserRepository;
use CorporateAid\Platform\Support\Hasher;

/**
 * Authentication service. Owns the "currently logged in user" concept.
 *
 * Session keys written by this class:
 *   - auth_user_id    (login / read on every authenticated request)
 *   - auth_tenant_id  (login — consumed by TenantContext::id())
 *
 * TenantContext must NOT depend on Auth (cycle: Auth -> UserRepository
 * -> TenantContext -> Auth). Instead, login() writes the tenant id to
 * the session here, and TenantContext reads it from the session. See
 * TenantContext's docblock for the full rationale.
 */
final class Auth
{
    private const SESSION_USER_KEY   = 'auth_user_id';
    private const SESSION_TENANT_KEY = 'auth_tenant_id';

    private UserRepository $users;
    private Session $session;
    private ?User $cached = null;

    public function __construct(UserRepository $users, Session $session)
    {
        $this->users = $users;
        $this->session = $session;
    }

    /**
     * Attempt to log in. Returns the User on success, null on failure.
     * Does not distinguish "wrong email" from "wrong password" (security).
     */
    public function attempt(string $email, string $password): ?User
    {
        $user = $this->users->findByEmail($email);
        if ($user === null) {
            // Waste time to avoid user-enumeration via timing
            Hasher::verify($password, '$2y$10$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG');
            return null;
        }

        if (!$user->isActive()) {
            return null;
        }

        if ($user->isLocked()) {
            return null;
        }

        if (!Hasher::verify($password, $user->passwordHash)) {
            $this->users->incrementFailedLogins($user->id);
            return null;
        }

        // Password is correct. Rehash if the algorithm has changed.
        if (Hasher::needsRehash($user->passwordHash)) {
            $this->users->updatePassword($user->id, $password);
        }

        // Load roles & permissions so the app knows what they can do
        $this->users->loadRoles($user);
        $this->users->loadPermissions($user);

        // Log them in
        $this->login($user);

        // Record the successful login
        $this->users->updateLastLogin($user->id);

        return $user;
    }

    /**
     * Log a user in directly (used after successful attempt, and by
     * "remember me" or admin impersonation later).
     *
     * Writes both the user id and the tenant id to the session. The
     * tenant id is what TenantContext::id() reads; without it, every
     * request would fall back to the default tenant (1), which is fine
     * for a single-tenant install but wrong the moment a second tenant
     * exists.
     */
    public function login(User $user): void
    {
        $this->session->regenerate(true);
        $this->session->set(self::SESSION_USER_KEY, $user->id);
        if (isset($user->tenantId) && $user->tenantId > 0) {
            $this->session->set(self::SESSION_TENANT_KEY, $user->tenantId);
        }
        $this->cached = $user;
    }

    public function logout(): void
    {
        $this->session->forget(self::SESSION_USER_KEY);
        $this->session->forget(self::SESSION_TENANT_KEY);
        $this->session->regenerate(true);
        $this->cached = null;
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function id(): ?int
    {
        $user = $this->user();
        return $user?->id;
    }

    public function user(): ?User
    {
        if ($this->cached instanceof User) {
            return $this->cached;
        }

        $id = $this->session->get(self::SESSION_USER_KEY);
        if (!is_int($id) || $id <= 0) {
            return null;
        }

        $user = $this->users->findById($id);
        if ($user === null || !$user->isActive()) {
            $this->logout();
            return null;
        }

        $this->users->loadRoles($user);
        $this->users->loadPermissions($user);

        $this->cached = $user;
        return $user;
    }

    public function can(string $permission): bool
    {
        $user = $this->user();
        return $user !== null && $user->can($permission);
    }




    public function canAccessGlobalDashboard(): bool
    {
        return $this->can('cas.dashboard.access');
    }

    public function getDefaultApplication(): ?string
    {
        $userId = $this->id();
        if ($userId === null) return null;
        return $this->users->getDefaultApplication($userId);
    }

    /** @return string[] */
    public function getAssignedApplications(): array
    {
        $userId = $this->id();
        if ($userId === null) return [];
        return $this->users->getAssignedApplications($userId);
    }

}

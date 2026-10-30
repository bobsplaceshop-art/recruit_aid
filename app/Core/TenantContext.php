<?php
declare(strict_types=1);

namespace CorporateAid\Core;

/**
 * Resolves the current tenant for the request.
 *
 * In a single-tenant install today, this always returns 1.
 * In a multi-tenant install, it would resolve from the session
 * (set at login by matching the user's tenant_id).
 *
 * All repositories that read/write tenant-scoped tables must call
 * TenantContext::id() and filter by it.
 */
final class TenantContext
{
    private const SESSION_TENANT_KEY = 'auth_tenant_id';
    private const DEFAULT_TENANT_ID = 1;

    public function __construct(
        private Session $session,
        private ?Auth $auth = null
    ) {}

    /**
     * The current tenant ID. Precedence:
     *   1. Explicitly set in the session (auth_tenant_id)
     *   2. The authenticated user's tenant_id
     *   3. The default tenant (1) for a fresh install
     */
    public function id(): int
    {
        $sessionTenant = $this->session->get(self::SESSION_TENANT_KEY);
        if (is_int($sessionTenant) && $sessionTenant > 0) {
            return $sessionTenant;
        }

        if ($this->auth !== null) {
            $user = $this->auth->user();
            if ($user !== null && isset($user->tenantId) && $user->tenantId > 0) {
                return $user->tenantId;
            }
        }

        return self::DEFAULT_TENANT_ID;
    }

    public function set(int $tenantId): void
    {
        $this->session->set(self::SESSION_TENANT_KEY, $tenantId);
    }

    public function clear(): void
    {
        $this->session->forget(self::SESSION_TENANT_KEY);
    }
}

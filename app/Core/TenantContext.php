<?php
declare(strict_types=1);

namespace CorporateAid\Core;

/**
 * Resolves the current tenant for the request.
 *
 * Single-tenant install today: returns the session value if set,
 * else the default tenant (1). Multi-tenant installs resolve from
 * the session, which Auth::login() populates via TenantContext::set().
 *
 * This class MUST NOT depend on Auth. Auth depends on UserRepository,
 * UserRepository depends on TenantContext — a TenantContext->Auth edge
 * would close that loop and recurse in the container (no autowiring,
 * no cycle detection).
 *
 * All repositories that read/write tenant-scoped tables must call
 * TenantContext::id() and filter by it.
 */
final class TenantContext
{
    private const SESSION_TENANT_KEY = 'auth_tenant_id';
    private const DEFAULT_TENANT_ID  = 1;

    public function __construct(
        private Session $session
    ) {}

    /**
     * The current tenant ID. Precedence:
     *   1. Explicitly set in the session (auth_tenant_id)
     *   2. The default tenant (1) for a fresh install
     *
     * Never reads from Auth. Auth::login() calls set() to keep the
     * session value in sync after a user logs in.
     */
    public function id(): int
    {
        $sessionTenant = $this->session->get(self::SESSION_TENANT_KEY);
        if (is_int($sessionTenant) && $sessionTenant > 0) {
            return $sessionTenant;
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

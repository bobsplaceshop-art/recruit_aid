<?php
declare(strict_types=1);

namespace CorporateAid\Core;

/**
 * Resolves the current tenant for the request.
 *
 * Supports two distinct concepts:
 *   - Home Tenant (identity): The tenant the user belongs to.
 *   - Effective Tenant (scope): The tenant whose data is being queried.
 *
 * For regular users, Effective == Home.
 * For platform admins viewing another tenant, Effective != Home.
 */
final class TenantContext
{
    private const SESSION_HOME_KEY      = 'auth_tenant_id';
    private const SESSION_EFFECTIVE_KEY = 'auth_effective_tenant_id';
    private const DEFAULT_TENANT_ID     = 1;

    public function __construct(
        private Session $session
    ) {}

    /**
     * The effective tenant ID for data queries.
     * Falls back to the home tenant if no override is set.
     */
    public function id(): int
    {
        $effective = $this->session->get(self::SESSION_EFFECTIVE_KEY);
        if (is_int($effective) && $effective > 0) {
            return $effective;
        }
        return $this->homeId();
    }

    /**
     * The home tenant ID (identity).
     */
    public function homeId(): int
    {
        $home = $this->session->get(self::SESSION_HOME_KEY);
        if (is_int($home) && $home > 0) {
            return $home;
        }
        return self::DEFAULT_TENANT_ID;
    }

    /**
     * Set the effective tenant (for platform admins).
     */
    public function setEffective(int $tenantId): void
    {
        $this->session->set(self::SESSION_EFFECTIVE_KEY, $tenantId);
    }

    /**
     * Set the home tenant (called by Auth::login).
     */
    public function set(int $tenantId): void
    {
        $this->session->set(self::SESSION_HOME_KEY, $tenantId);
    }

    public function clear(): void
    {
        $this->session->forget(self::SESSION_HOME_KEY);
        $this->session->forget(self::SESSION_EFFECTIVE_KEY);
    }
}

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
     * Remove the temporary effective-tenant override and return to the
     * home tenant. Idempotent: calling when no override is set is a no-op.
     *
     * This method MUST NOT modify SESSION_HOME_KEY. The home tenant is the
     * authenticated identity; only Auth::login() / Auth::logout() and
     * TenantContext::set() / clear() may write or forget it.
     */
    public function clearEffective(): void
    {
        $this->session->forget(self::SESSION_EFFECTIVE_KEY);
    }

    /**
     * Set the active tenant identity (home) and clear any effective override.
     *
     * This is the "activate this tenant fully" operation. It is called when
     * the session's identity changes — e.g. after login, or when a test
     * switches tenant to exercise a scoped code path. It is NEVER called by
     * the /cas/* context-switch flow, which uses setEffective() to change
     * scope while leaving home identity untouched.
     *
     * Auth::login() currently writes the home key directly and performs the
     * same explicit effective-override forget; the two paths are consistent.
     */
    public function set(int $tenantId): void
    {
        if ($tenantId <= 0) {
            throw new \InvalidArgumentException(
                'Tenant ID must be positive; got ' . $tenantId . '.'
            );
        }

        $this->session->set(self::SESSION_HOME_KEY, $tenantId);
        // id() prefers the effective tenant, so a stale override would
        // silently shadow the newly-set home. Clearing it here makes the
        // post-condition id() === $tenantId true unless setEffective() is
        // called afterwards.
        $this->session->forget(self::SESSION_EFFECTIVE_KEY);
    }

    public function clear(): void
    {
        $this->session->forget(self::SESSION_HOME_KEY);
        $this->session->forget(self::SESSION_EFFECTIVE_KEY);
    }
}

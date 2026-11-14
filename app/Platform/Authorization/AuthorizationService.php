<?php
declare(strict_types=1);

namespace CorporateAid\Platform\Authorization;

use CorporateAid\Core\Container;
use CorporateAid\Core\Database;
use CorporateAid\Core\PDOParams;
use CorporateAid\Core\Row;
use CorporateAid\Core\TenantContext;
use CorporateAid\Platform\Models\User;
use RuntimeException;

/**
 * The single enforcement point for "can this user do X?".
 *
 * Replaces User::can() and Auth::can() internals. See
 * docs/RBAC-ARCHITECTURE.md §5 for the precedence rules.
 *
 * The service is booted once per request from App::bootstrap().
 * User::can() and Auth::can() delegate here.
 */
final class AuthorizationService
{
    private static ?self $instance = null;

    /** @var array<string, array{module:string, sensitivity:string, is_active:bool}> */
    private array $permissionCache = [];

    /** @var array<string, array<string, bool>> tenant:user => (module_key => true) */
    private array $assignedAppsCache = [];

    /** @var array<string, array<int, array{module_key:string, allowed:array<int,string>, denied:array<int,string>, expires_at:?string}>> tenant:user => delegations */
    private array $delegationsCache = [];

    /** @var array<string, array<int, string>> tenant:user => denied permission slugs */
    private array $userDenialsCache = [];

    /** @var array<string, array<int, string>> tenant:user => explicit grants */
    private array $userGrantsCache = [];

    /** @var array<string, bool> module_key => is registered in core_modules */
    private array $moduleRegisteredCache = [];

    public function __construct(
        private Database $db,
        private TenantContext $tenant,
        private SupportDelegationService $supportDelegations,
        private PlatformOwnerService $platformOwners
    ) {}

    public static function boot(Container $c): void
    {
        self::$instance = new self(
            $c->get(Database::class),
            $c->get(TenantContext::class),
            $c->get(SupportDelegationService::class),
            $c->get(PlatformOwnerService::class)
        );
    }

    public static function instance(): self
    {
        if (self::$instance === null) {
            throw new RuntimeException('AuthorizationService has not been booted.');
        }
        return self::$instance;
    }

    /**
     * Clear all per-request caches. Call after any RBAC mutation
     * (e.g., in tests, or in a long-running worker that processes
     * permission-changing events).
     */
    public function clearCache(): void
    {
        $this->permissionCache = [];
        $this->assignedAppsCache = [];
        $this->delegationsCache = [];
        $this->userDenialsCache = [];
        $this->userGrantsCache = [];
        $this->moduleRegisteredCache = [];
    }

    public static function check(User $user, string $permission, array $context = []): bool
    {
        return self::instance()->allows($user, $permission, $context);
    }

    /**
     * The central decision. Implements the 11-rule precedence from
     * docs/RBAC-ARCHITECTURE.md §5.2.
     *
     * @param array<string, mixed> $context
     */
    public function allows(?User $user, string $permission, array $context = []): bool
    {
        // Rule 1: unauthenticated -> DENY
        if ($user === null) {
            return false;
        }

        // Rule 2: permission must exist and be active.
        $perm = $this->getPermission($permission);
        if ($perm === null || !$perm['is_active']) {
            return false;
        }

        // CAS platform permissions are evaluated independently from
        // tenant membership, tenant roles and tenant direct grants.
        // A platform assignment never grants access to Recruit Aid data.
        if (str_starts_with($permission, 'cas.')) {
            return $this->hasPlatformPermission($user->id, $permission);
        }

        // Rule 3b: Developer super-administrator exception.
        // A platform owner (platform_owners) is granted any tenant-domain
        // permission (scope='app') across all tenants, without a tenant
        // role or support delegation. Platform-domain permissions
        // (scope='global') are NOT granted here — those are governed by
        // Rule 3 (cas.*) or by the tenant-scoped rules for core.*.
        // No per-check audit write: see RBAC-ARCHITECTURE.md §9.4.
        if ($perm['scope'] === 'app' && $this->platformOwners->isOwner($user->id)) {
            return true;
        }

        // Rule 4: a valid active tenant context is required for any
        // tenant-owned permission. Missing context is a hard DENY.
        $tenantId = $this->tenant->id();
        if ($tenantId <= 0) {
            return false;
        }

        // Resolve ownership once. Rules 4b and 6 use the resolved app key.
        $resolution = $this->resolveApplication($perm['module'], $permission);

        // Rule 4a: unknown / unregistered ownership -> DENY (fail closed)
        if ($resolution->isInvalid()) {
            return false;
        }

        // Rule 4b: owning application disabled -> DENY
        if ($resolution->isResolved()) {
            if (!$this->isModuleEnabled($resolution->applicationKey)) {
                return false;
            }
        }

        // Rule 5: absolute policy denial -> DENY (evaluated against the
        // active tenant context, regardless of how the user arrived there).
        if ($this->isPolicyDenied($tenantId, $permission)) {
            return false;
        }

        // Rule 6: application assignment is mandatory for app-scoped
        // permissions. Applies universally — identity or delegation.
        // A delegated cas_admin must still have the module assigned.
        if ($resolution->isResolved()) {
            if (!$this->isAppAssigned($user->id, $resolution->applicationKey)) {
                return false;
            }
        }

        // --- Branch: identity vs cross-tenant delegation ---
        //
        // Identity path: user's home tenant == active tenant. Use Rules 7–9.
        // Delegation path: user is acting cross-tenant. Support delegation is
        // the ONLY way to authorize. Rules 7–9 (home-tenant grants/roles) do
        // NOT apply — they would leak home-tenant authority into another tenant.
        $identityMatch = $user->tenantId > 0 && $user->tenantId === $tenantId;

        if (!$identityMatch) {
            // Rule 5 (from the design): delegation cannot grant cas.* or
            // core.* permissions. The app-scoped check enforces this since
            // cas.* / core.* resolve as global (isResolved() === false).
            if (!$resolution->isResolved()) {
                return false;
            }
            // Rule 8: expiry, revocation, and grantee status are checked
            // inside activeFor(). No caching — every decision is fresh.
            $delegation = $this->supportDelegations->activeFor(
                $user->id,
                $tenantId,
                $resolution->applicationKey
            );
            if ($delegation === null) {
                return false;
            }
            // permits() enforces: scope ceiling, explicit deny, allowlist.
            return $this->supportDelegations->permits($delegation, $permission);
        }

        // Identity path — the user's home tenant matches the active tenant.
        // Rule 7: explicit user denial -> DENY (overrides grants + roles)
        if (in_array($permission, $this->getUserDenials($tenantId, $user->id), true)) {
            return false;
        }

        // Rule 8: explicit user grant -> ALLOW
        if (in_array($permission, $this->getUserGrants($tenantId, $user->id), true)) {
            return true;
        }

        // Rule 9: role-based grant -> ALLOW
        if (in_array($permission, $user->permissionSlugs, true)) {
            return true;
        }

        // Rule 11: default -> DENY
        return false;
    }

    /** @return array{module:string, sensitivity:string, scope:string, is_active:bool}|null */
    private function getPermission(string $slug): ?array
    {
        if (isset($this->permissionCache[$slug])) {
            return $this->permissionCache[$slug];
        }

        $row = $this->db->selectOne(
            "SELECT `module`, `sensitivity`, `scope`, `is_active`
             FROM `core_permissions` WHERE `slug` = ? LIMIT 1",
            PDOParams::positional([$slug])
        );

        if ($row === null) {
            return null;
        }

        $this->permissionCache[$slug] = [
            'module'      => Row::str($row, 'module'),
            'sensitivity' => Row::str($row, 'sensitivity', 'public'),
            'scope'       => Row::str($row, 'scope', 'app'),
            'is_active'   => (bool) Row::int($row, 'is_active'),
        ];

        return $this->permissionCache[$slug];
    }

    private function isModuleEnabled(string $moduleKey): bool
    {
        if ($moduleKey === 'core') {
            return true;
        }

        $row = $this->db->selectOne(
            "SELECT `is_enabled` FROM `core_module_state` WHERE `module_key` = ? LIMIT 1",
            PDOParams::positional([$moduleKey])
        );

        // If no state row exists, treat as enabled (backwards-compat with
        // modules that predate core_module_state).
        if ($row === null) {
            return true;
        }

        return Row::int($row, 'is_enabled') === 1;
    }

    /**
     * Returns the module_key that must be assigned to the user
     * for this permission to work, or null if the permission is global.
     */
    private function resolveApplication(string $permModule, string $permissionSlug): ApplicationResolution
    {
        // Global namespaces: cas.*, core.*, and legacy $permModule === 'core'.
        if ($permModule === 'core'
            || str_starts_with($permissionSlug, 'cas.')
            || str_starts_with($permissionSlug, 'core.')) {
            return ApplicationResolution::global();
        }

        // Application key is the first segment of the module identifier:
        //   recruit.candidates -> recruit
        //   hr.payroll         -> hr
        $firstSegment = explode('.', $permModule, 2)[0];

        if ($firstSegment === '' || !$this->isModuleRegistered($firstSegment)) {
            return ApplicationResolution::invalid();
        }

        return ApplicationResolution::resolved($firstSegment);
    }

    private function isModuleRegistered(string $moduleKey): bool
    {
        if (array_key_exists($moduleKey, $this->moduleRegisteredCache)) {
            return $this->moduleRegisteredCache[$moduleKey];
        }

        $count = $this->db->scalarInt(
            "SELECT COUNT(*) FROM `core_modules` WHERE `module_key` = ?",
            PDOParams::positional([$moduleKey])
        );

        $this->moduleRegisteredCache[$moduleKey] = $count > 0;
        return $this->moduleRegisteredCache[$moduleKey];
    }

    private function isAppAssigned(int $userId, string $moduleKey): bool
    {
        $tenantId = $this->tenant->id();
        $cacheKey = $tenantId . ':' . $userId;

        if (isset($this->assignedAppsCache[$cacheKey])) {
            return isset($this->assignedAppsCache[$cacheKey][$moduleKey]);
        }

        $rows = $this->db->select(
            "SELECT `module_key`
             FROM `core_user_applications`
             WHERE `user_id` = ?",
            PDOParams::positional([$userId])
        );

        $apps = [];
        foreach ($rows as $r) {
            $apps[Row::str($r, 'module_key')] = true;
        }
        $this->assignedAppsCache[$cacheKey] = $apps;
        return isset($apps[$moduleKey]);
    }

    private function isPolicyDenied(int $tenantId, string $slug): bool
    {
        $row = $this->db->selectOne(
            "SELECT `effect` FROM `core_permission_policies`
             WHERE `tenant_id` = ? AND `permission_slug` = ? LIMIT 1",
            PDOParams::positional([$tenantId, $slug])
        );
        return $row !== null && Row::str($row, 'effect') === 'deny';
    }

    /** @return string[] */
    private function getUserDenials(int $tenantId, int $userId): array
    {
        $cacheKey = $tenantId . ':' . $userId;
        if (isset($this->userDenialsCache[$cacheKey])) {
            return $this->userDenialsCache[$cacheKey];
        }

        $rows = $this->db->select(
            "SELECT p.`slug`
             FROM `core_user_permissions` up
             JOIN `core_permissions` p ON p.`id` = up.`permission_id`
             WHERE up.`tenant_id` = ?
               AND up.`user_id` = ?
               AND up.`denied` = 1
               AND (up.`expires_at` IS NULL OR up.`expires_at` > NOW())",
            PDOParams::positional([$tenantId, $userId])
        );

        $out = array_map(static fn($r) => Row::str($r, 'slug'), $rows);
        $this->userDenialsCache[$cacheKey] = $out;
        return $out;
    }

    /** @return string[] */
    private function getUserGrants(int $tenantId, int $userId): array
    {
        $cacheKey = $tenantId . ':' . $userId;
        if (isset($this->userGrantsCache[$cacheKey])) {
            return $this->userGrantsCache[$cacheKey];
        }

        $rows = $this->db->select(
            "SELECT p.`slug`
             FROM `core_user_permissions` up
             JOIN `core_permissions` p ON p.`id` = up.`permission_id`
             WHERE up.`tenant_id` = ?
               AND up.`user_id` = ?
               AND up.`denied` = 0
               AND (up.`expires_at` IS NULL OR up.`expires_at` > NOW())",
            PDOParams::positional([$tenantId, $userId])
        );

        $out = array_map(static fn($r) => Row::str($r, 'slug'), $rows);
        $this->userGrantsCache[$cacheKey] = $out;
        return $out;
    }

    /**
     * Check a global CAS permission through platform role assignments only.
     * Tenant roles and direct tenant grants are deliberately excluded.
     */
    private function hasPlatformPermission(int $userId, string $permission): bool
    {
        if ($userId <= 0) {
            return false;
        }

        return $this->db->scalarInt(
            "SELECT COUNT(*)
             FROM `core_user_platform_roles` upr
             JOIN `core_platform_roles` pr
               ON pr.`id` = upr.`role_id`
             JOIN `core_platform_role_permissions` prp
               ON prp.`role_id` = pr.`id`
             JOIN `core_permissions` p
               ON p.`id` = prp.`permission_id`
             JOIN `core_users` u
               ON u.`id` = upr.`user_id`
             WHERE upr.`user_id` = ?
               AND p.`slug` = ?
               AND p.`scope` = 'global'
               AND p.`is_active` = 1
               AND (upr.`expires_at` IS NULL OR upr.`expires_at` > NOW())
               AND u.`status` = 'active'
               AND u.`deleted_at` IS NULL",
            PDOParams::positional([$userId, $permission])
        ) > 0;
    }

    /**
     * Delegations are consulted by AdminDelegationService, not by
     * the main authorization flow. The cache exists so callers can
     * reach them cheaply.
     *
     * @return array<int, array{module_key:string, allowed:array<int,string>, denied:array<int,string>, expires_at:?string}>
     */
    public function getActiveDelegations(int $userId): array
    {
        $tenantId = $this->tenant->id();

        if ($tenantId <= 0 || $userId <= 0) {
            return [];
        }

        $cacheKey = $tenantId . ':' . $userId;

        if (array_key_exists($cacheKey, $this->delegationsCache)) {
            return $this->delegationsCache[$cacheKey];
        }

        $rows = $this->db->select(
            "SELECT d.`module_key`, d.`allowed_permissions_json`, d.`denied_permissions_json`, d.`expires_at`
             FROM `core_admin_delegations` d
             INNER JOIN `core_users` u
                ON u.`id` = d.`user_id`
               AND u.`tenant_id` = d.`tenant_id`
             WHERE d.`tenant_id` = ? AND d.`user_id` = ?
               AND d.`revoked_at` IS NULL
               AND (d.`expires_at` IS NULL OR d.`expires_at` > NOW())",
            PDOParams::positional([$tenantId, $userId])
        );

        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'module_key' => Row::str($r, 'module_key'),
                'allowed'    => (array) json_decode(Row::str($r, 'allowed_permissions_json', '[]'), true),
                'denied'     => (array) json_decode(Row::nullableStr($r, 'denied_permissions_json') ?? '[]', true),
                'expires_at' => Row::nullableStr($r, 'expires_at'),
            ];
        }

        $this->delegationsCache[$cacheKey] = $out;
        return $out;
    }
}

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

    /** @var array<int, array<string, bool>> userId => (module_key => true) */
    private array $assignedAppsCache = [];

    /** @var array<int, array<int, array{module_key:string, allowed:array<int,string>, denied:array<int,string>, expires_at:?string}>> */
    private array $delegationsCache = [];

    /** @var array<int, array<int, string>> userId => list of denied permission slugs */
    private array $userDenialsCache = [];

    /** @var array<int, array<int, string>> userId => list of explicit grants */
    private array $userGrantsCache = [];

    /** @var array<string, bool> module_key => is registered in core_modules */
    private array $moduleRegisteredCache = [];

    public function __construct(private Database $db, private TenantContext $tenant) {}

    public static function boot(Container $c): void
    {
        self::$instance = new self(
            $c->get(Database::class),
            $c->get(TenantContext::class)
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

        // Rule 2: no tenant context -> DENY
        $tenantId = $user->tenantId;
        if ($tenantId <= 0) {
            return false;
        }

        // Rule 3: permission not registered -> DENY
        $perm = $this->getPermission($permission);
        if ($perm === null || !$perm['is_active']) {
            return false;
        }

        // Resolve ownership once. Both Rule 4 (module enabled) and
        // Rule 6 (application assignment) use the resolved app key.
        $resolution = $this->resolveApplication($perm['module'], $permission);

        // Rule 4a: unknown / unregistered ownership -> DENY (fail closed)
        if ($resolution->isInvalid()) {
            return false;
        }

        // Rule 4b: owning application disabled -> DENY
        // (Only applies to app-scoped permissions; global namespaces skip.)
        if ($resolution->isResolved()) {
            if (!$this->isModuleEnabled($resolution->applicationKey)) {
                return false;
            }
        }

        // Rule 5: absolute policy denial -> DENY
        if ($this->isPolicyDenied($tenantId, $permission)) {
            return false;
        }

        // Rule 6: application assignment is mandatory for app-scoped
        // permissions. An explicit grant does NOT substitute for this.
        if ($resolution->isResolved()) {
            if (!$this->isAppAssigned($user->id, $resolution->applicationKey)) {
                return false;
            }
        }

        // Rule 7: explicit user denial -> DENY (overrides grants + roles)
        if (in_array($permission, $this->getUserDenials($user->id), true)) {
            return false;
        }

        // Rule 8: explicit user grant -> ALLOW (assigned user, no denial)
        if (in_array($permission, $this->getUserGrants($user->id), true)) {
            return true;
        }

        // Rule 9: role-based grant -> ALLOW
        if (in_array($permission, $user->permissionSlugs, true)) {
            return true;
        }

        // Rule 10: super_admin bypass - only for public sensitivity
        if ($user->isSuperAdmin() && $perm['sensitivity'] === 'public') {
            return true;
        }

        // Rule 11: default -> DENY
        return false;
    }

    /** @return array{module:string, sensitivity:string, is_active:bool}|null */
    private function getPermission(string $slug): ?array
    {
        if (isset($this->permissionCache[$slug])) {
            return $this->permissionCache[$slug];
        }

        $row = $this->db->selectOne(
            "SELECT `module`, `sensitivity`, `is_active`
             FROM `core_permissions` WHERE `slug` = ? LIMIT 1",
            PDOParams::positional([$slug])
        );

        if ($row === null) {
            return null;
        }

        $this->permissionCache[$slug] = [
            'module'      => Row::str($row, 'module'),
            'sensitivity' => Row::str($row, 'sensitivity', 'public'),
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
        if (isset($this->assignedAppsCache[$userId])) {
            return isset($this->assignedAppsCache[$userId][$moduleKey]);
        }

        $rows = $this->db->select(
            "SELECT `module_key` FROM `core_user_applications` WHERE `user_id` = ?",
            PDOParams::positional([$userId])
        );

        $apps = [];
        foreach ($rows as $r) {
            $apps[Row::str($r, 'module_key')] = true;
        }
        $this->assignedAppsCache[$userId] = $apps;
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
    private function getUserDenials(int $userId): array
    {
        if (isset($this->userDenialsCache[$userId])) {
            return $this->userDenialsCache[$userId];
        }

        $rows = $this->db->select(
            "SELECT p.`slug`
             FROM `core_user_permissions` up
             JOIN `core_permissions` p ON p.`id` = up.`permission_id`
             WHERE up.`user_id` = ?
               AND up.`denied` = 1
               AND (up.`expires_at` IS NULL OR up.`expires_at` > NOW())",
            PDOParams::positional([$userId])
        );

        $out = array_map(static fn($r) => Row::str($r, 'slug'), $rows);
        $this->userDenialsCache[$userId] = $out;
        return $out;
    }

    /** @return string[] */
    private function getUserGrants(int $userId): array
    {
        if (isset($this->userGrantsCache[$userId])) {
            return $this->userGrantsCache[$userId];
        }

        $rows = $this->db->select(
            "SELECT p.`slug`
             FROM `core_user_permissions` up
             JOIN `core_permissions` p ON p.`id` = up.`permission_id`
             WHERE up.`user_id` = ?
               AND up.`denied` = 0
               AND (up.`expires_at` IS NULL OR up.`expires_at` > NOW())",
            PDOParams::positional([$userId])
        );

        $out = array_map(static fn($r) => Row::str($r, 'slug'), $rows);
        $this->userGrantsCache[$userId] = $out;
        return $out;
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
        if (isset($this->delegationsCache[$userId])) {
            return $this->delegationsCache[$userId];
        }

        $rows = $this->db->select(
            "SELECT `module_key`, `allowed_permissions_json`, `denied_permissions_json`, `expires_at`
             FROM `core_admin_delegations`
             WHERE `user_id` = ? AND `revoked_at` IS NULL
               AND (`expires_at` IS NULL OR `expires_at` > NOW())",
            PDOParams::positional([$userId])
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

        $this->delegationsCache[$userId] = $out;
        return $out;
    }
}
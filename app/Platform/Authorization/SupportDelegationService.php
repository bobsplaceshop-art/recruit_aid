<?php
declare(strict_types=1);

namespace CorporateAid\Platform\Authorization;

use CorporateAid\Core\Database;
use CorporateAid\Core\PDOParams;
use CorporateAid\Core\Row;
use InvalidArgumentException;
use RuntimeException;

/**
 * Manages support delegations.
 *
 * A support delegation is an explicit, expiring, auditable grant that lets
 * a cas_admin (or a tenant principal) access one tenant's data within one
 * module, without changing their home tenant.
 *
 * Asymmetric revocation:
 *   - granted_by_type = 'cas_developer'  → immediate revoke.
 *   - granted_by_type = 'tenant_admin'   → revocation_pending with a
 *     cooling-off window (24h) during which the developer can cancel.
 *
 * Enforcement model:
 *   scope is the SECURITY CEILING. It governs which action suffixes are
 *   permitted at all, regardless of JSON. `full` scope allows every
 *   non-destructive action automatically; destructive actions (delete,
 *   manage, purge, restore) require explicit `allowed_extra_permissions`
 *   at grant time.
 *
 *   Within the ceiling:
 *     1. explicit denial (denied_permissions_json)    → DENY
 *     2. explicit allowlist (allowed_permissions_json) → requires membership
 *     3. otherwise                                     → ALLOW
 *
 *   cas.* permissions are rejected at grant time. A support delegation is
 *   for tenant business data, not platform authority.
 */
final class SupportDelegationService
{
    private const COOLING_OFF_HOURS = 24;

    private const VALID_SCOPES = ['read_only', 'read_write', 'full'];

    private const VALID_GRANTOR_TYPES = ['tenant_admin', 'cas_developer'];

    /** Actions each non-full scope permits. Fail-closed: unknown suffix denies. */
    private const SCOPE_SUFFIXES = [
        'read_only'  => ['view', 'list', 'read', 'export', 'search'],
        'read_write' => [
            'view', 'list', 'read', 'export', 'search',
            'create', 'edit', 'update', 'send', 'respond', 'submit',
            'evaluate', 'advance', 'close', 'assign', 'unassign',
            'upload', 'link', 'attach',
        ],
    ];

    /** Actions that full scope does NOT auto-grant. Require explicit opt-in. */
    private const DESTRUCTIVE_SUFFIXES = ['delete', 'manage', 'purge', 'restore'];

    public function __construct(
        private Database $db,
        private PlatformOwnerService $owners
    ) {}

    /**
     * @param array{
     *   user_id:int,
     *   tenant_id:int,
     *   module_key:string,
     *   granted_by_user_id:int,
     *   granted_by_type:string,
     *   scope:string,
     *   reason:?string,
     *   expires_at:string,
     *   denied_permissions?:string[],
     *   allowed_extra_permissions?:string[]
     * } $args
     */
    public function grant(array $args): int
    {
        $userId       = (int)  ($args['user_id'] ?? 0);
        $tenantId     = (int)  ($args['tenant_id'] ?? 0);
        $moduleKey    = (string) ($args['module_key'] ?? '');
        $grantedBy    = (int)  ($args['granted_by_user_id'] ?? 0);
        $grantorType  = (string) ($args['granted_by_type'] ?? '');
        $scope        = (string) ($args['scope'] ?? '');
        $reason       = isset($args['reason']) ? (string) $args['reason'] : null;
        $expiresAt    = (string) ($args['expires_at'] ?? '');
        $denied       = $args['denied_permissions'] ?? [];
        $allowedExtra = $args['allowed_extra_permissions'] ?? [];

        if ($userId <= 0 || $tenantId <= 0 || $grantedBy <= 0) {
            throw new InvalidArgumentException('user_id, tenant_id and granted_by_user_id are required.');
        }
        if ($moduleKey === '' || !$this->moduleExists($moduleKey)) {
            throw new InvalidArgumentException("Unknown module_key: {$moduleKey}.");
        }
        if (!in_array($grantorType, self::VALID_GRANTOR_TYPES, true)) {
            throw new InvalidArgumentException("Invalid granted_by_type: {$grantorType}.");
        }
        if (!in_array($scope, self::VALID_SCOPES, true)) {
            throw new InvalidArgumentException("Invalid scope: {$scope}.");
        }
        if ($grantorType === 'cas_developer' && ($reason === null || trim($reason) === '')) {
            throw new InvalidArgumentException('A reason is required for cas_developer grants.');
        }
        $expiresTs = strtotime($expiresAt);
        if ($expiresTs === false || $expiresTs <= time()) {
            throw new InvalidArgumentException('expires_at must be a future datetime.');
        }

        $this->assertGrantorAuthorized($grantorType, $grantedBy, $tenantId);
        $this->assertGranteeEligible($grantorType, $userId, $tenantId);

        $allowList = $this->materializeAllowList($scope, $moduleKey, $allowedExtra);
        if ($allowList === []) {
            throw new InvalidArgumentException(
                "Scope '{$scope}' materialized no permissions for module '{$moduleKey}'."
            );
        }

        $deniedClean = $this->normalizeSlugs($denied);
        $this->assertValidPermissionSlugs($moduleKey, $allowList);
        $this->assertValidPermissionSlugs($moduleKey, $deniedClean);

        return $this->db->insert(
            "INSERT INTO `core_support_delegations`
                (`user_id`, `tenant_id`, `module_key`,
                 `granted_by_user_id`, `granted_by_type`,
                 `scope`, `allowed_permissions_json`, `denied_permissions_json`,
                 `reason`, `expires_at`)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            PDOParams::positional([
                $userId,
                $tenantId,
                $moduleKey,
                $grantedBy,
                $grantorType,
                $scope,
                json_encode(array_values($allowList), JSON_THROW_ON_ERROR),
                $deniedClean === []
                    ? null
                    : json_encode(array_values($deniedClean), JSON_THROW_ON_ERROR),
                $reason,
                $expiresAt,
            ])
        );
    }

    /**
     * Revoke a delegation.
     *
     * Asymmetric:
     *   - If the delegation was granted by a cas_developer, revocation is
     *     immediate.
     *   - If it was granted by a tenant_admin, revocation enters
     *     'revocation_pending' with a 24-hour cooling-off window during
     *     which the cas_developer can cancel it.
     */
    public function revoke(int $delegationId, int $revokedByUserId): void
    {
        $row = $this->db->selectOne(
            "SELECT `granted_by_type`, `revocation_state`
             FROM `core_support_delegations` WHERE `id` = ? LIMIT 1",
            PDOParams::positional([$delegationId])
        );
        if ($row === null) {
            throw new InvalidArgumentException("Delegation {$delegationId} not found.");
        }
        if (Row::str($row, 'revocation_state') !== 'active') {
            throw new InvalidArgumentException(
                "Delegation {$delegationId} is not active (state: "
                . Row::str($row, 'revocation_state') . ').'
            );
        }

        if (Row::str($row, 'granted_by_type') === 'cas_developer') {
            $this->db->execute(
                "UPDATE `core_support_delegations`
                 SET `revocation_state` = 'revoked',
                     `revoked_at` = NOW(),
                     `revoked_by_user_id` = ?
                 WHERE `id` = ?",
                PDOParams::positional([$revokedByUserId, $delegationId])
            );
            return;
        }

        // Tenant-admin grant → cooling-off window.
        $this->db->execute(
            "UPDATE `core_support_delegations`
             SET `revocation_state` = 'revocation_pending',
                 `revocation_requested_at` = NOW(),
                 `revocation_effective_at` = DATE_ADD(NOW(), INTERVAL ? HOUR),
                 `revoked_by_user_id` = ?
             WHERE `id` = ?",
            PDOParams::positional([self::COOLING_OFF_HOURS, $revokedByUserId, $delegationId])
        );
    }

    /**
     * Cancel a pending tenant revocation. Only a cas_developer can.
     */
    public function cancelPendingRevocation(int $delegationId, int $cancelledByUserId): void
    {
        // Only the contractual platform owner can cancel a pending tenant
        // revocation (the asymmetric-revocation escape hatch from §14.5).
        $this->assertUserIsPlatformOwner($cancelledByUserId);

        $row = $this->db->selectOne(
            "SELECT `revocation_state` FROM `core_support_delegations`
             WHERE `id` = ? LIMIT 1",
            PDOParams::positional([$delegationId])
        );
        if ($row === null) {
            throw new InvalidArgumentException("Delegation {$delegationId} not found.");
        }
        if (Row::str($row, 'revocation_state') !== 'revocation_pending') {
            throw new InvalidArgumentException(
                'Only pending revocations can be cancelled.'
            );
        }

        $this->db->execute(
            "UPDATE `core_support_delegations`
             SET `revocation_state` = 'active',
                 `revocation_requested_at` = NULL,
                 `revocation_effective_at` = NULL,
                 `revoked_by_user_id` = NULL
             WHERE `id` = ?",
            PDOParams::positional([$delegationId])
        );
    }

    /**
     * Look up an active delegation for (user, tenant, module).
     *
     * @return array{
     *   id:int,
     *   scope:string,
     *   granted_by_type:string,
     *   allowed:list<string>,
     *   denied:list<string>
     * }|null
     */
    public function activeFor(int $userId, int $tenantId, string $moduleKey): ?array
    {
        if ($userId <= 0 || $tenantId <= 0 || $moduleKey === '') {
            return null;
        }

        // Rule 8: grantee status is checked on every authorization decision.
        // A delegation issued to a user who is no longer a cas_admin is
        // treated as inactive — the delegation follows the user's current
        // platform authority, not the authority they held at grant time.
        if (!$this->isCasAdmin($userId)) {
            return null;
        }

        $row = $this->db->selectOne(
            "SELECT `id`, `scope`, `granted_by_type`,
                    `allowed_permissions_json`, `denied_permissions_json`
             FROM `core_support_delegations`
             WHERE `user_id` = ?
               AND `tenant_id` = ?
               AND `module_key` = ?
               AND `revocation_state` = 'active'
               AND `expires_at` > NOW()
             ORDER BY `expires_at` DESC
             LIMIT 1",
            PDOParams::positional([$userId, $tenantId, $moduleKey])
        );
        if ($row === null) {
            return null;
        }

        $allowed = $this->decodeJsonArray(Row::str($row, 'allowed_permissions_json'));
        $denied  = $this->decodeJsonArray(Row::str($row, 'denied_permissions_json'));

        return [
            'id'              => Row::int($row, 'id'),
            'scope'           => Row::str($row, 'scope'),
            'granted_by_type' => Row::str($row, 'granted_by_type'),
            'allowed'         => $allowed,
            'denied'          => $denied,
        ];
    }

    /**
     * Evaluate whether a delegation permits a given permission.
     *
     * Order:
     *   1. scope ceiling: suffix must be compatible with scope
     *   2. explicit denial: permission in denied → DENY
     *   3. explicit allowlist: permission must be present if allowlist non-empty
     *   4. otherwise → ALLOW
     *
     * Assumes the caller has already checked tenant identity and module
     * enabled state. Assumes the permission is tenant-scoped (not cas.*).
     *
     * @param array{scope:string, allowed:list<string>, denied:list<string>} $delegation
     */
    public function permits(array $delegation, string $permission): bool
    {
        if (str_starts_with($permission, 'cas.')) {
            return false;
        }

        $scope   = $delegation['scope'];
        $allowed = $delegation['allowed'];
        $denied  = $delegation['denied'];
        $suffix  = $this->suffixOf($permission);

        // 1. Scope ceiling
        if ($scope === 'full') {
            if (in_array($suffix, self::DESTRUCTIVE_SUFFIXES, true)
                && !in_array($permission, $allowed, true)) {
                return false;
            }
        } else {
            $permittedSuffixes = self::SCOPE_SUFFIXES[$scope] ?? [];
            if (!in_array($suffix, $permittedSuffixes, true)) {
                return false;
            }
        }

        // 2. Explicit denial
        if (in_array($permission, $denied, true)) {
            return false;
        }

        // 3. Explicit allowlist
        if ($allowed !== [] && !in_array($permission, $allowed, true)) {
            return false;
        }

        return true;
    }

    // ---------------------------------------------------------------------
    // Private helpers
    // ---------------------------------------------------------------------

    private function assertGrantorAuthorized(string $grantorType, int $grantedBy, int $tenantId): void
    {
        if ($grantorType === 'cas_developer') {
            // Per RBAC-ARCHITECTURE.md §14.1, only the contractual platform
            // owner can grant cas_developer delegations. A cas_admin operator
            // who is not in platform_owners must fall back to tenant_admin.
            $this->assertUserIsPlatformOwner($grantedBy);
            return;
        }
        // tenant_admin: must have an active tenant role in this tenant.
        $hasRole = $this->db->scalarInt(
            "SELECT COUNT(*) FROM `core_user_roles` ur
             WHERE ur.`user_id` = ? AND ur.`tenant_id` = ?
               AND (ur.`expires_at` IS NULL OR ur.`expires_at` > NOW())",
            PDOParams::positional([$grantedBy, $tenantId])
        );
        if ($hasRole === 0) {
            throw new InvalidArgumentException(
                'Tenant-admin grantor has no active role in this tenant.'
            );
        }
    }

    private function assertGranteeEligible(string $grantorType, int $userId, int $tenantId): void
    {
        if ($grantorType === 'cas_developer') {
            $this->assertUserIsCasAdmin($userId);
            return;
        }
        // tenant_admin can grant to:
        //   (a) a cas_admin (cross-tenant support), or
        //   (b) a user with an active role in this tenant (intra-tenant).
        if ($this->isCasAdmin($userId)) {
            return;
        }
        $hasRole = $this->db->scalarInt(
            "SELECT COUNT(*) FROM `core_user_roles` ur
             WHERE ur.`user_id` = ? AND ur.`tenant_id` = ?
               AND (ur.`expires_at` IS NULL OR ur.`expires_at` > NOW())",
            PDOParams::positional([$userId, $tenantId])
        );
        if ($hasRole === 0) {
            throw new InvalidArgumentException(
                'Tenant admin may only grant to a cas_admin or to a user with an active role in this tenant.'
            );
        }
    }

    private function assertUserIsCasAdmin(int $userId): void
    {
        if (!$this->isCasAdmin($userId)) {
            throw new InvalidArgumentException(
                "User {$userId} does not hold a cas_admin platform role."
            );
        }
    }

    private function assertUserIsPlatformOwner(int $userId): void
    {
        if (!$this->owners->isOwner($userId)) {
            throw new InvalidArgumentException(
                "User {$userId} is not a designated platform owner — only the "
                . "contractual developer can perform this action."
            );
        }
    }

    private function isCasAdmin(int $userId): bool
    {
        $count = $this->db->scalarInt(
            "SELECT COUNT(*) FROM `core_user_platform_roles` upr
             JOIN `core_platform_roles` pr ON pr.`id` = upr.`role_id`
             WHERE upr.`user_id` = ?
               AND pr.`slug` = 'cas_admin'
               AND (upr.`expires_at` IS NULL OR upr.`expires_at` > NOW())",
            PDOParams::positional([$userId])
        );
        return $count > 0;
    }

    /**
     * Build the allowlist for a grant by materializing the scope ceiling
     * against the current set of active permissions for the module, then
     * merging in any explicit extras.
     *
     * @param string[] $allowedExtra
     * @return list<string>
     */
    private function materializeAllowList(string $scope, string $moduleKey, array $allowedExtra): array
    {
        $rows = $this->db->select(
            "SELECT `slug`, `module` FROM `core_permissions`
             WHERE SUBSTRING_INDEX(`module`, '.', 1) = ?
               AND `is_active` = 1",
            PDOParams::positional([$moduleKey])
        );

        $allow = [];
        foreach ($rows as $r) {
            $slug   = Row::str($r, 'slug');
            $suffix = $this->suffixOf($slug);

            if ($scope === 'full') {
                if (!in_array($suffix, self::DESTRUCTIVE_SUFFIXES, true)) {
                    $allow[] = $slug;
                }
            } elseif (in_array($suffix, self::SCOPE_SUFFIXES[$scope] ?? [], true)) {
                $allow[] = $slug;
            }
        }

        foreach ($this->normalizeSlugs($allowedExtra) as $slug) {
            $allow[] = $slug;
        }

        $allow = array_values(array_unique($allow));
        sort($allow);
        return $allow;
    }

    /** @param string[] $slugs */
    private function assertValidPermissionSlugs(string $moduleKey, array $slugs): void
    {
        if ($slugs === []) {
            return;
        }
        $placeholders = implode(',', array_fill(0, count($slugs), '?'));
        $valid = $this->db->select(
            "SELECT `slug` FROM `core_permissions`
             WHERE `slug` IN ({$placeholders})
               AND SUBSTRING_INDEX(`module`, '.', 1) = ?
               AND `is_active` = 1",
            PDOParams::positional(array_merge($slugs, [$moduleKey]))
        );
        $validSlugs = array_map(static fn ($r) => Row::str($r, 'slug'), $valid);
        $invalid = array_diff($slugs, $validSlugs);
        if ($invalid !== []) {
            throw new InvalidArgumentException(
                'Unknown or inactive permissions for module ' . $moduleKey . ': '
                . implode(', ', $invalid)
            );
        }
    }

    private function moduleExists(string $moduleKey): bool
    {
        return $this->db->scalarInt(
            "SELECT COUNT(*) FROM `core_modules` WHERE `module_key` = ?",
            PDOParams::positional([$moduleKey])
        ) > 0;
    }

    /**
     * @param string[] $slugs
     * @return list<string>
     */
    private function normalizeSlugs(array $slugs): array
    {
        $out = [];
        foreach ($slugs as $s) {
            $s = trim((string) $s);
            if ($s === '') {
                continue;
            }
            if (str_starts_with($s, 'cas.')) {
                throw new InvalidArgumentException(
                    "cas.* permissions cannot be granted via support delegation: {$s}."
                );
            }
            $out[] = $s;
        }
        return array_values(array_unique($out));
    }

    /** @return list<string> */
    private function decodeJsonArray(string $json): array
    {
        if ($json === '') {
            return [];
        }
        $decoded = json_decode($json, true);
        return is_array($decoded) ? array_values($decoded) : [];
    }

    private function suffixOf(string $slug): string
    {
        $pos = strrpos($slug, '.');
        return $pos === false ? $slug : substr($slug, $pos + 1);
    }
}

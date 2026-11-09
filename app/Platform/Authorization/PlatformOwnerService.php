<?php
declare(strict_types=1);

namespace CorporateAid\Platform\Authorization;

use CorporateAid\Core\Database;
use CorporateAid\Core\PDOParams;
use CorporateAid\Core\Row;

/**
 * Read-only service for the platform_owners designation.
 *
 * The web application uses this to distinguish the contractual developer
 * from a regular cas_admin operator. The table itself is written by
 * manual DB operations or a signed CLI command — never by a route.
 *
 * Per RBAC-ARCHITECTURE.md §14.1: the developer is the platform owner
 * under contract. Only they can grant cas_developer delegations, and
 * only they can cancel pending tenant revocations.
 */
final class PlatformOwnerService
{
    public function __construct(private Database $db) {}

    /**
     * Is this user the contractual platform owner?
     */
    public function isOwner(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }
        return $this->db->scalarInt(
            "SELECT COUNT(*) FROM `platform_owners` WHERE `user_id` = ?",
            PDOParams::positional([$userId])
        ) > 0;
    }

    /**
     * Returns the designation of the owner ("Founder", "CTO", etc.) or
     * null if the user is not an owner.
     */
    public function designation(int $userId): ?string
    {
        if ($userId <= 0) {
            return null;
        }
        $row = $this->db->selectOne(
            "SELECT `designation` FROM `platform_owners` WHERE `user_id` = ? LIMIT 1",
            PDOParams::positional([$userId])
        );
        return $row === null ? null : Row::str($row, 'designation');
    }
}

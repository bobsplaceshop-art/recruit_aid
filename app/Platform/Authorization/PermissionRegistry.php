<?php
declare(strict_types=1);

namespace CorporateAid\Platform\Authorization;

use CorporateAid\Core\Database;
use CorporateAid\Core\PDOParams;
use CorporateAid\Core\Row;
use RuntimeException;

/**
 * Application permission registration.
 *
 * Applications declare their permissions via
 * ModuleContract::getPermissionDefinitions(). This class:
 *   1. Validates the namespace matches the module's key.
 *   2. Rejects registration under cas.* and core.* (protected).
 *   3. Upserts to core_permission_registrations.
 *   4. Does NOT auto-insert into core_permissions - activation is
 *      explicit (see activate()).
 *
 * See docs/RBAC-ARCHITECTURE.md §8.
 */
final class PermissionRegistry
{
    private const PROTECTED_NAMESPACES = ['cas', 'core'];

    public function __construct(private Database $db) {}

    /**
     * @param array<int, array{slug:string, name:string, description:string, sensitivity:string}> $definitions
     */
    public function register(string $moduleKey, array $definitions): void
    {
        foreach ($definitions as $def) {
            $slug = $def['slug'] ?? '';
            if ($slug === '') {
                throw new RuntimeException("Permission definition missing 'slug' for module '{$moduleKey}'.");
            }

            $this->assertNamespaceOwnership($moduleKey, $slug);

            $this->db->execute(
                "INSERT INTO `core_permission_registrations`
                    (`module_key`, `slug`, `name`, `description`, `sensitivity`)
                 VALUES (?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    `name` = VALUES(`name`),
                    `description` = VALUES(`description`),
                    `sensitivity` = VALUES(`sensitivity`)",
                PDOParams::positional([
                    $moduleKey,
                    $slug,
                    (string) ($def['name'] ?? $slug),
                    (string) ($def['description'] ?? ''),
                    (string) ($def['sensitivity'] ?? 'public'),
                ])
            );
        }
    }

    /**
     * Moves a registered permission into core_permissions (making it
     * assignable). Called by an admin or by a migration.
     */
    public function activate(string $slug): void
    {
        $reg = $this->db->selectOne(
            "SELECT `module_key`, `name`, `description`, `sensitivity`
             FROM `core_permission_registrations` WHERE `slug` = ? LIMIT 1",
            PDOParams::positional([$slug])
        );

        if ($reg === null) {
            throw new RuntimeException("Cannot activate unregistered permission: {$slug}");
        }

        $this->db->execute(
            "INSERT INTO `core_permissions`
                (`slug`, `module`, `description`, `scope`, `module_key`, `sensitivity`, `is_active`)
             VALUES (?, ?, ?, 'app', ?, ?, 1)
             ON DUPLICATE KEY UPDATE
                `description` = VALUES(`description`),
                `sensitivity` = VALUES(`sensitivity`),
                `is_active`   = 1",
            PDOParams::positional([
                $slug,
                Row::str($reg, 'module_key'),
                Row::str($reg, 'description'),
                Row::str($reg, 'module_key'),
                Row::str($reg, 'sensitivity', 'public'),
            ])
        );
    }

    public function deactivate(string $slug): void
    {
        $this->db->execute(
            "UPDATE `core_permissions` SET `is_active` = 0 WHERE `slug` = ?",
            PDOParams::positional([$slug])
        );
    }

    private function assertNamespaceOwnership(string $moduleKey, string $slug): void
    {
        $parts = explode('.', $slug, 2);
        $namespace = $parts[0] ?? '';

        if (in_array($namespace, self::PROTECTED_NAMESPACES, true)) {
            throw new RuntimeException(
                "Module '{$moduleKey}' may not register permission '{$slug}' — " .
                "namespace '{$namespace}' is protected."
            );
        }

        if ($namespace !== $moduleKey) {
            throw new RuntimeException(
                "Module '{$moduleKey}' may not register permission '{$slug}' — " .
                "namespace '{$namespace}' does not match module key."
            );
        }
    }
}
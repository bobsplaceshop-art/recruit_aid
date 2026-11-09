<?php
declare(strict_types=1);

namespace CorporateAid\Platform\Repositories;

use CorporateAid\Core\Database;
use CorporateAid\Core\PDOParams;
use InvalidArgumentException;

/**
 * All SQL against core_tenants.
 *
 * Tenants are global — no tenant_id filter on their own queries. This
 * is the one repository in the codebase that doesn't use TenantContext.
 *
 * The `code` column is UNIQUE and immutable after creation. It's a
 * tenant identity key, not a display label. Only `name`, colors,
 * logo_path, login_message, and status are mutable.
 *
 * Soft delete: `deleted_at` is set when a tenant is archived. The
 * `findAll()` default excludes soft-deleted; `findById()` includes them
 * so an admin can restore an archived tenant.
 */
final class TenantRepository
{
    public function __construct(private Database $db) {}

    /**
     * List tenants, optionally filtered by status. Excludes soft-deleted
     * unless $status = 'archived' and $includeArchived is true.
     *
     * @return array<int, array<string, mixed>>
     */
    public function findAll(?string $status = null): array
    {
        $where = ["`deleted_at` IS NULL"];
        $params = [];

        if ($status !== null && in_array($status, ['active', 'suspended', 'archived'], true)) {
            $where[] = "`status` = ?";
            $params[] = $status;
        }

        return $this->db->select(
            "SELECT `id`, `code`, `name`, `status`, `created_at`,
                    `primary_color`, `secondary_color`
             FROM `core_tenants`
             WHERE " . implode(' AND ', $where) . "
             ORDER BY `name` ASC",
            PDOParams::positional($params)
        );
    }

    /**
     * Find a tenant by ID. Includes soft-deleted so an admin can inspect
     * an archived tenant before restoring it.
     *
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        return $this->db->selectOne(
            "SELECT * FROM `core_tenants` WHERE `id` = ? LIMIT 1",
            PDOParams::positional([$id])
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByCode(string $code): ?array
    {
        return $this->db->selectOne(
            "SELECT * FROM `core_tenants` WHERE `code` = ? LIMIT 1",
            PDOParams::positional([$code])
        );
    }

    /**
     * @param array{code:string, name:string, primary_color?:string, secondary_color?:string, login_message?:?string} $data
     * @return int The new tenant ID.
     */
    public function create(array $data): int
    {
        $code = trim($data['code'] ?? '');
        $name = trim($data['name'] ?? '');

        if ($code === '' || $name === '') {
            throw new InvalidArgumentException('Tenant code and name are required.');
        }

        if ($this->findByCode($code) !== null) {
            throw new InvalidArgumentException("Tenant code '{$code}' is already in use.");
        }

        return $this->db->insert(
            "INSERT INTO `core_tenants`
                (`code`, `name`, `primary_color`, `secondary_color`, `login_message`, `status`)
             VALUES (?, ?, ?, ?, ?, 'active')",
            PDOParams::positional([
                $code,
                $name,
                $data['primary_color'] ?? null,
                $data['secondary_color'] ?? null,
                $data['login_message'] ?? null,
            ])
        );
    }

    /**
     * Update mutable fields. `code` cannot be changed.
     *
     * @param array{name?:string, primary_color?:?string, secondary_color?:?string, logo_path?:?string, login_message?:?string} $data
     */
    public function update(int $id, array $data): void
    {
        $sets = [];
        $params = [];

        if (isset($data['name'])) {
            $name = trim((string) $data['name']);
            if ($name === '') {
                throw new InvalidArgumentException('Tenant name cannot be empty.');
            }
            $sets[] = "`name` = ?";
            $params[] = $name;
        }
        if (array_key_exists('primary_color', $data)) {
            $sets[] = "`primary_color` = ?";
            $params[] = $data['primary_color'];
        }
        if (array_key_exists('secondary_color', $data)) {
            $sets[] = "`secondary_color` = ?";
            $params[] = $data['secondary_color'];
        }
        if (array_key_exists('logo_path', $data)) {
            $sets[] = "`logo_path` = ?";
            $params[] = $data['logo_path'];
        }
        if (array_key_exists('login_message', $data)) {
            $sets[] = "`login_message` = ?";
            $params[] = $data['login_message'];
        }

        if ($sets === []) {
            return;
        }

        $params[] = $id;
        $this->db->execute(
            "UPDATE `core_tenants` SET " . implode(', ', $sets) . " WHERE `id` = ?",
            PDOParams::positional($params)
        );
    }

    public function suspend(int $id): void
    {
        $this->transitionStatus($id, 'active', 'suspended');
    }

    public function archive(int $id): void
    {
        // archived_from: active or suspended. Both are legitimate.
        $this->db->execute(
            "UPDATE `core_tenants` SET `status` = 'archived', `deleted_at` = NOW()
             WHERE `id` = ? AND `status` IN ('active', 'suspended')",
            PDOParams::positional([$id])
        );
    }

    public function restore(int $id): void
    {
        $this->db->execute(
            "UPDATE `core_tenants` SET `status` = 'active', `deleted_at` = NULL
             WHERE `id` = ? AND `status` = 'archived'",
            PDOParams::positional([$id])
        );
    }

    /**
     * Aggregate counts per status. Used by the dashboard (aggregate-only).
     *
     * @return array{active:int, suspended:int, archived:int}
     */
    public function countByStatus(): array
    {
        $rows = $this->db->select(
            "SELECT `status`, COUNT(*) AS n FROM `core_tenants`
             WHERE `deleted_at` IS NULL GROUP BY `status`"
        );
        $out = ['active' => 0, 'suspended' => 0, 'archived' => 0];
        foreach ($rows as $r) {
            $status = (string) \CorporateAid\Core\Row::str($r, 'status');
            if (array_key_exists($status, $out)) {
                $out[$status] = (int) \CorporateAid\Core\Row::int($r, 'n');
            }
        }
        return $out;
    }

    private function transitionStatus(int $id, string $from, string $to): void
    {
        $this->db->execute(
            "UPDATE `core_tenants` SET `status` = ? WHERE `id` = ? AND `status` = ?",
            PDOParams::positional([$to, $id, $from])
        );
    }
}

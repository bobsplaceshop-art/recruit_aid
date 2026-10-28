<?php
declare(strict_types=1);

namespace CorporateAid\Platform\Repositories;

use CorporateAid\Core\Database;
use CorporateAid\Core\PDOParams;
use CorporateAid\Core\Row;

final class RoleRepository
{
    public function __construct(private Database $db) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        return $this->db->select("SELECT `id`, `name`, `slug`, `description`, `is_system` FROM `core_roles` ORDER BY `name`");
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findBySlug(string $slug): ?array
    {
        return $this->db->selectOne(
            "SELECT * FROM `core_roles` WHERE `slug` = ? LIMIT 1",
            PDOParams::positional([$slug])
        );
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function permissionsGroupedByModule(): array
    {
        $rows = $this->db->select("SELECT `id`, `slug`, `module`, `description` FROM `core_permissions` ORDER BY `module`, `slug`");
        $grouped = [];
        foreach ($rows as $r) {
            $module = Row::str($r, 'module');
            $grouped[$module][] = $r;
        }
        return $grouped;
    }
}

<?php
declare(strict_types=1);

namespace CorporateAid\Platform\Repositories;

use CorporateAid\Core\Database;

final class RoleRepository
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function all(): array
    {
        return $this->db->select("SELECT `id`, `name`, `slug`, `description`, `is_system` FROM `core_roles` ORDER BY `name`");
    }

    public function findBySlug(string $slug): ?array
    {
        return $this->db->selectOne("SELECT * FROM `core_roles` WHERE `slug` = ? LIMIT 1", [$slug]);
    }

    public function permissionsGroupedByModule(): array
    {
        $rows = $this->db->select("SELECT `id`, `slug`, `module`, `description` FROM `core_permissions` ORDER BY `module`, `slug`");
        $grouped = [];
        foreach ($rows as $r) {
            $grouped[$r['module']][] = $r;
        }
        return $grouped;
    }
}

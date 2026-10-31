<?php
declare(strict_types=1);

namespace CorporateAid\Recruit\Repositories;

use CorporateAid\Core\Database;
use CorporateAid\Core\PDOParams;
use CorporateAid\Core\Row;
use CorporateAid\Core\TenantContext;
use CorporateAid\Platform\Support\CodeGenerator;
use CorporateAid\Recruit\Models\Contact;

final class ContactRepository implements \CorporateAid\Platform\LiveSearch\LiveSearchable
{
    public function __construct(
        private Database $db,
        private TenantContext $tenant,
        private CodeGenerator $codes
    ) {}

    public function findById(int $id): ?Contact
    {
        $row = $this->db->selectOne(
            "SELECT * FROM `recruit_client_contacts` WHERE `id` = ? AND `tenant_id` = ? LIMIT 1",
            PDOParams::positional([$id, $this->tenant->id()])
        );
        return $row ? $this->hydrateRoles(Contact::fromRow($row)) : null;
    }

    public function findByCode(string $code, bool $includeDeleted = false): ?Contact
    {
        $sql = "SELECT * FROM `recruit_client_contacts` WHERE `code` = ? AND `tenant_id` = ?";
        if (!$includeDeleted) {
            $sql .= " AND `deleted_at` IS NULL";
        }
        $sql .= " LIMIT 1";

        $row = $this->db->selectOne($sql, PDOParams::positional([$code, $this->tenant->id()]));
        return $row ? $this->hydrateRoles(Contact::fromRow($row)) : null;
    }

    public function findByEmail(string $email, ?int $excludeId = null): ?Contact
    {
        $sql = "SELECT * FROM `recruit_client_contacts`
                WHERE LOWER(`email`) = LOWER(?) AND `tenant_id` = ? AND `deleted_at` IS NULL";
        $params = [$email, $this->tenant->id()];
        if ($excludeId !== null) {
            $sql .= " AND `id` != ?";
            $params[] = $excludeId;
        }
        $sql .= " LIMIT 1";

        $row = $this->db->selectOne($sql, PDOParams::positional($params));
        return $row === null ? null : $this->hydrateRoles(Contact::fromRow($row));
    }

    /** @return string[] */
    public function rolesForContact(int $contactId): array
    {
        $rows = $this->db->select(
            "SELECT `role_slug` FROM `recruit_contact_roles` WHERE `contact_id` = ? ORDER BY `role_slug`",
            PDOParams::positional([$contactId])
        );
        return array_map(fn(array $r) => Row::str($r, 'role_slug'), $rows);
    }

    /**
     * @param int[] $contactIds
     * @return array<int, string[]>
     */
    public function rolesForContacts(array $contactIds): array
    {
        if (empty($contactIds)) return [];
        $placeholders = implode(',', array_fill(0, count($contactIds), '?'));
        $rows = $this->db->select(
            "SELECT `contact_id`, `role_slug` FROM `recruit_contact_roles`
             WHERE `contact_id` IN ({$placeholders})
             ORDER BY `contact_id`, `role_slug`",
            PDOParams::positional($contactIds)
        );
        $out = [];
        foreach ($rows as $r) {
            $cid = Row::int($r, 'contact_id');
            $out[$cid][] = Row::str($r, 'role_slug');
        }
        return $out;
    }

    private function hydrateRoles(Contact $contact): Contact
    {
        $contact->roles = $this->rolesForContact($contact->id);
        return $contact;
    }

    /**
     * @param string[] $roleSlugs
     */
    public function syncRoles(int $contactId, array $roleSlugs): void
    {
        $clean = [];
        foreach ($roleSlugs as $slug) {
            $s = strtolower(trim($slug));
            if ($s !== '') $clean[$s] = true;
        }
        $clean = array_keys($clean);

        $this->db->execute(
            "DELETE FROM `recruit_contact_roles` WHERE `contact_id` = ? AND `tenant_id` = ?",
            PDOParams::positional([$contactId, $this->tenant->id()])
        );

        if (!empty($clean)) {
            $values = [];
            $params = [];
            foreach ($clean as $slug) {
                $values[] = "(?, ?, ?)";
                $params[] = $this->tenant->id();
                $params[] = $contactId;
                $params[] = $slug;
            }
            $this->db->execute(
                "INSERT INTO `recruit_contact_roles` (`tenant_id`, `contact_id`, `role_slug`) VALUES " . implode(', ', $values),
                PDOParams::positional($params)
            );
        }

        $legacy = ['is_primary', 'is_hiring_manager', 'is_finance', 'is_hr', 'is_decision_maker'];
        $sets = [];
        $bParams = [];
        foreach ($legacy as $flag) {
            $sets[] = "`{$flag}` = ?";
            $bParams[] = in_array($flag, $clean, true) ? 1 : 0;
        }
        $bParams[] = $contactId;
        $bParams[] = $this->tenant->id();
        $this->db->execute(
            "UPDATE `recruit_client_contacts` SET " . implode(', ', $sets) . " WHERE `id` = ? AND `tenant_id` = ?",
            PDOParams::positional($bParams)
        );
    }

    /** @return Contact[] */
    public function forOrganisation(int $organisationId, bool $includeDeleted = false): array
    {
        $sql = "SELECT * FROM `recruit_client_contacts` WHERE `client_organisation_id` = ? AND `tenant_id` = ?";
        $params = [$organisationId, $this->tenant->id()];
        if (!$includeDeleted) {
            $sql .= " AND `deleted_at` IS NULL";
        }
        $sql .= " ORDER BY `is_primary` DESC, `last_name` ASC, `first_name` ASC";

        $rows = $this->db->select($sql, PDOParams::positional($params));
        $contacts = array_map([Contact::class, 'fromRow'], $rows);

        $ids = array_map(fn(Contact $c) => $c->id, $contacts);
        $roleMap = $this->rolesForContacts($ids);
        foreach ($contacts as $c) {
            $c->roles = $roleMap[$c->id] ?? [];
        }
        return $contacts;
    }

    public function countForOrganisation(int $organisationId): int
    {
        return $this->db->scalarInt(
            "SELECT COUNT(*) FROM `recruit_client_contacts`
             WHERE `client_organisation_id` = ? AND `tenant_id` = ? AND `deleted_at` IS NULL",
            PDOParams::positional([$organisationId, $this->tenant->id()])
        );
    }

    public function codeExists(string $code): bool
    {
        return $this->db->scalarInt(
            "SELECT COUNT(*) FROM `recruit_client_contacts` WHERE `code` = ? AND `tenant_id` = ?",
            PDOParams::positional([$code, $this->tenant->id()])
        ) > 0;
    }

    public function nextCode(): string
    {
        return $this->codes->nextForTenant('rec_client_contact_code', 'CNT', $this->tenant->id());
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $sql = "INSERT INTO `recruit_client_contacts`
            (`tenant_id`, `client_organisation_id`, `code`, `first_name`, `last_name`,
             `position`, `department`, `email`, `phone`, `alt_phone`,
             `country_code`, `preferred_communication`,
             `is_primary`, `is_hiring_manager`, `is_finance`, `is_hr`, `is_decision_maker`,
             `notes`, `created_by`)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        return $this->db->insert($sql, PDOParams::positional([
            $this->tenant->id(),
            $data['client_organisation_id'],
            $data['code'],
            $data['first_name'],
            $data['last_name'],
            $data['position'] ?? null,
            $data['department'] ?? null,
            $data['email'] ?? null,
            $data['phone'] ?? null,
            $data['alt_phone'] ?? null,
            $data['country_code'] ?? null,
            $data['preferred_communication'] ?? 'email',
            !empty($data['is_primary']) ? 1 : 0,
            !empty($data['is_hiring_manager']) ? 1 : 0,
            !empty($data['is_finance']) ? 1 : 0,
            !empty($data['is_hr']) ? 1 : 0,
            !empty($data['is_decision_maker']) ? 1 : 0,
            $data['notes'] ?? null,
            $data['created_by'] ?? null,
        ]));
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, array{old:mixed,new:mixed}>
     */
    public function update(int $id, array $data): array
    {
        $existing = $this->findById($id);
        if (!$existing) {
            throw new \RuntimeException('Contact not found.');
        }

        $allowed = [
            'first_name', 'last_name', 'position', 'department',
            'email', 'phone', 'alt_phone', 'country_code', 'preferred_communication',
            'is_primary', 'is_hiring_manager', 'is_finance', 'is_hr', 'is_decision_maker',
            'notes',
        ];

        $sets = [];
        $params = [];
        $changes = [];

        foreach ($allowed as $col) {
            if (!array_key_exists($col, $data)) continue;

            $newVal = $data[$col];

            $prop = match ($col) {
                'first_name'              => 'firstName',
                'last_name'               => 'lastName',
                'alt_phone'               => 'altPhone',
                'country_code'            => 'countryCode',
                'preferred_communication' => 'preferredCommunication',
                'is_primary'              => 'isPrimary',
                'is_hiring_manager'       => 'isHiringManager',
                'is_finance'              => 'isFinance',
                'is_hr'                   => 'isHr',
                'is_decision_maker'       => 'isDecisionMaker',
                default                   => $col,
            };
            $oldVal = $existing->$prop ?? null;

            $newForCompare = $newVal;
            if (in_array($col, ['is_primary','is_hiring_manager','is_finance','is_hr','is_decision_maker'], true)) {
                $newForCompare = (bool) $newVal;
            }

            if ($oldVal != $newForCompare) {
                $changes[$col] = ['old' => $oldVal, 'new' => $newForCompare];
            }

            $sets[] = "`{$col}` = ?";
            if (in_array($col, ['is_primary','is_hiring_manager','is_finance','is_hr','is_decision_maker'], true)) {
                $params[] = !empty($newVal) ? 1 : 0;
            } else {
                $params[] = $newVal;
            }
        }

        if (empty($sets)) return [];

        $params[] = $id;
        $params[] = $this->tenant->id();
        $this->db->execute(
            "UPDATE `recruit_client_contacts` SET " . implode(', ', $sets) . " WHERE `id` = ? AND `tenant_id` = ?",
            PDOParams::positional($params)
        );

        return $changes;
    }

    public function softDelete(int $id): void
    {
        $this->db->execute(
            "UPDATE `recruit_client_contacts` SET `deleted_at` = NOW() WHERE `id` = ? AND `tenant_id` = ?",
            PDOParams::positional([$id, $this->tenant->id()])
        );
    }

    public function restore(int $id): void
    {
        $this->db->execute(
            "UPDATE `recruit_client_contacts` SET `deleted_at` = NULL WHERE `id` = ? AND `tenant_id` = ?",
            PDOParams::positional([$id, $this->tenant->id()])
        );
    }

    /** @return Contact[] */
    public function allActive(): array
    {
        $rows = $this->db->select(
            "SELECT * FROM `recruit_client_contacts`
             WHERE `tenant_id` = ? AND `deleted_at` IS NULL
             ORDER BY `client_organisation_id`, `is_primary` DESC, `last_name` ASC, `first_name` ASC",
            PDOParams::positional([$this->tenant->id()])
        );
        $contacts = array_map([Contact::class, 'fromRow'], $rows);
        $ids = array_map(fn(Contact $c) => $c->id, $contacts);
        $roleMap = $this->rolesForContacts($ids);
        foreach ($contacts as $c) {
            $c->roles = $roleMap[$c->id] ?? [];
        }
        return $contacts;
    }

    /**
     * @param array{q?: string, org_id?: int, role?: string} $filters
     * @return array{rows: array<int, array{contact: Contact, organisation_name: string, organisation_code: string}>, total: int, page: int, per_page: int, total_pages: int}
     */
    public function paginateAll(array $filters = [], int $page = 1, int $perPage = 50): array
    {
        $page = max(1, $page);
        $perPage = max(1, min($perPage, 100));
        $offset = ($page - 1) * $perPage;

        $where = ['c.`deleted_at` IS NULL', 'o.`deleted_at` IS NULL', 'c.`tenant_id` = ?', 'o.`tenant_id` = ?'];
        $params = [$this->tenant->id(), $this->tenant->id()];

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $where[] = '(c.`first_name` LIKE ? OR c.`last_name` LIKE ? OR c.`email` LIKE ? OR c.`position` LIKE ? OR o.`name` LIKE ?)';
            $like = '%' . $q . '%';
            for ($i = 0; $i < 5; $i++) $params[] = $like;
        }

        $orgId = (int) ($filters['org_id'] ?? 0);
        if ($orgId > 0) {
            $where[] = 'c.`client_organisation_id` = ?';
            $params[] = $orgId;
        }

        $role = (string) ($filters['role'] ?? '');
        if ($role !== '') {
            $legacyFlags = ['is_primary', 'is_hiring_manager', 'is_finance', 'is_hr', 'is_decision_maker'];
            if (in_array($role, $legacyFlags, true)) {
                $where[] = "(EXISTS (SELECT 1 FROM `recruit_contact_roles` r WHERE r.`contact_id` = c.`id` AND r.`role_slug` = ?) OR c.`{$role}` = 1)";
                $params[] = $role;
            } else {
                $where[] = "EXISTS (SELECT 1 FROM `recruit_contact_roles` r WHERE r.`contact_id` = c.`id` AND r.`role_slug` = ?)";
                $params[] = $role;
            }
        }

        $whereSql = 'WHERE ' . implode(' AND ', $where);

        $total = $this->db->scalarInt(
            "SELECT COUNT(*) FROM `recruit_client_contacts` c
             INNER JOIN `recruit_client_organisations` o ON o.`id` = c.`client_organisation_id`
             {$whereSql}",
            PDOParams::positional($params)
        );

        $rows = $this->db->select(
            "SELECT c.*,
                    o.`name` AS `organisation_name`,
                    o.`code` AS `organisation_code`
             FROM `recruit_client_contacts` c
             INNER JOIN `recruit_client_organisations` o ON o.`id` = c.`client_organisation_id`
             {$whereSql}
             ORDER BY o.`name` ASC, c.`is_primary` DESC, c.`last_name` ASC, c.`first_name` ASC
             LIMIT " . $perPage . " OFFSET " . $offset,
            PDOParams::positional($params)
        );

        $out = [];
        $ids = [];
        foreach ($rows as $row) {
            $contact = Contact::fromRow($row);
            $out[] = [
                'contact'           => $contact,
                'organisation_name' => Row::str($row, 'organisation_name'),
                'organisation_code' => Row::str($row, 'organisation_code'),
            ];
            $ids[] = $contact->id;
        }
        $roleMap = $this->rolesForContacts($ids);
        foreach ($out as $idx => $row) {
            $out[$idx]['contact']->roles = $roleMap[$row['contact']->id] ?? [];
        }

        $totalPages = max(1, (int) ceil($total / $perPage));

        return [
            'rows'        => $out,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => $totalPages,
        ];
    }

    public function liveSearchConfig(): array
    {
        return [
            'table'            => 'recruit_client_contacts',
            'alias'            => 'c',
            'columns'          => ['c.first_name', 'c.last_name', 'c.email', 'c.position'],
            'order'            => 'c.last_name ASC, c.first_name ASC',
            'permission'       => 'recruit.contacts.view',
            'baseFilters'      => ['c.`deleted_at` IS NULL'],
            'joins'            => [
                'INNER JOIN `recruit_client_organisations` o ON o.`id` = c.`client_organisation_id` AND o.`tenant_id` = c.`tenant_id`',
                'LEFT JOIN `recruit_contact_roles` cr ON cr.`contact_id` = c.`id` AND cr.`tenant_id` = c.`tenant_id`',
            ],
            'rowView'          => 'contacts/_row',
            'extraColumns'     => ['o.name AS organisation_name', 'o.code AS organisation_code'],
            'aggregateColumns' => ["GROUP_CONCAT(cr.role_slug ORDER BY cr.role_slug) AS role_slugs"],
            'groupBy' => 'c.id',
        ];
    }
}

<?php
declare(strict_types=1);

namespace CorporateAid\Platform\Services;

use CorporateAid\Core\Auth;
use CorporateAid\Core\Database;
use CorporateAid\Core\PDOParams;
use CorporateAid\Core\Row;

/**
 * Cross-entity global search. Substring LIKE %term% per entity,
 * permission-filtered by what the current user can view.
 */
final class GlobalSearchService
{
    public function __construct(
        private Database $db,
        private ?Auth $auth = null
    ) {}

    /**
     * Run the search across all searchable entities the user can view.
     *
     * @return array{
     *   term: string,
     *   total: int,
     *   groups: array<int, array{key: string, label: string, items: array<int, array{title: string, subtitle: string, url: string, meta: string}>}>
     * }
     */
    public function search(string $term, int $limitPerGroup = 20): array
    {
        $term = trim($term);
        if ($term === '') {
            return ['term' => '', 'total' => 0, 'groups' => []];
        }

        $like = '%' . $term . '%';
        $groups = [];
        $total = 0;
        $user = $this->auth?->user();

        // Organisations
        if ($user !== null && $user->can('recruit.organisations.view')) {
            $rows = $this->db->select(
                "SELECT id, code, name, legal_name, email, industry
                 FROM `recruit_client_organisations`
                 WHERE `deleted_at` IS NULL
                   AND (`name` LIKE ? OR `legal_name` LIKE ? OR `email` LIKE ? OR `industry` LIKE ?)
                 ORDER BY `name` ASC
                 LIMIT " . (int) $limitPerGroup,
                PDOParams::positional([$like, $like, $like, $like])
            );
            $items = array_map(fn($r) => [
                'title'    => Row::str($r, 'name'),
                'subtitle' => Row::str($r, 'legal_name') ?: (Row::str($r, 'industry') ?: 'Organisation'),
                'meta'     => Row::str($r, 'code'),
                'url'      => '/organisations/' . Row::str($r, 'code'),
            ], $rows);
            if (!empty($items)) {
                $groups[] = ['key' => 'organisations', 'label' => 'Organisations', 'items' => $items];
                $total += count($items);
            }
        }

        // Contacts
        if ($user !== null && $user->can('recruit.contacts.view')) {
            $rows = $this->db->select(
                "SELECT c.id, c.code, c.first_name, c.last_name, c.email, c.position,
                        o.code AS org_code, o.name AS org_name
                 FROM `recruit_client_contacts` c
                 INNER JOIN `recruit_client_organisations` o ON o.id = c.organisation_id
                 WHERE c.`deleted_at` IS NULL AND o.`deleted_at` IS NULL
                   AND (c.`first_name` LIKE ? OR c.`last_name` LIKE ? OR c.`email` LIKE ?
                        OR c.`position` LIKE ? OR c.`department` LIKE ?)
                 ORDER BY c.`last_name` ASC, c.`first_name` ASC
                 LIMIT " . (int) $limitPerGroup,
                PDOParams::positional([$like, $like, $like, $like, $like])
            );
            $items = array_map(fn($r) => [
                'title'    => trim(Row::str($r, 'first_name') . ' ' . Row::str($r, 'last_name')),
                'subtitle' => Row::str($r, 'position') ?: (Row::str($r, 'email') ?: 'Contact'),
                'meta'     => Row::str($r, 'org_name') . ' · ' . Row::str($r, 'code'),
                'url'      => '/organisations/' . Row::str($r, 'org_code') . '/contacts/' . Row::str($r, 'code'),
            ], $rows);
            if (!empty($items)) {
                $groups[] = ['key' => 'contacts', 'label' => 'Contacts', 'items' => $items];
                $total += count($items);
            }
        }

        // Engagements
        if ($user !== null && $user->can('recruit.engagements.view')) {
            $rows = $this->db->select(
                "SELECT id, code, name, service_type, notes
                 FROM `recruit_engagements`
                 WHERE `deleted_at` IS NULL
                   AND (`name` LIKE ? OR `notes` LIKE ? OR `scope_description` LIKE ?)
                 ORDER BY `name` ASC
                 LIMIT " . (int) $limitPerGroup,
                PDOParams::positional([$like, $like, $like])
            );
            $items = array_map(fn($r) => [
                'title'    => Row::str($r, 'name'),
                'subtitle' => Row::str($r, 'service_type') ?: 'Engagement',
                'meta'     => Row::str($r, 'code'),
                'url'      => '/engagements/' . Row::str($r, 'code'),
            ], $rows);
            if (!empty($items)) {
                $groups[] = ['key' => 'engagements', 'label' => 'Engagements', 'items' => $items];
                $total += count($items);
            }
        }

        // Vacancies
        if ($user !== null && $user->can('recruit.vacancies.view')) {
            $rows = $this->db->select(
                "SELECT id, code, title, department, city
                 FROM `recruit_vacancies`
                 WHERE `deleted_at` IS NULL
                   AND (`title` LIKE ? OR `department` LIKE ? OR `city` LIKE ? OR `description` LIKE ?)
                 ORDER BY `title` ASC
                 LIMIT " . (int) $limitPerGroup,
                PDOParams::positional([$like, $like, $like, $like])
            );
            $items = array_map(fn($r) => [
                'title'    => Row::str($r, 'title'),
                'subtitle' => Row::str($r, 'department') ?: (Row::str($r, 'city') ?: 'Vacancy'),
                'meta'     => Row::str($r, 'code'),
                'url'      => '/vacancies/' . Row::str($r, 'code'),
            ], $rows);
            if (!empty($items)) {
                $groups[] = ['key' => 'vacancies', 'label' => 'Vacancies', 'items' => $items];
                $total += count($items);
            }
        }

        return ['term' => $term, 'total' => $total, 'groups' => $groups];
    }
}

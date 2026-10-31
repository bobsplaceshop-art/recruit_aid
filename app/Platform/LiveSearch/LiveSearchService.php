<?php
declare(strict_types=1);

namespace CorporateAid\Platform\LiveSearch;

use CorporateAid\Core\Auth;
use CorporateAid\Core\Database;
use CorporateAid\Core\Exceptions\HttpException;
use CorporateAid\Core\PDOParams;
use CorporateAid\Core\TenantContext;
use CorporateAid\Core\View;

/**
 * Generic tenant-aware live-search engine.
 */
final class LiveSearchService
{
    public function __construct(
        private Database $db,
        private View $view,
        private TenantContext $tenant,
        private ?Auth $auth = null
    ) {}

    /**
     * @param array{
     *   table: string,
     *   alias: string,
     *   columns: string[],
     *   order: string,
     *   permission: string,
     *   baseFilters: string[],
     *   baseFilterParams?: array<int, mixed>,
     *   joins: string[],
     *   rowView: string,
     *   extraColumns: string[],
     *   aggregateColumns?: string[],
     *   groupBy?: string,
     *   filterable?: array<string, string>
     * } $config
     *
     * @param array<string, string> $extraFilters
     */
    public function run(
        array $config,
        string $term,
        int $page = 1,
        int $perPage = 25,
        array $extraFilters = []
    ): array {
        $user = $this->auth?->user();

        if ($user === null || !$user->can($config['permission'])) {
            throw HttpException::forbidden();
        }

        $term = trim($term);
        $alias = $config['alias'];
        $table = $config['table'];
        $joins = $config['joins'];

        // Enforce tenant isolation centrally for every root entity.
        $where = [
            "{$alias}.`tenant_id` = ?",
            ...$config['baseFilters'],
        ];

        $params = [
            $this->tenant->id(),
            ...($config['baseFilterParams'] ?? []),
        ];

        // Apply only explicitly declared filters.
        $filterable = $config['filterable'] ?? [];

        foreach ($extraFilters as $key => $value) {
            if (isset($filterable[$key]) && $value !== '') {
                $where[] = $filterable[$key];
                $params[] = $value;
            }
        }

        if ($term !== '') {
            $like = '%' . strtolower($term) . '%';
            $clauses = [];

            foreach ($config['columns'] as $column) {
                $clauses[] = 'LOWER(' . $column . ') LIKE ?';
                $params[] = $like;
            }

            $where[] = '(' . implode(' OR ', $clauses) . ')';
        }

        $whereSql = 'WHERE ' . implode(' AND ', $where);
        $joinsSql = $joins ? implode(' ', $joins) : '';
        $paramObj = PDOParams::positional($params);

        // DISTINCT prevents joined or grouped rows inflating the result count.
        $total = $this->db->scalarInt(
            "SELECT COUNT(DISTINCT {$alias}.id)
             FROM `{$table}` {$alias} {$joinsSql} {$whereSql}",
            $paramObj
        );

        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $totalPages = max(1, (int) ceil($total / $perPage));

        if ($page > $totalPages) {
            $page = $totalPages;
        }

        $offset = ($page - 1) * $perPage;

        $selectCols = $alias . '.*';

        if (!empty($config['extraColumns'])) {
            $selectCols .= ', ' . implode(', ', $config['extraColumns']);
        }

        if (!empty($config['aggregateColumns'])) {
            $selectCols .= ', ' . implode(', ', $config['aggregateColumns']);
        }

        $groupBySql = !empty($config['groupBy'])
            ? 'GROUP BY ' . $config['groupBy']
            : '';

        $rows = $this->db->select(
            "SELECT {$selectCols}
             FROM `{$table}` {$alias} {$joinsSql} {$whereSql}
             {$groupBySql}
             ORDER BY {$config['order']}
             LIMIT {$perPage} OFFSET {$offset}",
            $paramObj
        );

        $rendered = [];

        foreach ($rows as $row) {
            $rendered[] = [
                'html' => $this->view->render(
                    $config['rowView'],
                    ['row' => $row]
                ),
            ];
        }

        return [
            'rows'        => $rendered,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => $totalPages,
        ];
    }
}
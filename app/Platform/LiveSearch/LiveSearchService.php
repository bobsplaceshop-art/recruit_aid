<?php
declare(strict_types=1);

namespace CorporateAid\Platform\LiveSearch;

use CorporateAid\Core\Auth;
use CorporateAid\Core\Database;
use CorporateAid\Core\Exceptions\HttpException;
use CorporateAid\Core\PDOParams;
use CorporateAid\Core\View;

/**
 * Generic live-search engine. Builds a LIKE query from an entity config,
 * paginates, and renders each row via a per-entity view partial so the
 * frontend gets fully rendered HTML alongside metadata.
 */
final class LiveSearchService
{
    public function __construct(
        private Database $db,
        private View $view,
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
     *   joins: string[],
     *   rowView: string,
     *   extraColumns: string[],
     *   aggregateColumns?: string[],
     *   groupBy?: string,
     *   filterable?: array<string, string>
     * } $config
     * @param array<string, string> $extraFilters
     * @return array{rows: array<int, array{html: string}>, total: int, page: int, per_page: int, total_pages: int}
     */
    public function run(array $config, string $term, int $page = 1, int $perPage = 25, array $extraFilters = []): array
    {
        // Permission gate
        $user = $this->auth?->user();
        if ($user === null || !$user->can($config['permission'])) {
            throw HttpException::forbidden();
        }

        $term = trim($term);
        $alias = $config['alias'];
        $table = $config['table'];
        $joins = $config['joins'];
        $where = $config['baseFilters'];
        $params = [];

        // Apply per-entity extra filters if declared in config['filterable']
        $filterable = $config['filterable'] ?? [];
        foreach ($extraFilters as $key => $value) {
            if (isset($filterable[$key]) && $value !== '') {
                $where[] = $filterable[$key];  // e.g. "o.`status` = ?"
                $params[] = $value;
            }
        }

        if ($term !== '') {
            $like = '%' . strtolower($term) . '%';
            $clauses = [];
            foreach ($config['columns'] as $col) {
                $clauses[] = 'LOWER(' . $col . ') LIKE ?';
                $params[] = $like;
            }
            $where[] = '(' . implode(' OR ', $clauses) . ')';
        }

        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
        $joinsSql = $joins ? implode(' ', $joins) : '';

        $paramObj = PDOParams::positional($params);

        $total = $this->db->scalarInt(
            "SELECT COUNT(*) FROM `{$table}` {$alias} {$joinsSql} {$whereSql}",
            $paramObj
        );

        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $totalPages = max(1, (int) ceil($total / $perPage));
        if ($page > $totalPages) $page = $totalPages;
        $offset = ($page - 1) * $perPage;

        $selectCols = $alias . '.*';
        if (!empty($config['extraColumns'])) {
            $selectCols .= ', ' . implode(', ', $config['extraColumns']);
        }

        $rows = $this->db->select(
            "SELECT {$selectCols}
             FROM `{$table}` {$alias} {$joinsSql} {$whereSql}
             ORDER BY {$config['order']}
             LIMIT {$perPage} OFFSET {$offset}",
            $paramObj
        );

        // Render each row via the entity's partial
        $rendered = [];
        foreach ($rows as $row) {
            $rendered[] = [
                'html' => $this->view->render($config['rowView'], ['row' => $row]),
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

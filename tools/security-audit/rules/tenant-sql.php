<?php

declare(strict_types=1);

/**
 * TENANT-002: Identify SQL references to tenant-scoped tables
 * without a corresponding tenant ownership condition.
 *
 * This is a static heuristic. It does not prove whether ownership
 * was validated earlier in the calling code.
 */

return static function (
    array $tokens,
    string $relativePath,
    array $classification
): array {
    $findings = [];
    $tenantTables = $classification['tenant_scoped'] ?? [];

    $ignoredAliases = [
        'where', 'join', 'left', 'right', 'inner', 'outer',
        'cross', 'on', 'using', 'set', 'values', 'order',
        'group', 'limit', 'offset', 'having', 'union',
        'for', 'lock',
    ];

    foreach ($tokens as $token) {
        if (!is_array($token) || $token[0] !== T_CONSTANT_ENCAPSED_STRING) {
            continue;
        }

        $literal = $token[1];
        $sql = stripcslashes(substr($literal, 1, -1));

        if (!preg_match('/\b(SELECT|INSERT|UPDATE|DELETE|REPLACE)\b/i', $sql)) {
            continue;
        }

        $tables = [];

        foreach ($tenantTables as $table) {
            $pattern =
                '/\b(?:FROM|JOIN|UPDATE|INTO|DELETE\s+FROM)\s+`?'
                . preg_quote($table, '/')
                . '`?(?:\s+(?:AS\s+)?([a-z_][a-z0-9_]*))?/i';

            if (!preg_match_all($pattern, $sql, $matches, PREG_SET_ORDER)) {
                continue;
            }

            foreach ($matches as $match) {
                $alias = strtolower($match[1] ?? '');

                if ($alias === '' || in_array($alias, $ignoredAliases, true)) {
                    $alias = null;
                }

                $tables[] = [
                    'name' => $table,
                    'alias' => $alias,
                ];
            }
        }

        foreach ($tables as $entry) {
            $table = $entry['name'];
            $alias = $entry['alias'];

            $isInsert = preg_match(
                '/\b(?:INSERT|REPLACE)\s+INTO\s+`?'
                . preg_quote($table, '/')
                . '`?\s*\(([^)]*)\)/i',
                $sql,
                $insertMatch
            ) === 1;

            if ($isInsert) {
                $hasTenantColumn = preg_match(
                    '/(?:^|,)\s*`?tenant_id`?\s*(?:,|$)/i',
                    $insertMatch[1]
                ) === 1;

                if (!$hasTenantColumn) {
                    $findings[] = [
                        'rule' => 'TENANT-002',
                        'severity' => 'REVIEW',
                        'file' => $relativePath,
                        'line' => $token[2],
                        'evidence' => "INSERT into {$table} does not explicitly provide tenant_id",
                    ];
                }

                continue;
            }

            if ($alias !== null) {
                $column = preg_quote($alias, '/') . '\s*\.\s*`?tenant_id`?';
            } else {
                // Unqualified tenant_id is accepted only when the SQL
                // references exactly one classified tenant-scoped table.
                if (count($tables) !== 1) {
                    $findings[] = [
                        'rule' => 'TENANT-002',
                        'severity' => 'REVIEW',
                        'file' => $relativePath,
                        'line' => $token[2],
                        'evidence' => "Cannot reliably establish tenant predicate for {$table}",
                    ];
                    continue;
                }

                $column = '`?tenant_id`?';
            }

            $predicatePattern =
                '/\b(?:WHERE|AND|ON)\b.{0,1500}?(?:'
                . $column
                . '\s*(?:=|<>|!=|IN\s*\(|IS\s+(?:NOT\s+)?NULL)'
                . '|(?:\?|:[a-z_][a-z0-9_]*)\s*=\s*'
                . $column
                . ')/is';

            if (preg_match($predicatePattern, $sql) === 1) {
                continue;
            }

            $findings[] = [
                'rule' => 'TENANT-002',
                'severity' => 'REVIEW',
                'file' => $relativePath,
                'line' => $token[2],
                'evidence' => "SQL references {$table}"
                    . ($alias !== null ? " as {$alias}" : '')
                    . ' without an obvious tenant ownership condition',
            ];
        }
    }

    return $findings;
};

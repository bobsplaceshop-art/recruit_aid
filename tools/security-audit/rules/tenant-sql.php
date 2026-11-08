<?php

declare(strict_types=1);

/**
 * TENANT-002: Identify SQL references to tenant-scoped tables
 * without an obvious tenant ownership condition.
 *
 * SQL is divided into independent query scopes. Nested queries and
 * UNION branches are analysed separately so predicates cannot leak
 * between scopes.
 *
 * This remains a static heuristic. It cannot prove whether ownership
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
        'for', 'lock', 'use', 'force', 'index',
    ];

    /**
     * Split SQL at a delimiter occurring outside quoted strings
     * and parentheses.
     */
    $splitTopLevel = static function (string $sql, string $delimiter): array {
        $parts = [];
        $start = 0;
        $depth = 0;
        $quote = null;
        $length = strlen($sql);
        $delimiterLength = strlen($delimiter);

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];

            if ($quote !== null) {
                if ($char === '\\') {
                    $i++;
                    continue;
                }

                if ($char === $quote) {
                    if (($sql[$i + 1] ?? '') === $quote) {
                        $i++;
                        continue;
                    }

                    $quote = null;
                }

                continue;
            }

            if ($char === "'" || $char === '"' || $char === '`') {
                $quote = $char;
                continue;
            }

            if ($char === '(') {
                $depth++;
                continue;
            }

            if ($char === ')') {
                $depth = max(0, $depth - 1);
                continue;
            }

            if (
                $depth === 0
                && strcasecmp(substr($sql, $i, $delimiterLength), $delimiter) === 0
            ) {
                $before = $i > 0 ? $sql[$i - 1] : ' ';
                $after = $sql[$i + $delimiterLength] ?? ' ';

                if (
                    !preg_match('/[a-z0-9_]/i', $before)
                    && !preg_match('/[a-z0-9_]/i', $after)
                ) {
                    $parts[] = substr($sql, $start, $i - $start);
                    $i += $delimiterLength - 1;
                    $start = $i + 1;
                }
            }
        }

        $parts[] = substr($sql, $start);

        return $parts;
    };

    /**
     * Replace parenthesized expressions with spaces while returning
     * their contents for independent nested-query analysis.
     */
    $extractNested = static function (string $sql): array {
        $masked = '';
        $nested = [];
        $stack = [];
        $quote = null;
        $length = strlen($sql);

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];

            if ($quote !== null) {
                if ($char === '\\') {
                    $masked .= '  ';
                    $i++;
                    continue;
                }

                if ($char === $quote) {
                    if (($sql[$i + 1] ?? '') === $quote) {
                        $masked .= '  ';
                        $i++;
                        continue;
                    }

                    $quote = null;
                }

                $masked .= ' ';
                continue;
            }

            if ($char === "'" || $char === '"') {
                $quote = $char;
                $masked .= ' ';
                continue;
            }

            if ($char === '(') {
                $stack[] = [
                    'start' => $i,
                    'content' => '',
                ];
                $masked .= ' ';
                continue;
            }

            if ($char === ')') {
                if ($stack !== []) {
                    $entry = array_pop($stack);

                    if ($stack === []) {
                        $nested[] = $entry['content'];
                    } else {
                        $stack[count($stack) - 1]['content'] .=
                            '(' . $entry['content'] . ')';
                    }
                }

                $masked .= ' ';
                continue;
            }

            if ($stack !== []) {
                $stack[count($stack) - 1]['content'] .= $char;
            } else {
                $masked .= $char;
            }
        }

        // Unbalanced parentheses make the scope uncertain.
        if ($stack !== []) {
            return [$sql, [], false];
        }

        return [$masked, $nested, true];
    };

    $addFinding = static function (
        string $evidence,
        int $line
    ) use (&$findings, $relativePath): void {
        $findings[] = [
            'rule' => 'TENANT-002',
            'severity' => 'REVIEW',
            'file' => $relativePath,
            'line' => $line,
            'evidence' => $evidence,
        ];
    };

    /**
     * Analyse a single SQL query scope.
     */
    $analyseScope = null;

    $analyseScope = static function (
        string $sql,
        int $line,
        int $depth = 0
    ) use (
        &$analyseScope,
        $splitTopLevel,
        $extractNested,
        $tenantTables,
        $ignoredAliases,
        $addFinding
    ): void {
        if ($depth > 32 || trim($sql) === '') {
            return;
        }

        // UNION branches are independent query scopes.
        $branches = $splitTopLevel($sql, 'UNION');

        if (count($branches) > 1) {
            foreach ($branches as $branch) {
                $analyseScope($branch, $line, $depth + 1);
            }

            return;
        }

        [$scopeSql, $nestedQueries, $balanced] = $extractNested($sql);

        if (!$balanced) {
            $addFinding(
                'SQL contains unbalanced parentheses; tenant scope cannot be reliably analysed',
                $line
            );
            return;
        }

        // Analyse nested SELECTs independently.
        foreach ($nestedQueries as $nestedQuery) {
            if (preg_match('/\b(SELECT|INSERT|UPDATE|DELETE|REPLACE)\b/i', $nestedQuery)) {
                $analyseScope($nestedQuery, $line, $depth + 1);
            }
        }

        if (!preg_match('/\b(SELECT|INSERT|UPDATE|DELETE|REPLACE)\b/i', $scopeSql)) {
            return;
        }

        $references = [];

        foreach ($tenantTables as $table) {
            $pattern =
                '/\b(?:FROM|JOIN|UPDATE|INTO|DELETE\s+FROM)\s+`?'
                . preg_quote($table, '/')
                . '`?(?:\s+(?:AS\s+)?([a-z_][a-z0-9_]*))?/i';

            if (!preg_match_all($pattern, $scopeSql, $matches, PREG_SET_ORDER)) {
                continue;
            }

            foreach ($matches as $match) {
                $alias = strtolower($match[1] ?? '');

                if ($alias === '' || in_array($alias, $ignoredAliases, true)) {
                    $alias = null;
                }

                $references[] = [
                    'name' => $table,
                    'alias' => $alias,
                ];
            }
        }

        foreach ($references as $entry) {
            $table = $entry['name'];
            $alias = $entry['alias'];

            $insertPattern =
                '/\b(?:INSERT|REPLACE)\s+INTO\s+`?'
                . preg_quote($table, '/')
                . '`?\s*\(([^)]*)\)/i';

            if (preg_match($insertPattern, $sql, $insertMatch) === 1) {
                $hasTenantColumn = preg_match(
                    '/(?:^|,)\s*`?tenant_id`?\s*(?:,|$)/i',
                    $insertMatch[1]
                ) === 1;

                if (!$hasTenantColumn) {
                    $addFinding(
                        "INSERT into {$table} does not explicitly provide tenant_id",
                        $line
                    );
                }

                continue;
            }

            if ($alias !== null) {
                $column = preg_quote($alias, '/') . '\s*\.\s*`?tenant_id`?';
            } else {
                if (count($references) !== 1) {
                    $addFinding(
                        "Cannot reliably establish tenant predicate for {$table} in this query scope",
                        $line
                    );
                    continue;
                }

                $column = '(?:[a-z_][a-z0-9_]*\s*\.\s*)?`?tenant_id`?';
            }

            $predicatePattern =
                '/\b(?:WHERE|AND|ON)\b.{0,1500}?(?:'
                . $column
                . '\s*(?:=|<>|!=|IN\s*\(|IS\s+(?:NOT\s+)?NULL)'
                . '|(?:\?|:[a-z_][a-z0-9_]*)\s*=\s*'
                . $column
                . ')/is';

            if (preg_match($predicatePattern, $scopeSql) === 1) {
                continue;
            }

            $addFinding(
                "SQL references {$table}"
                . ($alias !== null ? " as {$alias}" : '')
                . ' without an obvious tenant ownership condition in its query scope',
                $line
            );
        }
    };

    foreach ($tokens as $token) {
        if (!is_array($token) || $token[0] !== T_CONSTANT_ENCAPSED_STRING) {
            continue;
        }

        $literal = $token[1];
        $sql = stripcslashes(substr($literal, 1, -1));

        if (!preg_match('/\b(SELECT|INSERT|UPDATE|DELETE|REPLACE)\b/i', $sql)) {
            continue;
        }

        $analyseScope($sql, (int) ($token[2] ?? 0));
    }

    return $findings;
};

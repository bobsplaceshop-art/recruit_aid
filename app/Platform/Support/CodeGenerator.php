<?php
declare(strict_types=1);

namespace CorporateAid\Platform\Support;

use CorporateAid\Core\Database;

/**
 * Generates human-friendly identifier codes.
 * Format: PREFIX-000000 (six-digit, zero-padded).
 * The DB unique constraint on the code column guarantees safety.
 */
final class CodeGenerator
{
    public function __construct(private Database $db)
    {
    }

    /**
     * Generate the next code for the given table. E.g. next('organisations', 'ORG')
     * returns "ORG-000001" if the table is empty, "ORG-000042" if 41 rows exist.
     *
     * Note: this is NOT transactionally safe under heavy concurrency. For a
     * recruitment tool with low write volume, it's fine. If we ever need high
     * concurrency, switch to a dedicated sequences table with SELECT ... FOR UPDATE.
     */
    public function next(string $table, string $prefix, int $padding = 6): string
    {
        $sql = "SELECT `code` FROM `{$table}`
                WHERE `code` LIKE ?
                ORDER BY `id` DESC
                LIMIT 1";
        $like = $prefix . '-%';

        $last = $this->db->scalar($sql, [$like]);

        if ($last === null || $last === false) {
            $num = 1;
        } else {
            // Extract the numeric part after the last dash
            $parts = explode('-', (string) $last);
            $num = (int) end($parts) + 1;
        }

        return $prefix . '-' . str_pad((string) $num, $padding, '0', STR_PAD_LEFT);
    }
}

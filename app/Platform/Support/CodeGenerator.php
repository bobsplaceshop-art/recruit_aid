<?php
declare(strict_types=1);

namespace CorporateAid\Platform\Support;

use CorporateAid\Core\Database;
use CorporateAid\Core\PDOParams;
use InvalidArgumentException;
use RuntimeException;

final class CodeGenerator
{
    public function __construct(private Database $db)
    {
    }

    /**
     * Legacy/global code generation retained for compatibility.
     */
    public function next(string $table, string $prefix, int $padding = 6): string
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
            throw new InvalidArgumentException('Invalid code table name.');
        }

        $sql = "SELECT `code` FROM `{$table}`
                WHERE `code` LIKE ?
                ORDER BY `id` DESC
                LIMIT 1";

        $last = $this->db->scalarString(
            $sql,
            PDOParams::positional([$prefix . '-%'])
        );

        $num = $last === ''
            ? 1
            : ((int) substr(strrchr($last, '-') ?: '', 1) + 1);

        return $prefix . '-' . str_pad((string) $num, $padding, '0', STR_PAD_LEFT);
    }

    /**
     * Atomically allocate the next code from a tenant-specific sequence.
     * The counter row must already exist.
     */
    public function nextForTenant(
        string $counterName,
        string $prefix,
        int $tenantId,
        int $padding = 6
    ): string {
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $counterName)) {
            throw new InvalidArgumentException('Invalid sequence counter name.');
        }

        if (!preg_match('/^[A-Z][A-Z0-9]*$/', $prefix)) {
            throw new InvalidArgumentException('Invalid code prefix.');
        }

        if ($tenantId < 1) {
            throw new InvalidArgumentException('Tenant ID must be positive.');
        }

        if ($padding < 1 || $padding > 18) {
            throw new InvalidArgumentException('Code padding must be between 1 and 18.');
        }

        $tenantCounter = $counterName . '_t' . $tenantId;

        if (strlen($tenantCounter) > 60) {
            throw new InvalidArgumentException('Tenant sequence counter name exceeds 60 characters.');
        }

        $pdo = $this->db->pdo();

        $statement = $pdo->prepare(
            'UPDATE `core_sequence_counters`
             SET `last_value` = LAST_INSERT_ID(`last_value` + 1)
             WHERE `name` = ?'
        );

        $statement->execute([$tenantCounter]);

        if ($statement->rowCount() !== 1) {
            throw new RuntimeException(
                sprintf('Sequence counter "%s" is missing or could not be incremented.', $tenantCounter)
            );
        }

        $nextValue = (int) $pdo->query('SELECT LAST_INSERT_ID()')->fetchColumn();

        if ($nextValue < 1) {
            throw new RuntimeException(
                sprintf('Sequence counter "%s" returned an invalid value.', $tenantCounter)
            );
        }

        return $prefix . '-' . str_pad((string) $nextValue, $padding, '0', STR_PAD_LEFT);
    }
}

<?php
declare(strict_types=1);

namespace CorporateAid\Core;

use PDO;
use RuntimeException;

/**
 * Applies .sql migration files in numeric order.
 * Tracks what has been run in a `core_migrations` table.
 */
final class Migrator
{
    private PDO $pdo;
    private string $migrationsDir;

    public function __construct(PDO $pdo, string $migrationsDir)
    {
        $this->pdo = $pdo;
        $this->migrationsDir = rtrim($migrationsDir, '/\\');
    }

    public function ensureMigrationsTable(): void
    {
        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS `core_migrations` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `filename` VARCHAR(255) NOT NULL,
                `batch` INT UNSIGNED NOT NULL DEFAULT 1,
                `applied_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_filename` (`filename`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    /** @return array<string, true> */
    public function applied(): array
    {
        $stmt = $this->pdo->query("SELECT `filename` FROM `core_migrations`");
        if ($stmt === false) {
            throw new RuntimeException('Failed to query applied migrations.');
        }
        $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $out = [];
        foreach ($rows as $f) {
            $out[(string) $f] = true;
        }
        return $out;
    }

    /** @return string[] */
    public function available(): array
    {
        if (!is_dir($this->migrationsDir)) {
            throw new RuntimeException("Migrations directory not found: {$this->migrationsDir}");
        }
        $files = glob($this->migrationsDir . '/*.sql') ?: [];
        $names = array_map('basename', $files);
        sort($names, SORT_NATURAL | SORT_FLAG_CASE);
        return $names;
    }

    /** @return string[] */
    public function run(): array
    {
        $this->ensureMigrationsTable();
        $applied = $this->applied();
        $all = $this->available();

        $pending = array_values(array_filter($all, fn (string $f) => !isset($applied[$f])));

        if (empty($pending)) {
            return [];
        }

        $stmt = $this->pdo->query("SELECT COALESCE(MAX(`batch`), 0) + 1 FROM `core_migrations`");
        if ($stmt === false) {
            throw new RuntimeException('Failed to compute migration batch number.');
        }
        $batch = (int) $stmt->fetchColumn();
        $ran = [];

        foreach ($pending as $filename) {
            $path = $this->migrationsDir . '/' . $filename;
            $sql = file_get_contents($path);
            if ($sql === false) {
                throw new RuntimeException("Unable to read migration: {$filename}");
            }

            // 1. Strip comment-only lines first.
            // 2. Split into statements.
            // This avoids the bug where a leading comment swallows the following SQL.
            $cleanSql = $this->stripComments($sql);
            $statements = $this->splitStatements($cleanSql);

            try {
                foreach ($statements as $stmt) {
                    $trimmed = trim($stmt);
                    if ($trimmed === '') {
                        continue;
                    }
                    // Use query() + closeCursor() instead of exec()
                    // to fully drain any result set (needed when the SQL
                    // contains PREPARE/EXECUTE/SELECT-style statements).
                    $result = $this->pdo->query($trimmed);
                    if ($result instanceof \PDOStatement) {
                        $result->closeCursor();
                    }
                }

                $ins = $this->pdo->prepare("INSERT INTO `core_migrations` (`filename`, `batch`) VALUES (?, ?)");
                $ins->execute([$filename, $batch]);

                $ran[] = $filename;
            } catch (\Throwable $e) {
                throw new RuntimeException("Migration failed: {$filename} — " . $e->getMessage(), 0, $e);
            }
        }

        return $ran;
    }

    /**
     * Remove `-- line comments` and `/* block comments *\/`.
     * Does NOT touch comment markers inside quoted strings.
     */
    private function stripComments(string $sql): string
    {
        $out = '';
        $len = strlen($sql);
        $inString = false;
        $stringChar = '';

        for ($i = 0; $i < $len; $i++) {
            $c = $sql[$i];

            if ($inString) {
                if ($c === '\\') {
                    $out .= $c;
                    if ($i + 1 < $len) {
                        $out .= $sql[++$i];
                    }
                    continue;
                }
                if ($c === $stringChar) {
                    $inString = false;
                }
                $out .= $c;
                continue;
            }

            if ($c === "'" || $c === '"') {
                $inString = true;
                $stringChar = $c;
                $out .= $c;
                continue;
            }

            // Handle -- line comments
            if ($c === '-' && ($i + 1 < $len) && $sql[$i + 1] === '-') {
                while ($i < $len && $sql[$i] !== "\n") {
                    $i++;
                }
                $out .= "\n";
                continue;
            }

            // Handle /* block comments */
            if ($c === '/' && ($i + 1 < $len) && $sql[$i + 1] === '*') {
                $i += 2;
                while ($i + 1 < $len && !($sql[$i] === '*' && $sql[$i + 1] === '/')) {
                    $i++;
                }
                $i++; // skip the closing /
                continue;
            }

            $out .= $c;
        }

        return $out;
    }

    /**
     * Split into statements on semicolons not inside strings.
     *
     * @return string[]
     */
    private function splitStatements(string $sql): array
    {
        $statements = [];
        $buffer = '';
        $inString = false;
        $stringChar = '';
        $len = strlen($sql);

        for ($i = 0; $i < $len; $i++) {
            $c = $sql[$i];

            if ($inString) {
                if ($c === '\\') {
                    $buffer .= $c;
                    if ($i + 1 < $len) {
                        $buffer .= $sql[++$i];
                    }
                    continue;
                }
                if ($c === $stringChar) {
                    $inString = false;
                }
                $buffer .= $c;
                continue;
            }

            if ($c === "'" || $c === '"') {
                $inString = true;
                $stringChar = $c;
                $buffer .= $c;
                continue;
            }

            if ($c === ';') {
                $statements[] = $buffer;
                $buffer = '';
                continue;
            }

            $buffer .= $c;
        }

        if (trim($buffer) !== '') {
            $statements[] = $buffer;
        }

        return $statements;
    }
}

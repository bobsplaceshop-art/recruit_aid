<?php
declare(strict_types=1);

namespace CorporateAid\Core;

use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;

/**
 * Thin wrapper over PDO. Every query goes through prepared statements.
 *
 * Configuration is a DatabaseConfig value object — no raw arrays.
 * Query parameters are PDOParams value objects — the caller declares
 * positional vs named at the call site, and PHPStan can verify it.
 */
final class Database
{
    private ?PDO $pdo = null;

    public function __construct(private readonly DatabaseConfig $config)
    {
    }

    public function pdo(): PDO
    {
        if ($this->pdo instanceof PDO) {
            return $this->pdo;
        }

        try {
            $this->pdo = new PDO(
                $this->config->dsn(),
                $this->config->username,
                $this->config->password,
                $this->config->pdoOptions
            );
        } catch (PDOException $e) {
            throw new RuntimeException('Database connection failed. Check config/local.php.', 0, $e);
        }

        return $this->pdo;
    }

    public function query(string $sql, ?PDOParams $params = null): PDOStatement
    {
        $params ??= PDOParams::empty();
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($params->toArray());
        return $stmt;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function select(string $sql, ?PDOParams $params = null): array
    {
        $rows = $this->query($sql, $params)->fetchAll();
        /** @var array<int, array<string, mixed>> $rows */
        return $rows;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function selectOne(string $sql, ?PDOParams $params = null): ?array
    {
        $row = $this->query($sql, $params)->fetch();
        if ($row === false) {
            return null;
        }
        /** @var array<string, mixed> $row */
        return $row;
    }

    public function scalar(string $sql, ?PDOParams $params = null): mixed
    {
        $value = $this->query($sql, $params)->fetchColumn();
        return $value === false ? null : $value;
    }

    public function scalarInt(string $sql, ?PDOParams $params = null): int
    {
        $value = $this->scalar($sql, $params);
        return is_numeric($value) ? (int) $value : 0;
    }

    public function scalarString(string $sql, ?PDOParams $params = null): string
    {
        $value = $this->scalar($sql, $params);
        return is_scalar($value) ? (string) $value : '';
    }

    public function execute(string $sql, ?PDOParams $params = null): int
    {
        return $this->query($sql, $params)->rowCount();
    }

    public function insert(string $sql, ?PDOParams $params = null): int
    {
        $this->query($sql, $params);
        return (int) $this->pdo()->lastInsertId();
    }

    public function beginTransaction(): void { $this->pdo()->beginTransaction(); }
    public function commit(): void           { $this->pdo()->commit(); }
    public function rollBack(): void         { if ($this->pdo()->inTransaction()) $this->pdo()->rollBack(); }

    public function testConnection(): bool
    {
        try {
            $this->pdo()->query('SELECT 1');
            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}

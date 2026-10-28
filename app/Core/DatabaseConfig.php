<?php
declare(strict_types=1);

namespace CorporateAid\Core;

use RuntimeException;

/**
 * Value object for database configuration.
 *
 * Constructed once at boot from config/local.php. Every field is
 * typed; the constructor validates required fields and throws if
 * anything critical is missing.
 *
 * All fields are readonly — configuration is immutable after
 * construction. If a deployment needs different credentials, it
 * restarts the process with a different config.
 */
final class DatabaseConfig
{
    public readonly string $driver;
    public readonly string $host;
    public readonly int    $port;
    public readonly string $database;
    public readonly string $charset;
    public readonly string $username;
    public readonly string $password;
    /** @var array<int, mixed> */
    public readonly array  $pdoOptions;

    /**
     * @param array<string, mixed> $config Raw config array from config/local.php
     */
    public static function fromArray(array $config): self
    {
        $driver = self::str($config, 'driver', 'mysql');
        if ($driver !== 'mysql') {
            throw new RuntimeException("Unsupported DB driver: {$driver}");
        }

        $database = self::str($config, 'database');
        if ($database === '') {
            throw new RuntimeException('Database name is required in config/local.php (key: database).');
        }

        return new self(
            driver:     $driver,
            host:       self::str($config, 'host', 'localhost'),
            port:       self::int($config, 'port', 3306),
            database:   $database,
            charset:    self::str($config, 'charset', 'utf8mb4'),
            username:   self::str($config, 'username'),
            password:   self::str($config, 'password'),
            pdoOptions: self::optArr($config, 'options'),
        );
    }

    /**
     * @param array<int, mixed> $pdoOptions
     */
    private function __construct(
        string $driver,
        string $host,
        int $port,
        string $database,
        string $charset,
        string $username,
        string $password,
        array $pdoOptions
    ) {
        $this->driver = $driver;
        $this->host = $host;
        $this->port = $port;
        $this->database = $database;
        $this->charset = $charset;
        $this->username = $username;
        $this->password = $password;
        $this->pdoOptions = $pdoOptions;
    }

    public function dsn(): string
    {
        return sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $this->host,
            $this->port,
            $this->database,
            $this->charset
        );
    }

    /** @param array<string, mixed> $config */
    private static function str(array $config, string $key, string $default = ''): string
    {
        $value = $config[$key] ?? $default;
        return is_scalar($value) ? (string) $value : $default;
    }

    /** @param array<string, mixed> $config */
    private static function int(array $config, string $key, int $default): int
    {
        $value = $config[$key] ?? $default;
        return is_numeric($value) ? (int) $value : $default;
    }

    /** @param array<string, mixed> $config
     *  @return array<int, mixed> */
    private static function optArr(array $config, string $key): array
    {
        $value = $config[$key] ?? [];
        return is_array($value) ? array_values($value) : [];
    }
}

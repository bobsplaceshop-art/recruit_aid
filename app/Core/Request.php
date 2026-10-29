<?php
declare(strict_types=1);

namespace CorporateAid\Core;

use CorporateAid\Core\Row;

/**
 * Represents an incoming HTTP request. Wraps PHP superglobals
 * so the rest of the app never touches $_GET/$_POST/$_SERVER directly.
 */
final class Request
{
    private string $method;
    private string $path;
    private string $queryString;
    /** @var array<string, mixed> */
    private array $queryParams;
    /** @var array<string, mixed> */
    private array $body;
    /** @var array<string, mixed> */
    private array $files;
    /** @var array<string, mixed> */
    private array $server;
    /** @var array<string, mixed> */
    private array $headers;
    private ?string $rawBody;

    /**
     * @param array<string, mixed> $queryParams
     * @param array<string, mixed> $body
     * @param array<string, mixed> $files
     * @param array<string, mixed> $server
     * @param array<string, mixed> $cookies
     * @param array<string, mixed> $headers
     */
    /**
     * @param array<string, mixed> $queryParams
     * @param array<string, mixed> $body
     * @param array<string, mixed> $files
     * @param array<string, mixed> $server
     * @param array<string, mixed> $cookies
     * @param array<string, mixed> $headers
     */
    public function __construct(
        string $method,
        string $path,
        string $queryString,
        array $queryParams,
        array $body,
        array $files,
        array $server,
        array $cookies,
        array $headers,
        ?string $rawBody = null
    ) {
        unset($cookies); // acknowledge intentional unused param
        $this->method = strtoupper($method);
        $this->path = $path;
        $this->queryString = $queryString;
        $this->queryParams = $queryParams;
        $this->body = $body;
        $this->files = $files;
        $this->server = $server;
        $this->headers = $headers;
        $this->rawBody = $rawBody;
    }

    public static function capture(): self
    {
        $methodRaw = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $method = is_scalar($methodRaw) ? (string) $methodRaw : 'GET';

        $uriRaw = $_SERVER['REQUEST_URI'] ?? '/';
        $uri = is_scalar($uriRaw) ? (string) $uriRaw : '/';

        $parts  = parse_url($uri);

        $path  = $parts['path']  ?? '/';
        $query = $parts['query'] ?? '';

        if ($method === 'POST' && isset($_POST['_method'])) {
            $methodValue = $_POST['_method'];
            if (is_scalar($methodValue)) {
                $method = strtoupper((string) $methodValue);
            }
        }

        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }

        return new self(
            $method,
            $path,
            $query,
            $_GET,
            $_POST,
            $_FILES,
            $_SERVER,
            $_COOKIE,
            self::extractHeaders($_SERVER),
            file_get_contents('php://input') ?: null
        );
    }

    /**
     * @param array<string, mixed> $server
     * @return array<string, mixed>
     */
    private static function extractHeaders(array $server): array
    {
        $headers = [];
        foreach ($server as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($key, 5)))));
                $headers[$name] = $value;
            }
        }
        return $headers;
    }

    public function method(): string { return $this->method; }
    public function path(): string   { return $this->path; }
    public function queryString(): string { return $this->queryString; }
    public function isMethod(string $method): bool { return $this->method === strtoupper($method); }
    public function isGet(): bool    { return $this->isMethod('GET'); }
    public function isPost(): bool   { return $this->isMethod('POST'); }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->queryParams[$key] ?? $default;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->body)) {
            return $this->body[$key];
        }

        if ($this->rawBody !== null && $this->isJson()) {
            $decoded = json_decode($this->rawBody, true);
            if (is_array($decoded) && array_key_exists($key, $decoded)) {
                return $decoded[$key];
            }
        }

        return $default;
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        $merged = $this->queryParams;
        foreach ($this->body as $k => $v) {
            $merged[$k] = $v;
        }
        return $merged;
    }

    /**
     * Return a new Request with the given body overrides merged in.
     * Used by middleware that injects data for downstream handlers.
     *
     * @param array<string, mixed> $overrides
     */
    public function withBody(array $overrides): self
    {
        $newBody = $this->body;
        foreach ($overrides as $k => $v) {
            $newBody[$k] = $v;
        }
        return new self(
            $this->method,
            $this->path,
            $this->queryString,
            $this->queryParams,
            $newBody,
            $this->files,
            $this->server,
            [],
            $this->headers,
            $this->rawBody
        );
    }

    /**
     * @param string[] $keys
     * @return array<string, mixed>
     */
    public function only(array $keys): array
    {
        $all = $this->all();
        return array_intersect_key($all, array_flip($keys));
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->body) || array_key_exists($key, $this->queryParams);
    }

    /**
     * @return array{name:string,type:string,tmp_name:string,error:int,size:int}|null
     */
    public function file(string $key): ?array
    {
        $file = $this->files[$key] ?? null;
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        /** @var array{name:string,type:string,tmp_name:string,error:int,size:int} $file */
        return $file;
    }

    public function header(string $name, mixed $default = null): mixed
    {
        return $this->headers[$name] ?? $default;
    }

    public function ip(): string
    {
        return Row::str($this->server, 'REMOTE_ADDR', '0.0.0.0');
    }

    public function userAgent(): string
    {
        return Row::str($this->server, 'HTTP_USER_AGENT');
    }

    public function isJson(): bool
    {
        $ct = Row::str($this->headers, 'Content-Type');
        return str_contains(strtolower($ct), 'application/json');
    }

    public function wantsJson(): bool
    {
        $accept = Row::str($this->headers, 'Accept');
        return str_contains($accept, 'application/json') || str_starts_with($this->path, '/api');
    }

    public function rawBody(): ?string
    {
        return $this->rawBody;
    }

    public function inputString(string $key, string $default = ''): string
    {
        $value = $this->input($key, $default);
        return is_scalar($value) ? (string) $value : $default;
    }

    public function inputInt(string $key, int $default = 0): int
    {
        $value = $this->input($key, $default);
        return is_numeric($value) ? (int) $value : $default;
    }

    public function inputBool(string $key, bool $default = false): bool
    {
        $value = $this->input($key);
        if ($value === null) return $default;
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /** @return array<string, mixed> */
    public function inputArray(string $key): array
    {
        $value = $this->input($key, []);
        return is_array($value) ? $value : [];
    }

    public function queryStringValue(string $key, string $default = ''): string
    {
        $value = $this->query($key, $default);
        return is_scalar($value) ? (string) $value : $default;
    }

    public function queryInt(string $key, int $default = 0): int
    {
        $value = $this->query($key, $default);
        return is_numeric($value) ? (int) $value : $default;
    }
}

<?php
declare(strict_types=1);

namespace CorporateAid\Core;

/**
 * Represents an incoming HTTP request. Wraps PHP superglobals
 * so the rest of the app never touches $_GET/$_POST/$_SERVER directly.
 */
final class Request
{
    private string $method;
    private string $path;
    private string $queryString;
    private array $queryParams;
    private array $body;
    private array $files;
    private array $server;
    private array $cookies;
    private array $headers;
    private ?string $rawBody;

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
        $this->method = strtoupper($method);
        $this->path = $path;
        $this->queryString = $queryString;
        $this->queryParams = $queryParams;
        $this->body = $body;
        $this->files = $files;
        $this->server = $server;
        $this->cookies = $cookies;
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

    public function all(): array
    {
        $merged = $this->queryParams;
        foreach ($this->body as $k => $v) {
            $merged[$k] = $v;
        }
        return $merged;
    }

    public function only(array $keys): array
    {
        $all = $this->all();
        return array_intersect_key($all, array_flip($keys));
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->body) || array_key_exists($key, $this->queryParams);
    }

    public function file(string $key): ?array
    {
        $file = $this->files[$key] ?? null;
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        return $file;
    }

    public function header(string $name, mixed $default = null): mixed
    {
        return $this->headers[$name] ?? $default;
    }

    public function ip(): string
    {
        return (string) ($this->server['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    public function userAgent(): string
    {
        return (string) ($this->server['HTTP_USER_AGENT'] ?? '');
    }

    public function isJson(): bool
    {
        $ct = (string) ($this->headers['Content-Type'] ?? '');
        return str_contains(strtolower($ct), 'application/json');
    }

    public function wantsJson(): bool
    {
        $accept = (string) ($this->headers['Accept'] ?? '');
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

    /**
     * @return array<string, mixed>
     */
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

<?php

declare(strict_types=1);

namespace CorporateAid\Core;

/**
 * Config store. Loads from arrays, reads with dot notation.
 */
final class Config
{
    /** @var array<string, mixed> */
    private array $items;

    /** @param array<string, mixed> $items */
    public function __construct(array $items = [])
    {
        $this->items = $items;
    }

    /**
     * Read a value using dot notation. Returns $default if not found.
     * Example: get('app.session.lifetime', 3600)
     */
    public function get(string $key, mixed $default = null): mixed
    {
        if ($key === '') {
            return $this->items;
        }

        $segments = explode('.', $key);
        $value = $this->items;

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * Check whether a key exists.
     */
    public function has(string $key): bool
    {
        return $this->get($key, '__missing__') !== '__missing__';
    }

    /**
     * Set a value at runtime. Useful for tests and post-install tweaks.
     */
    public function set(string $key, mixed $value): void
    {
        $segments = explode('.', $key);
        $ref = &$this->items;
        foreach ($segments as $segment) {
            if (!isset($ref[$segment]) || !is_array($ref[$segment])) {
                $ref[$segment] = [];
            }
            $ref = &$ref[$segment];
        }
        $ref = $value;
    }

    /**
     * Get the whole config tree.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->items;
    }
}

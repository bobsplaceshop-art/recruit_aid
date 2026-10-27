<?php
declare(strict_types=1);

namespace CorporateAid\Core;

use Closure;
use RuntimeException;

/**
 * Minimal dependency injection container.
 * Stores services so they are built once and reused.
 *
 * @template-covariant T of object  (documentation only; the class is not generic per-instance)
 */
final class Container
{
    /** @var array<string, Closure(self): mixed> */
    private array $bindings = [];

    /** @var array<string, mixed> */
    private array $instances = [];

    /** @var array<string, bool> */
    private array $singletons = [];

    /**
     * Register a factory. If $singleton is true, the factory runs once
     * and the result is cached.
     *
     * @param Closure(self): mixed $factory
     */
    public function bind(string $id, Closure $factory, bool $singleton = false): void
    {
        $this->bindings[$id] = $factory;
        if ($singleton) {
            $this->singletons[$id] = true;
        }
    }

    /** @param Closure(self): mixed $factory */
    public function singleton(string $id, Closure $factory): void
    {
        $this->bind($id, $factory, true);
    }

    /**
     * Register an already-built value.
     */
    public function instance(string $id, mixed $value): void
    {
        $this->instances[$id] = $value;
    }

    public function has(string $id): bool
    {
        return isset($this->instances[$id]) || isset($this->bindings[$id]);
    }

    /**
     * Resolve a service. If it was bound as a singleton, cache it.
     *
     * The generic signature tells PHPStan that resolving a class-string
     * returns an instance of that class. This is what removes hundreds
     * of "Cannot call method on mixed" errors across the codebase.
     *
     * @template R of object
     * @param  class-string<R> $id
     * @return R
     */
    public function get(string $id): mixed
    {
        if (isset($this->instances[$id])) {
            /** @var R */
            return $this->instances[$id];
        }

        if (!isset($this->bindings[$id])) {
            throw new RuntimeException("Container: nothing bound for '{$id}'.");
        }

        /** @var R $value */
        $value = ($this->bindings[$id])($this);

        if (isset($this->singletons[$id])) {
            $this->instances[$id] = $value;
        }

        return $value;
    }
}

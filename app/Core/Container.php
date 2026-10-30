<?php
declare(strict_types=1);

namespace CorporateAid\Core;

use Closure;
use RuntimeException;

/**
 * Minimal dependency injection container.
 * Stores services so they are built once and reused.
 *
 * Explicit bindings only — no autowiring. Every class resolved via
 * get() must be bound in App::registerServices().
 *
 * Cycle detection: if a factory (directly or indirectly) asks the
 * container for a class that is already being resolved, we throw a
 * named exception instead of recursing. Without this guard, a
 * circular binding manifests as "Maximum function nesting level"
 * or — worse — as a misleading constructor-arity error from an
 * interrupted argument list. The guard makes the failure say what
 * it is.
 */
final class Container
{
    /** @var array<string, Closure(self): mixed> */
    private array $bindings = [];

    /** @var array<string, mixed> */
    private array $instances = [];

    /** @var array<string, bool> */
    private array $singletons = [];

    /** @var array<string, bool> Classes currently being resolved. */
    private array $resolving = [];

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
     * returns an instance of that class.
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

        if (isset($this->resolving[$id])) {
            $chain = implode(' -> ', array_keys($this->resolving)) . " -> {$id}";
            throw new RuntimeException(
                "Container: circular dependency detected while resolving '{$id}'. Chain: {$chain}"
            );
        }

        $this->resolving[$id] = true;
        try {
            /** @var R $value */
            $value = ($this->bindings[$id])($this);
        } finally {
            unset($this->resolving[$id]);
        }

        if (isset($this->singletons[$id])) {
            $this->instances[$id] = $value;
        }

        return $value;
    }
}

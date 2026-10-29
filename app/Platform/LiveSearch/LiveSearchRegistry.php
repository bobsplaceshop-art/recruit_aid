<?php
declare(strict_types=1);

namespace CorporateAid\Platform\LiveSearch;

use CorporateAid\Core\Container;

/**
 * Registry of searchable entities. Each entity slug maps to a repository
 * class that implements LiveSearchable.
 *
 * Registration in App::registerServices():
 *   LiveSearchRegistry::register('organisations', OrganisationRepository::class);
 *   LiveSearchRegistry::register('contacts', ContactRepository::class);
 *   ...
 */
final class LiveSearchRegistry
{
    /** @var array<string, class-string> */
    private static array $entities = [];

    private static ?Container $container = null;

    public static function boot(Container $container): void
    {
        self::$container = $container;
    }

    /** @param class-string $repositoryClass */
    public static function register(string $slug, string $repositoryClass): void
    {
        self::$entities[$slug] = $repositoryClass;
    }

    public static function has(string $slug): bool
    {
        return isset(self::$entities[$slug]);
    }

    /**
     * Resolve the repository instance for a slug. Returns null if not registered.
     */
    public static function resolve(string $slug): ?LiveSearchable
    {
        if (!isset(self::$entities[$slug]) || self::$container === null) {
            return null;
        }
        $repo = self::$container->get(self::$entities[$slug]);
        return $repo instanceof LiveSearchable ? $repo : null;
    }

    /** @return string[] */
    public static function all(): array
    {
        return array_keys(self::$entities);
    }
}

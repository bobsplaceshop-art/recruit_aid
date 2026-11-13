<?php
declare(strict_types=1);

namespace CorporateAid\Platform\Calendar;

use CorporateAid\Core\Auth;

/**
 * Static registry of calendar event sources.
 *
 * Modules register their sources at boot time via Module::register($c):
 *   CalendarRegistry::register($c->get(InterviewCalendarSource::class));
 *
 * The CalendarService then iterates the registry to aggregate events.
 *
 * Unlike LiveSearchRegistry, this registry is populated with INSTANCES
 * (not class names) because each source is a singleton bound in the
 * container — the module's register() method resolves it and hands it in.
 *
 * Filtering: filter($moduleKey, $auth) returns only sources whose module
 * matches $moduleKey (or all if null) AND that the user has permission
 * for. A user with calendar access but no interviews.view permission
 * will not see interview events.
 */
final class CalendarRegistry
{
    /** @var array<string, CalendarEventSource> */
    private static array $sources = [];

    public static function register(CalendarEventSource $source): void
    {
        self::$sources[$source->getKey()] = $source;
    }

    public static function has(string $key): bool
    {
        return isset(self::$sources[$key]);
    }

    public static function get(string $key): ?CalendarEventSource
    {
        return self::$sources[$key] ?? null;
    }

    /** @return array<string, CalendarEventSource> */
    public static function all(): array
    {
        return self::$sources;
    }

    /**
     * Return sources the user can see, optionally restricted to one module.
     *
     * @param string|null $moduleKey 'recruit' for the Recruit calendar,
     *                               'hr' for HR, or null for platform-wide.
     * @param Auth        $auth      Used to check each source's getPermission().
     *
     * @return array<int, CalendarEventSource>
     */
    public static function filter(?string $moduleKey, Auth $auth): array
    {
        $out = [];
        foreach (self::$sources as $source) {
            if ($moduleKey !== null && $source->getModuleKey() !== $moduleKey) {
                continue;
            }
            if (!$auth->can($source->getPermission())) {
                continue;
            }
            $out[] = $source;
        }
        return $out;
    }

    /**
     * Reset the registry. Test-only.
     */
    public static function reset(): void
    {
        self::$sources = [];
    }
}

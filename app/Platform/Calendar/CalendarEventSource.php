<?php
declare(strict_types=1);

namespace CorporateAid\Platform\Calendar;

/**
 * Contract for modules that contribute events to the shared calendar.
 *
 * Each implementation is registered with CalendarRegistry at boot time
 * and is responsible for querying its own events for a single tenant
 * within a date range.
 *
 * The calendar feature is reusable across any module:
 *   - Recruit Aid registers interview/task/offer/vacancy sources.
 *   - HR Aid (future) registers leave/onboarding/training sources.
 *   - No changes to CalendarService or CalendarController are needed.
 *
 * Tenant scoping is enforced by the caller (CalendarService passes the
 * resolved tenant_id to eventsForRange). The implementation MUST filter
 * its query by that tenant_id — no cross-tenant leakage.
 *
 * Permission scoping: getPermission() is checked by CalendarService
 * before the source is queried. A user who can view the calendar but
 * lacks the source-specific permission sees no events from it.
 */
interface CalendarEventSource
{
    /**
     * Unique key for this source, e.g. 'recruit.interviews'.
     */
    public function getKey(): string;

    /**
     * Human-readable label, e.g. 'Interviews'.
     */
    public function getLabel(): string;

    /**
     * Bootstrap badge color hint: 'info', 'success', 'warning', 'danger', 'primary', 'secondary'.
     */
    public function getColor(): string;

    /**
     * The module this source belongs to: 'recruit', 'hr', etc.
     * Used for module-scoped calendar filtering.
     */
    public function getModuleKey(): string;

    /**
     * The permission required to see this source's events.
     * Checked by CalendarService before calling eventsForRange().
     */
    public function getPermission(): string;

    /**
     * Return events for the given tenant within [startDate, endDate] (inclusive).
     *
     * @param int    $tenantId  Tenant to scope to. Implementation MUST filter by it.
     * @param string $startDate Y-m-d inclusive
     * @param string $endDate   Y-m-d inclusive
     *
     * @return array<int, array{
     *   date: string,        // Y-m-d — the day the event falls on
     *   title: string,       // short display title
     *   time: ?string,       // H:i — start time, or null for all-day events
     *   url: string,         // link to the entity detail page
     *   badge: string,       // short type label, e.g. 'Interview', 'Task due'
     *   color: string,       // bootstrap color hint for the badge
     *   meta: array<string, mixed> // free-form metadata for tooltips / future filters
     * }>
     */
    public function eventsForRange(int $tenantId, string $startDate, string $endDate): array;
}

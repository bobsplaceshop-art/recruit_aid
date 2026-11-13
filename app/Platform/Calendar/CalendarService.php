<?php
declare(strict_types=1);

namespace CorporateAid\Platform\Calendar;

use CorporateAid\Core\Auth;
use CorporateAid\Core\Database;
use CorporateAid\Core\PDOParams;

/**
 * Aggregates calendar events from all registered sources for a given
 * tenant and date range.
 *
 * Tenant scoping: the caller passes $tenantId. For tenant users this is
 * TenantContext::id(). For platform admins, the controller resolves it
 * from the effective tenant context (which is either the home tenant or
 * an explicitly context-switched tenant). The service never queries
 * across multiple tenants at once — that's a controller concern.
 *
 * Permission scoping: each source's getPermission() is checked via
 * Auth::can(). A user with calendar access but without a source-specific
 * permission sees only the sources they're allowed to see. The service
 * is a composition layer, not an authorization layer — but it enforces
 * the source-level permissions defensively.
 *
 * Delegation summaries: for the /cas/calendar route, delegatedSummaries()
 * returns count-only summaries for tenants the admin has active support
 * delegations to. No raw event data across tenants.
 */
final class CalendarService
{
    public function __construct(
        private Auth $auth,
        private Database $db
    ) {}

    /**
     * Aggregate events from all sources the user can see, scoped to one
     * tenant and one date range.
     *
     * @param int         $tenantId  The tenant whose events to include.
     * @param string      $startDate Y-m-d inclusive
     * @param string      $endDate   Y-m-d inclusive
     * @param string|null $moduleKey Restrict to one module ('recruit', 'hr'),
     *                               or null for all modules the user can see.
     *
     * @return array{
     *   events: array<string, array<int, array>>,   // grouped by Y-m-d
     *   summary: array<string, int>,                // source_key => count
     *   sources: array<int, array{key,label,color}>,
     *   range: array{start: string, end: string, tenant_id: int, module_key: ?string}
     * }
     */
    public function eventsForRange(
        int $tenantId,
        string $startDate,
        string $endDate,
        ?string $moduleKey = null
    ): array {
        $sources = CalendarRegistry::filter($moduleKey, $this->auth);

        $events = [];
        $summary = [];
        $sourceInfo = [];

        foreach ($sources as $source) {
            $sourceEvents = $source->eventsForRange($tenantId, $startDate, $endDate);
            $summary[$source->getKey()] = count($sourceEvents);
            $sourceInfo[] = [
                'key'   => $source->getKey(),
                'label' => $source->getLabel(),
                'color' => $source->getColor(),
            ];

            foreach ($sourceEvents as $event) {
                $date = $event['date'];
                if (!isset($events[$date])) {
                    $events[$date] = [];
                }
                $events[$date][] = $event;
            }
        }

        // Sort each day's events: all-day first, then by time.
        foreach ($events as &$dayEvents) {
            usort($dayEvents, function (array $a, array $b): int {
                $aTime = $a['time'] ?? '';
                $bTime = $b['time'] ?? '';
                if ($aTime === '' && $bTime === '') return 0;
                if ($aTime === '') return -1;
                if ($bTime === '') return 1;
                return strcmp($aTime, $bTime);
            });
        }
        unset($dayEvents);

        ksort($events);

        return [
            'events'  => $events,
            'summary' => $summary,
            'sources' => $sourceInfo,
            'range'   => [
                'start'      => $startDate,
                'end'        => $endDate,
                'tenant_id'  => $tenantId,
                'module_key' => $moduleKey,
            ],
        ];
    }

    /**
     * Convenience: aggregate events for a full calendar month.
     */
    public function eventsForMonth(int $year, int $month, int $tenantId, ?string $moduleKey = null): array
    {
        $month = max(1, min(12, $month));
        $first = sprintf('%04d-%02d-01', $year, $month);
        $last  = date('Y-m-t', strtotime($first));

        return $this->eventsForRange($tenantId, $first, $last, $moduleKey);
    }

    /**
     * Delegated summaries: for each tenant the current user holds an
     * active support delegation to, return the count of events in the
     * date range. No titles, no times, no URLs. Used on /cas/calendar
     * when the admin hasn't context-switched.
     *
     * @return array<int, array{tenant_id:int, tenant_name:string, event_count:int}>
     */
    public function delegatedSummaries(string $startDate, string $endDate): array
    {
        $user = $this->auth->user();
        if ($user === null) {
            return [];
        }

        // Source list = all visible sources (permission-checked).
        $sources = CalendarRegistry::filter(null, $this->auth);
        if (empty($sources)) {
            return [];
        }

        // Delegations are already permission-scoped + tenant-validated.
        // This method only reports counts.
        $rows = $this->fetchActiveDelegationsForSummary($user->id);
        if (empty($rows)) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $tenantId   = (int) $row['tenant_id'];
            $tenantName = (string) $row['tenant_name'];

            $count = 0;
            foreach ($sources as $source) {
                $count += count($source->eventsForRange($tenantId, $startDate, $endDate));
            }

            $out[] = [
                'tenant_id'   => $tenantId,
                'tenant_name' => $tenantName,
                'event_count' => $count,
            ];
        }
        return $out;
    }

    /**
     * Fetch active support delegations for a user, joined with tenant names.
     * Defensive: uses home tenant scoping (delegations ignore effective tenant).
     *
     * @return array<int, array{tenant_id:int, tenant_name:string}>
     */
    private function fetchActiveDelegationsForSummary(int $userId): array
    {
        $rows = $this->db->select(
            "SELECT DISTINCT d.`tenant_id`, t.`name` AS tenant_name
             FROM `core_support_delegations` d
             JOIN `core_tenants` t ON t.`id` = d.`tenant_id`
             WHERE d.`user_id` = ?
               AND d.`revocation_state` = 'active'
               AND d.`expires_at` > NOW()
               AND t.`deleted_at` IS NULL
             ORDER BY t.`name` ASC",
            PDOParams::positional([$userId])
        );

        return array_map(static fn (array $r): array => [
            'tenant_id'   => (int) ($r['tenant_id'] ?? 0),
            'tenant_name' => (string) ($r['tenant_name'] ?? ''),
        ], $rows);
    }
}

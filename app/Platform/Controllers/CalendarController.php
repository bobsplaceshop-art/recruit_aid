<?php
declare(strict_types=1);

namespace CorporateAid\Platform\Controllers;

use CorporateAid\Core\Auth;
use CorporateAid\Core\Container;
use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Core\TenantContext;
use CorporateAid\Core\View;
use CorporateAid\Platform\Calendar\CalendarService;

/**
 * Calendar controller.
 *
 * Two routes, one view, one service:
 *   GET /recruit/calendar  - moduleIndex('recruit') — tenant-scoped, Recruit sources only
 *   GET /cas/calendar      - platformIndex()       — platform calendar
 *
 * Platform calendar behavior:
 *   - If context-switched (TenantContext::id() !== homeId()), show the
 *     effective tenant's full raw calendar.
 *   - If not switched, show the home tenant's raw calendar + delegated
 *     summaries (counts only) for tenants the admin holds an active
 *     support delegation to. No cross-tenant raw events.
 */
final class CalendarController
{
    public function __construct(private Container $container) {}

    /** @param array<string, string> $params */
    public function moduleIndex(Request $request, array $params = []): Response
    {
        return $this->render($request, 'recruit', 'Calendar · Recruit Aid');
    }

    /** @param array<string, string> $params */
    public function platformIndex(Request $request, array $params = []): Response
    {
        return $this->render($request, null, 'Calendar · CAS Platform');
    }

    /**
     * @param string|null $moduleKey  Restricts the source set ('recruit' or null for all)
     */
    private function render(Request $request, ?string $moduleKey, string $title): Response
    {
        /** @var Auth $auth */
        $auth = $this->container->get(Auth::class);
        /** @var View $view */
        $view = $this->container->get(View::class);
        /** @var CalendarService $calendar */
        $calendar = $this->container->get(CalendarService::class);
        /** @var TenantContext $tenant */
        $tenant = $this->container->get(TenantContext::class);

        $user = $auth->user();
        if ($user === null) {
            return Response::redirect('/login');
        }

        // Resolve the tenant context.
        $tenantId = $tenant->id();
        $homeTenantId = $tenant->homeId();
        $isContextSwitched = $tenantId !== $homeTenantId;

        // Parse the month (default current).
        [$year, $month] = $this->parseMonth($request->queryStringValue('month', ''));

        // View mode: month, week, day.
        $viewMode = $request->queryStringValue('view', 'month');
        if (!in_array($viewMode, ['month', 'week', 'day'], true)) {
            $viewMode = 'month';
        }

        $data = $calendar->eventsForMonth($year, $month, $tenantId, $moduleKey);

        // Platform calendar + not context-switched: fetch delegated summaries.
        $delegatedSummaries = [];
        if ($moduleKey === null && !$isContextSwitched) {
            $first = sprintf('%04d-%02d-01', $year, $month);
            $last  = calendar_date(strtotime($first), 'Y-m-t') ?? $first;
            $delegatedSummaries = $calendar->delegatedSummaries($first, $last);
        }

        $html = $view->render('calendar/index', [
            'title'              => $title,
            'user'               => $user,
            'year'               => $year,
            'month'              => $month,
            'view_mode'          => $viewMode,
            'module_key'         => $moduleKey,
            'tenant_id'          => $tenantId,
            'home_tenant_id'     => $homeTenantId,
            'is_context_switched'=> $isContextSwitched,
            'events'             => $data['events'],
            'summary'            => $data['summary'],
            'sources'            => $data['sources'],
            'delegated_summaries'=> $delegatedSummaries,
        ], 'layouts/app');

        return Response::html($html);
    }

    /**
     * Parse the ?month=YYYY-MM query param. Falls back to the current month.
     *
     * @return array{0:int, 1:int} [year, month]
     */
    private function parseMonth(string $raw): array
    {
        if (preg_match('/^(\d{4})-(\d{2})$/', $raw, $m) === 1) {
            $year  = (int) $m[1];
            $month = (int) $m[2];
            if ($year >= 2000 && $year <= 2100 && $month >= 1 && $month <= 12) {
                return [$year, $month];
            }
        }
        return [(int) date('Y'), (int) date('n')];
    }
}

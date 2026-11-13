<?php
/**
 * Unified calendar view.
 *
 * Variables from CalendarController:
 *   $year, $month, $view_mode, $module_key
 *   $tenant_id, $home_tenant_id, $is_context_switched
 *   $events               — [Y-m-d => [event...]]
 *   $summary              — [source_key => count]
 *   $sources              — [{key, label, color}]
 *   $delegated_summaries  — [{tenant_id, tenant_name, event_count}]  (platform only, no context switch)
 *
 * View modes:
 *   month — 7-column grid, day cells with events
 *   week  — 7-column grid, hourly slots
 *   day   — single-day hourly slots
 *
 * Navigation: prev/next month, prev/next week, prev/next day depending on mode.
 */

$monthNames = ['January','February','March','April','May','June',
               'July','August','September','October','November','December'];

$firstOfMonth = sprintf('%04d-%02d-01', $year, $month);
$monthStart   = strtotime($firstOfMonth);
$monthEnd     = strtotime(date('Y-m-t', $monthStart));
$today        = date('Y-m-d');

$prevMonth = date('Y-m', strtotime('-1 month', $monthStart));
$nextMonth = date('Y-m', strtotime('+1 month', $monthStart));
$currentMonth = date('Y-m');
$currentBase  = $module_key === 'recruit' ? '/recruit/calendar' : '/cas/calendar';

$monthLabel = $monthNames[$month - 1] . ' ' . $year;

$totalEvents = array_sum($summary);
?>
<style>
    .cal-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 1px; background: var(--ra-border); border: 1px solid var(--ra-border); border-radius: .5rem; overflow: hidden; }
    .cal-head { padding: .5rem; background: var(--ra-surface-2); font-weight: 600; font-size: .85rem; text-align: center; }
    .cal-cell { background: var(--ra-surface); padding: .35rem; min-height: 96px; font-size: .8rem; }
    .cal-cell.is-outside { background: var(--ra-surface-2); opacity: .55; }
    .cal-cell.is-today { outline: 2px solid var(--ra-primary); outline-offset: -2px; }
    .cal-date { font-weight: 600; margin-bottom: .25rem; color: var(--ra-text-strong); }
    .cal-event { display: block; padding: 1px 4px; margin: .15rem 0; border-radius: 3px; font-size: .75rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; text-decoration: none; color: #fff; }
    .cal-event.bg-info    { background: #0dcaf0; }
    .cal-event.bg-primary { background: #0d6efd; }
    .cal-event.bg-warning { background: #ffc107; color: #212529; }
    .cal-event.bg-success { background: #198754; }
    .cal-event.bg-danger  { background: #dc3545; }
    .cal-event.bg-secondary { background: #6c757d; }
    .cal-more { display: block; font-size: .7rem; color: var(--ra-text-muted); padding-top: 2px; }
    .cal-legend { display: flex; flex-wrap: wrap; gap: 1rem; margin: 1rem 0; }
    .cal-legend-item { display: flex; align-items: center; gap: .35rem; font-size: .85rem; }
    .cal-legend-dot { width: 12px; height: 12px; border-radius: 50%; display: inline-block; }
    .cal-nav { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
</style>

<div class="ra-page-band">
    <div class="ra-page-band-row">
        <div>
            <div class="ra-page-band-label">
                <?= $module_key === 'recruit' ? 'Workspace · Calendar' : 'Platform · Calendar' ?>
            </div>
            <h1 class="ra-page-band-title"><?= e($monthLabel) ?></h1>
            <p class="ra-page-sub">
                <?= (int) $totalEvents ?> event<?= $totalEvents === 1 ? '' : 's' ?>
                <?php if ($module_key === null && $is_context_switched): ?>
                    · <strong>Viewing as tenant #<?= (int) $tenant_id ?></strong>
                <?php elseif ($module_key === null): ?>
                    · <span class="muted">home tenant #<?= (int) $home_tenant_id ?></span>
                <?php endif; ?>
            </p>
        </div>
        <div class="ra-page-band-actions">
            <a href="?month=<?= e($currentMonth) ?>&view=<?= e($view_mode) ?>" class="ra-btn ra-btn-outline">Today</a>
        </div>
    </div>
</div>

<?php if (!empty($delegated_summaries)): ?>
    <div class="ra-card" style="margin-bottom:1rem;">
        <strong>Delegated tenant summaries</strong> <span class="muted">(switch context to see raw events)</span>
        <ul style="margin:.5rem 0 0;">
            <?php foreach ($delegated_summaries as $ds): ?>
                <li>
                    <strong><?= e($ds['tenant_name']) ?></strong>
                    — <?= (int) $ds['event_count'] ?> event<?= $ds['event_count'] === 1 ? '' : 's' ?> this month
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="cal-nav">
    <div>
        <a href="?month=<?= e($prevMonth) ?>&view=<?= e($view_mode) ?>" class="ra-btn ra-btn-outline">&laquo; Prev</a>
    </div>
    <div>
        <?php foreach (['month' => 'Month', 'week' => 'Week', 'day' => 'Day'] as $mode => $label): ?>
            <a href="?month=<?= (int) $year ?>-<?= str_pad((string) $month, 2, '0', STR_PAD_LEFT) ?>&view=<?= e($mode) ?>"
               class="ra-btn <?= $view_mode === $mode ? 'ra-btn-primary' : 'ra-btn-outline' ?>">
                <?= e($label) ?>
            </a>
        <?php endforeach; ?>
    </div>
    <div>
        <a href="?month=<?= e($nextMonth) ?>&view=<?= e($view_mode) ?>" class="ra-btn ra-btn-outline">Next &raquo;</a>
    </div>
</div>

<div class="cal-legend">
    <?php foreach ($sources as $src): ?>
        <div class="cal-legend-item">
            <span class="cal-legend-dot" style="background: <?= e(match($src['color']) {
                'info'      => '#0dcaf0',
                'primary'   => '#0d6efd',
                'warning'   => '#ffc107',
                'success'   => '#198754',
                'danger'    => '#dc3545',
                default     => '#6c757d',
            }) ?>;"></span>
            <?= e($src['label']) ?> (<?= (int) ($summary[$src['key']] ?? 0) ?>)
        </div>
    <?php endforeach; ?>
</div>

<?php
// ============================================================
// CALENDAR GRID
// ============================================================
?>

<?php if ($view_mode === 'month'): ?>
    <?php
    // Month grid. Monday-start. Cells for padding days before/after.
    $firstDayOfMonth = (int) date('N', $monthStart); // 1=Mon, 7=Sun
    $daysInMonth = (int) date('t', $monthStart);
    $startPad = $firstDayOfMonth - 1; // how many days from previous month
    $totalCells = (int) ceil(($startPad + $daysInMonth) / 7) * 7;
    $startDate = strtotime('-' . $startPad . ' days', $monthStart);
    ?>
    <div class="cal-grid" style="grid-template-rows: auto repeat(<?= (int) ($totalCells / 7) ?>, auto);">
        <?php foreach (['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $d): ?>
            <div class="cal-head"><?= e($d) ?></div>
        <?php endforeach; ?>

        <?php for ($i = 0; $i < $totalCells; $i++):
            $cellDate = date('Y-m-d', strtotime('+' . $i . ' days', $startDate));
            $dayNum = (int) date('j', strtotime($cellDate));
            $isOutside = substr($cellDate, 0, 7) !== sprintf('%04d-%02d', $year, $month);
            $isToday = $cellDate === $today;
            $dayEvents = $events[$cellDate] ?? [];
        ?>
            <div class="cal-cell<?= $isOutside ? ' is-outside' : '' ?><?= $isToday ? ' is-today' : '' ?>">
                <div class="cal-date"><?= (int) $dayNum ?></div>
                <?php foreach (array_slice($dayEvents, 0, 3) as $event): ?>
                    <a href="<?= e($event['url']) ?>"
                       class="cal-event bg-<?= e($event['color']) ?>"
                       title="<?= e($event['title'] . ($event['time'] ? ' @ ' . $event['time'] : '')) ?>">
                        <?php if ($event['time']): ?><strong><?= e($event['time']) ?></strong> <?php endif; ?><?= e($event['title']) ?>
                    </a>
                <?php endforeach; ?>
                <?php if (count($dayEvents) > 3): ?>
                    <span class="cal-more">+<?= (int) (count($dayEvents) - 3) ?> more</span>
                <?php endif; ?>
            </div>
        <?php endfor; ?>
    </div>

<?php elseif ($view_mode === 'week'): ?>
    <?php
    // Week view. Anchored on the first day of the month's week (Mon-Sun).
    // Shows all 7 days of the week that contains the first of the month.
    $weekStart = strtotime('monday this week', $monthStart);
    $weekDays = [];
    for ($i = 0; $i < 7; $i++) {
        $weekDays[] = date('Y-m-d', strtotime('+' . $i . ' days', $weekStart));
    }
    ?>
    <div class="cal-grid" style="grid-template-columns: repeat(7, 1fr);">
        <?php foreach ($weekDays as $day): ?>
            <?php
            $ts = strtotime($day);
            $isToday = $day === $today;
            $dayEvents = $events[$day] ?? [];
            ?>
            <div>
                <div class="cal-head<?= $isToday ? ' is-today' : '' ?>">
                    <?= e(date('D j', $ts)) ?>
                </div>
                <div class="cal-cell" style="min-height: 300px;">
                    <?php foreach ($dayEvents as $event): ?>
                        <a href="<?= e($event['url']) ?>"
                           class="cal-event bg-<?= e($event['color']) ?>"
                           title="<?= e($event['title']) ?>">
                            <?php if ($event['time']): ?><strong><?= e($event['time']) ?></strong> <?php endif; ?><?= e($event['title']) ?>
                        </a>
                    <?php endforeach; ?>
                    <?php if (empty($dayEvents)): ?>
                        <span class="muted" style="font-size:.75rem;">—</span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

<?php else: // day view ?>
    <?php
    // Day view: anchored on the first of the month for simplicity.
    $dayDate = sprintf('%04d-%02d-01', $year, $month);
    $dayEvents = $events[$dayDate] ?? [];
    ?>
    <div class="ra-card">
        <h2 style="margin:0 0 1rem;"><?= e(date('l, F j, Y', strtotime($dayDate))) ?></h2>
        <?php if (empty($dayEvents)): ?>
            <p class="muted">No events on this day.</p>
        <?php else: ?>
            <ul style="list-style:none;padding:0;">
                <?php foreach ($dayEvents as $event): ?>
                    <li style="margin:.5rem 0;padding:.5rem;border-left:3px solid var(--ra-primary);background: var(--ra-surface-2);">
                        <a href="<?= e($event['url']) ?>" style="text-decoration:none;">
                            <?php if ($event['time']): ?>
                                <strong><?= e($event['time']) ?></strong> —
                            <?php endif; ?>
                            <?= e($event['title']) ?>
                        </a>
                        <span class="muted" style="font-size:.8rem;">(<?= e($event['badge']) ?>)</span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
<?php endif; ?>

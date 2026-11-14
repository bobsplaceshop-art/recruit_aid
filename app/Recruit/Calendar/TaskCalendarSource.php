<?php
declare(strict_types=1);

namespace CorporateAid\Recruit\Calendar;

use CorporateAid\Core\Database;
use CorporateAid\Core\PDOParams;
use CorporateAid\Core\Row;
use CorporateAid\Platform\Calendar\CalendarEventSource;

/**
 * Tasks as calendar events.
 *
 * Shows open tasks with a due date. Completed and cancelled tasks are
 * excluded — the calendar shows upcoming obligations, not history.
 *
 * Tasks with a due_at timestamp get a time slot. Tasks with a date-only
 * due_at (midnight) are shown as all-day events. The distinction is
 * applied in the view, not here — this source returns the actual
 * due_at timestamp string.
 */
final class TaskCalendarSource implements CalendarEventSource
{
    public function __construct(private Database $db) {}

    public function getKey(): string { return 'recruit.tasks'; }
    public function getLabel(): string { return 'Tasks'; }
    public function getColor(): string { return 'warning'; }
    public function getModuleKey(): string { return 'recruit'; }
    public function getPermission(): string { return 'recruit.tasks.view'; }

    /**
     * @return array<int, array{
     *   date: string, title: string, time: ?string, url: string,
     *   badge: string, color: string, meta: array<string, mixed>
     * }>
     */
    public function eventsForRange(int $tenantId, string $startDate, string $endDate): array
    {
        $rows = $this->db->select(
            "SELECT t.`id`, t.`title`, t.`due_at`, t.`priority`, t.`status`,
                    t.`assigned_to`, t.`entity_type`, t.`entity_id`,
                    u.`name` AS assigned_name
             FROM `recruit_tasks` t
             LEFT JOIN `core_users` u ON u.`id` = t.`assigned_to`
             WHERE t.`tenant_id` = ?
               AND t.`deleted_at` IS NULL
               AND t.`status` = 'open'
               AND t.`due_at` IS NOT NULL
               AND DATE(t.`due_at`) >= ?
               AND DATE(t.`due_at`) <= ?
             ORDER BY t.`due_at` ASC",
            PDOParams::positional([$tenantId, $startDate, $endDate])
        );

        $events = [];
        foreach ($rows as $r) {
            $dueAt = Row::str($r, 'due_at');
            $timestamp = strtotime($dueAt);
            $date = calendar_date($timestamp);
            if ($date === null) {
                continue;
            }
            $hour = (int) (calendar_date($timestamp, 'H') ?? '0');
            $minute = (int) (calendar_date($timestamp, 'i') ?? '0');

            // A task due at 00:00 is treated as an all-day event (no time slot).
            $isAllDay = ($hour === 0 && $minute === 0);
            $time = $isAllDay ? null : calendar_date($timestamp, 'H:i');

            $priority = Row::str($r, 'priority');
            $color = match ($priority) {
                'high'   => 'danger',
                'low'    => 'secondary',
                default  => $this->getColor(),
            };

            $title = Row::str($r, 'title');
            if ($priority === 'high') {
                $title = '⚠ ' . $title;
            }

            $assignedName = Row::nullableStr($r, 'assigned_name');
            $entityType = Row::nullableStr($r, 'entity_type');
            $entityId = Row::nullableInt($r, 'entity_id');

            $events[] = [
                'date'  => $date,
                'title' => $title,
                'time'  => $time,
                'url'   => '/recruit/tasks',
                'badge' => 'Task due',
                'color' => $color,
                'meta'  => [
                    'task_id'     => Row::int($r, 'id'),
                    'priority'    => $priority,
                    'assigned_to' => $assignedName,
                    'entity_type' => $entityType,
                    'entity_id'   => $entityId,
                    'is_all_day'  => $isAllDay,
                ],
            ];
        }

        return $events;
    }
}

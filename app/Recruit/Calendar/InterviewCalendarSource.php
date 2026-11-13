<?php
declare(strict_types=1);

namespace CorporateAid\Recruit\Calendar;

use CorporateAid\Core\Database;
use CorporateAid\Core\PDOParams;
use CorporateAid\Core\Row;
use CorporateAid\Platform\Calendar\CalendarEventSource;

/**
 * Interviews as calendar events.
 *
 * Shows scheduled interviews (status='scheduled') with their scheduled_at
 * as the event time. Each event links to the interview detail page.
 */
final class InterviewCalendarSource implements CalendarEventSource
{
    public function __construct(private Database $db) {}

    public function getKey(): string { return 'recruit.interviews'; }
    public function getLabel(): string { return 'Interviews'; }
    public function getColor(): string { return 'info'; }
    public function getModuleKey(): string { return 'recruit'; }
    public function getPermission(): string { return 'recruit.interviews.view'; }

    public function eventsForRange(int $tenantId, string $startDate, string $endDate): array
    {
        $rows = $this->db->select(
            "SELECT i.`id`, i.`code`, i.`scheduled_at`, i.`type`, i.`status`,
                    i.`location`,
                    a.`candidate_id`, a.`vacancy_id`,
                    c.`first_name`, c.`last_name`,
                    v.`title` AS vacancy_title
             FROM `recruit_interviews` i
             LEFT JOIN `recruit_applications` a ON a.`id` = i.`application_id`
             LEFT JOIN `recruit_candidates` c ON c.`id` = a.`candidate_id`
             LEFT JOIN `recruit_vacancies` v ON v.`id` = a.`vacancy_id`
             WHERE i.`tenant_id` = ?
               AND i.`deleted_at` IS NULL
               AND i.`status` = 'scheduled'
               AND DATE(i.`scheduled_at`) >= ?
               AND DATE(i.`scheduled_at`) <= ?
             ORDER BY i.`scheduled_at` ASC",
            PDOParams::positional([$tenantId, $startDate, $endDate])
        );

        $events = [];
        foreach ($rows as $r) {
            $scheduled = Row::str($r, 'scheduled_at');
            $timestamp = strtotime($scheduled);
            $date = date('Y-m-d', $timestamp);
            $time = date('H:i', $timestamp);

            $candidateName = trim(Row::str($r, 'first_name') . ' ' . Row::str($r, 'last_name'));
            $vacancyTitle = Row::str($r, 'vacancy_title');
            $type = Row::str($r, 'type');

            $title = 'Interview';
            if ($candidateName !== '') {
                $title .= ' — ' . $candidateName;
            }
            if ($vacancyTitle !== '') {
                $title .= ' (' . $vacancyTitle . ')';
            }

            $events[] = [
                'date'  => $date,
                'title' => $title,
                'time'  => $time,
                'url'   => '/recruit/interviews/' . Row::str($r, 'code'),
                'badge' => ucfirst($type) . ' Interview',
                'color' => $this->getColor(),
                'meta'  => [
                    'code'       => Row::str($r, 'code'),
                    'type'       => $type,
                    'location'   => Row::nullableStr($r, 'location'),
                    'candidate'  => $candidateName !== '' ? $candidateName : null,
                    'vacancy'    => $vacancyTitle !== '' ? $vacancyTitle : null,
                ],
            ];
        }

        return $events;
    }
}

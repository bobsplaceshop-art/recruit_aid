<?php
declare(strict_types=1);

namespace CorporateAid\Recruit\Calendar;

use CorporateAid\Core\Database;
use CorporateAid\Core\PDOParams;
use CorporateAid\Core\Row;
use CorporateAid\Platform\Calendar\CalendarEventSource;

/**
 * Vacancy target hire dates as calendar events.
 *
 * Shows the target_hire_date of vacancies that are still in an active
 * pipeline stage (open, sourcing, screening, shortlisting, client_review,
 * interviewing, offer). Draft, filled, cancelled, and closed vacancies
 * are excluded — the calendar tracks live obligations.
 *
 * target_hire_date is a date (not a datetime), so all events from this
 * source are all-day (time = null).
 */
final class VacancyCalendarSource implements CalendarEventSource
{
    public function __construct(private Database $db) {}

    public function getKey(): string { return 'recruit.vacancies'; }
    public function getLabel(): string { return 'Vacancy target hires'; }
    public function getColor(): string { return 'primary'; }
    public function getModuleKey(): string { return 'recruit'; }
    public function getPermission(): string { return 'recruit.vacancies.view'; }

    public function eventsForRange(int $tenantId, string $startDate, string $endDate): array
    {
        $rows = $this->db->select(
            "SELECT v.`id`, v.`code`, v.`title`, v.`target_hire_date`, v.`status`,
                    v.`positions_count`,
                    o.`name` AS org_name
             FROM `recruit_vacancies` v
             LEFT JOIN `recruit_client_organisations` o ON o.`id` = v.`client_organisation_id`
             WHERE v.`tenant_id` = ?
               AND v.`deleted_at` IS NULL
               AND v.`target_hire_date` IS NOT NULL
               AND v.`status` IN ('open','sourcing','screening','shortlisting','client_review','interviewing','offer')
               AND v.`target_hire_date` >= ?
               AND v.`target_hire_date` <= ?
             ORDER BY v.`target_hire_date` ASC",
            PDOParams::positional([$tenantId, $startDate, $endDate])
        );

        $events = [];
        foreach ($rows as $r) {
            $target = Row::str($r, 'target_hire_date');
            $date = date('Y-m-d', strtotime($target));

            $title = Row::str($r, 'title');
            $positions = Row::int($r, 'positions_count', 1);
            if ($positions > 1) {
                $title .= " (×{$positions})";
            }

            $orgName = Row::nullableStr($r, 'org_name');
            $status = Row::str($r, 'status');

            $events[] = [
                'date'  => $date,
                'title' => 'Target hire: ' . $title,
                'time'  => null,
                'url'   => '/recruit/vacancies/' . Row::str($r, 'code'),
                'badge' => 'Vacancy target',
                'color' => $this->getColor(),
                'meta'  => [
                    'code'       => Row::str($r, 'code'),
                    'status'     => $status,
                    'client'     => $orgName,
                    'positions'  => $positions,
                ],
            ];
        }

        return $events;
    }
}

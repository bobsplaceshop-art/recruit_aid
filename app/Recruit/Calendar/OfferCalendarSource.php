<?php
declare(strict_types=1);

namespace CorporateAid\Recruit\Calendar;

use CorporateAid\Core\Database;
use CorporateAid\Core\PDOParams;
use CorporateAid\Core\Row;
use CorporateAid\Platform\Calendar\CalendarEventSource;

/**
 * Offer deadlines as calendar events.
 *
 * Shows offers whose expiry date falls within the range and whose status
 * indicates the offer is still actionable (draft, sent). Accepted,
 * declined, expired, and withdrawn offers are excluded — the calendar
 * shows deadlines, not history.
 *
 * Offer expiry is a date (not a datetime), so all events from this
 * source are all-day (time = null).
 */
final class OfferCalendarSource implements CalendarEventSource
{
    public function __construct(private Database $db) {}

    public function getKey(): string { return 'recruit.offers'; }
    public function getLabel(): string { return 'Offer deadlines'; }
    public function getColor(): string { return 'success'; }
    public function getModuleKey(): string { return 'recruit'; }
    public function getPermission(): string { return 'recruit.offers.view'; }

    public function eventsForRange(int $tenantId, string $startDate, string $endDate): array
    {
        $rows = $this->db->select(
            "SELECT o.`id`, o.`code`, o.`expires_at`, o.`status`,
                    o.`currency`, o.`offered_salary`,
                    a.`candidate_id`, a.`vacancy_id`,
                    c.`first_name`, c.`last_name`,
                    v.`title` AS vacancy_title
             FROM `recruit_offers` o
             LEFT JOIN `recruit_applications` a ON a.`id` = o.`application_id`
             LEFT JOIN `recruit_candidates` c ON c.`id` = a.`candidate_id`
             LEFT JOIN `recruit_vacancies` v ON v.`id` = a.`vacancy_id`
             WHERE o.`tenant_id` = ?
               AND o.`deleted_at` IS NULL
               AND o.`status` IN ('draft', 'sent')
               AND o.`expires_at` >= ?
               AND o.`expires_at` <= ?
             ORDER BY o.`expires_at` ASC",
            PDOParams::positional([$tenantId, $startDate, $endDate])
        );

        $events = [];
        foreach ($rows as $r) {
            $expires = Row::str($r, 'expires_at');
            $date = date('Y-m-d', strtotime($expires));

            $candidateName = trim(Row::str($r, 'first_name') . ' ' . Row::str($r, 'last_name'));
            $vacancyTitle = Row::str($r, 'vacancy_title');
            $status = Row::str($r, 'status');

            $title = 'Offer expires';
            if ($candidateName !== '') {
                $title .= ' — ' . $candidateName;
            }
            if ($vacancyTitle !== '') {
                $title .= ' (' . $vacancyTitle . ')';
            }

            $badge = $status === 'sent' ? 'Offer expiring' : 'Offer draft expiring';
            $color = $status === 'sent' ? 'warning' : 'secondary';

            $events[] = [
                'date'  => $date,
                'title' => $title,
                'time'  => null,
                'url'   => '/recruit/offers/' . Row::str($r, 'code'),
                'badge' => $badge,
                'color' => $color,
                'meta'  => [
                    'code'        => Row::str($r, 'code'),
                    'status'      => $status,
                    'candidate'   => $candidateName !== '' ? $candidateName : null,
                    'vacancy'     => $vacancyTitle !== '' ? $vacancyTitle : null,
                    'expires_on'  => $expires,
                ],
            ];
        }

        return $events;
    }
}

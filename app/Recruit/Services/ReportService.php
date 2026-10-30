<?php
declare(strict_types=1);

namespace CorporateAid\Recruit\Services;

use CorporateAid\Core\Database;
use CorporateAid\Core\PDOParams;
use CorporateAid\Core\Row;

/**
 * Analytics and CSV export.
 *
 * Reports are computed from live tables (recruit_applications,
 * recruit_application_stage_history, recruit_vacancies,
 * recruit_candidates). No denormalized cache yet — fast enough at
 * current scale (< 10k rows per table).
 */
final class ReportService
{
    public function __construct(private Database $db) {}

    /**
     * Funnel report: count + avg time-in-stage for each stage of a vacancy.
     *
     * For each stage, count how many applications have ever entered it
     * (looking at recruit_application_stage_history + the current stage).
     * Average time-in-stage is computed from consecutive history entries
     * per application.
     *
     * @return array<int, array{
     *   stage_id:int, label:string, key_slug:string, sort_order:int,
     *   entered:int, current:int, avg_days:float
     * }>
     */
    public function funnelByVacancy(int $vacancyId): array
    {
        $stages = $this->db->select(
            "SELECT `id`, `key_slug`, `label`, `sort_order`
             FROM `recruit_vacancy_stages`
             WHERE `vacancy_id` = ?
             ORDER BY `sort_order` ASC",
            PDOParams::positional([$vacancyId])
        );

        $out = [];
        foreach ($stages as $s) {
            $sid = Row::int($s, 'id');

            // Entered: distinct applications that have a history row with to_stage_id = sid
            $entered = $this->db->scalarInt(
                "SELECT COUNT(DISTINCT sh.`application_id`)
                 FROM `recruit_application_stage_history` sh
                 JOIN `recruit_applications` a ON a.`id` = sh.`application_id`
                 WHERE sh.`to_stage_id` = ? AND a.`vacancy_id` = ? AND a.`deleted_at` IS NULL",
                PDOParams::positional([$sid, $vacancyId])
            );

            // Currently in this stage
            $current = $this->db->scalarInt(
                "SELECT COUNT(*) FROM `recruit_applications`
                 WHERE `current_stage_id` = ? AND `vacancy_id` = ?
                   AND `deleted_at` IS NULL AND `status` = 'active'",
                PDOParams::positional([$sid, $vacancyId])
            );

            // Average time-in-stage: for each application that entered this
            // stage, find the next history row (to a different stage) and
            // compute the delta. If no next row, use NOW().
            $avgSeconds = $this->db->scalar(
                "SELECT AVG(duration) FROM (
                    SELECT sh.`moved_at` AS entered_at,
                           COALESCE(
                               (SELECT MIN(sh2.`moved_at`)
                                FROM `recruit_application_stage_history` sh2
                                WHERE sh2.`application_id` = sh.`application_id`
                                  AND sh2.`id` > sh.`id`
                                  AND sh2.`to_stage_id` <> sh.`to_stage_id`),
                               NOW()
                           ) AS exited_at,
                           TIMESTAMPDIFF(SECOND, sh.`moved_at`,
                               COALESCE(
                                   (SELECT MIN(sh2.`moved_at`)
                                    FROM `recruit_application_stage_history` sh2
                                    WHERE sh2.`application_id` = sh.`application_id`
                                      AND sh2.`id` > sh.`id`
                                      AND sh2.`to_stage_id` <> sh.`to_stage_id`),
                                   NOW()
                               )
                           ) AS duration
                    FROM `recruit_application_stage_history` sh
                    JOIN `recruit_applications` a ON a.`id` = sh.`application_id`
                    WHERE sh.`to_stage_id` = ? AND a.`vacancy_id` = ? AND a.`deleted_at` IS NULL
                ) d",
                PDOParams::positional([$sid, $vacancyId])
            );

            $out[] = [
                'stage_id'   => $sid,
                'label'      => Row::str($s, 'label'),
                'key_slug'   => Row::str($s, 'key_slug'),
                'sort_order' => Row::int($s, 'sort_order'),
                'entered'    => $entered,
                'current'    => $current,
                'avg_days'   => $avgSeconds !== null && is_numeric($avgSeconds)
                    ? round(((float) $avgSeconds) / 86400, 1)
                    : 0.0,
            ];
        }
        return $out;
    }

    /**
     * Time-to-hire: average days from applied_at to hired for
     * applications with status = 'hired'.
     *
     * @return array<int, array{
     *   vacancy_id:int, vacancy_code:string, vacancy_title:string,
     *   hired_count:int, avg_days:float
     * }>
     */
    public function timeToHire(): array
    {
        $rows = $this->db->select(
            "SELECT v.`id` AS vacancy_id, v.`code` AS vacancy_code, v.`title` AS vacancy_title,
                    COUNT(a.`id`) AS hired_count,
                    AVG(TIMESTAMPDIFF(SECOND, a.`applied_at`, a.`updated_at`)) AS avg_seconds
             FROM `recruit_applications` a
             JOIN `recruit_vacancies` v ON v.`id` = a.`vacancy_id`
             WHERE a.`status` = 'hired' AND a.`deleted_at` IS NULL AND v.`deleted_at` IS NULL
             GROUP BY v.`id`
             ORDER BY hired_count DESC, avg_seconds ASC"
        );

        return array_map(static fn(array $r) => [
            'vacancy_id'    => Row::int($r, 'vacancy_id'),
            'vacancy_code'  => Row::str($r, 'vacancy_code'),
            'vacancy_title' => Row::str($r, 'vacancy_title'),
            'hired_count'   => Row::int($r, 'hired_count'),
            'avg_days'      => is_numeric($r['avg_seconds'] ?? null)
                ? round(((float) $r['avg_seconds']) / 86400, 1)
                : 0.0,
        ], $rows);
    }

    /**
     * Recruiter productivity: counts per recruiter_id over the given
     * window. Uses `created_by` on candidates and applications,
     * `created_by` on interviews, `created_by` on offers.
     *
     * @return array<int, array{
     *   user_id:int, name:string,
     *   candidates:int, applications:int, interviews:int, offers:int, hires:int
     * }>
     */
    public function recruiterProductivity(int $days = 30): array
    {
        // Build per-user aggregate using UNION ALL + GROUP BY.
        $rows = $this->db->select(
            "SELECT u.`id` AS user_id, u.`name`,
                    COALESCE(SUM(t.candidates), 0)   AS candidates,
                    COALESCE(SUM(t.applications), 0) AS applications,
                    COALESCE(SUM(t.interviews), 0)   AS interviews,
                    COALESCE(SUM(t.offers), 0)       AS offers,
                    COALESCE(SUM(t.hires), 0)        AS hires
             FROM `core_users` u
             LEFT JOIN (
                 SELECT `created_by` AS user_id, 1 AS candidates, 0 AS applications, 0 AS interviews, 0 AS offers, 0 AS hires
                 FROM `recruit_candidates`
                 WHERE `deleted_at` IS NULL AND `created_at` >= NOW() - INTERVAL ? DAY
                 UNION ALL
                 SELECT `created_by`, 0, 1, 0, 0, 0
                 FROM `recruit_applications`
                 WHERE `deleted_at` IS NULL AND `created_at` >= NOW() - INTERVAL ? DAY
                 UNION ALL
                 SELECT `created_by`, 0, 0, 1, 0, 0
                 FROM `recruit_interviews`
                 WHERE `deleted_at` IS NULL AND `created_at` >= NOW() - INTERVAL ? DAY
                 UNION ALL
                 SELECT `created_by`, 0, 0, 0, 1, 0
                 FROM `recruit_offers`
                 WHERE `deleted_at` IS NULL AND `created_at` >= NOW() - INTERVAL ? DAY
                 UNION ALL
                 SELECT a.`created_by`, 0, 0, 0, 0, 1
                 FROM `recruit_applications` a
                 WHERE a.`deleted_at` IS NULL AND a.`status` = 'hired'
                   AND a.`updated_at` >= NOW() - INTERVAL ? DAY
             ) t ON t.`user_id` = u.`id`
             WHERE u.`deleted_at` IS NULL AND u.`is_client_user` = 0
             GROUP BY u.`id`, u.`name`
             HAVING candidates + applications + interviews + offers + hires > 0
             ORDER BY hires DESC, applications DESC, candidates DESC",
            PDOParams::positional([$days, $days, $days, $days, $days])
        );

        return array_map(static fn(array $r) => [
            'user_id'      => Row::int($r, 'user_id'),
            'name'         => Row::str($r, 'name'),
            'candidates'   => Row::int($r, 'candidates'),
            'applications' => Row::int($r, 'applications'),
            'interviews'   => Row::int($r, 'interviews'),
            'offers'       => Row::int($r, 'offers'),
            'hires'        => Row::int($r, 'hires'),
        ], $rows);
    }

    /**
     * Stream a list of rows as CSV into the output buffer.
     *
     * @param array<int, array<string, mixed>> $rows
     * @param array<string, string>            $headers  column_key => display_label
     * @param string                           $filename
     */
    public function streamCsv(array $rows, array $headers, string $filename): void
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');
        if ($out === false) return;

        // BOM for Excel compatibility
        fwrite($out, "\xEF\xBB\xBF");

        fputcsv($out, array_values($headers));
        foreach ($rows as $row) {
            $line = [];
            foreach (array_keys($headers) as $key) {
                $v = $row[$key] ?? '';
                $line[] = is_scalar($v) ? (string) $v : '';
            }
            fputcsv($out, $line);
        }
        fclose($out);
    }
}

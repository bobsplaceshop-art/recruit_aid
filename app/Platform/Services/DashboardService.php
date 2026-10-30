<?php
declare(strict_types=1);

namespace CorporateAid\Platform\Services;

use CorporateAid\Core\Auth;
use CorporateAid\Core\Database;
use CorporateAid\Core\PDOParams;
use CorporateAid\Core\TenantContext;

/**
 * Aggregates dashboard content for the current user.
 * Keeps queries centralised and paginated — no view-layer SQL.
 */
final class DashboardService
{
    public function __construct(
        private Database $db,
        private TenantContext $tenant,
        private ?Auth $auth = null
    ) {
    }

    /** @return array<int, array<string,mixed>> */
    public function upcomingVacancyDeadlines(int $limit = 5): array
    {
        return $this->db->select(
            "SELECT v.`id`, v.`code`, v.`title`, v.`target_hire_date`, v.`status`,
                    o.`name` AS org_name, o.`code` AS org_code,
                    DATEDIFF(v.`target_hire_date`, CURDATE()) AS days_until
             FROM `recruit_vacancies` v
             JOIN `recruit_client_organisations` o ON o.`id` = v.`client_organisation_id`
             WHERE v.`tenant_id` = ?
               AND v.`deleted_at` IS NULL
               AND v.`target_hire_date` IS NOT NULL
               AND v.`status` NOT IN ('filled','cancelled','closed')
               AND v.`target_hire_date` >= CURDATE()
               AND v.`target_hire_date` <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
             ORDER BY v.`target_hire_date` ASC
             LIMIT {$limit}",
            PDOParams::positional([$this->tenant->id()])
        );
    }

    /** @return array<int, array<string,mixed>> */
    public function agingVacancies(int $limit = 5): array
    {
        return $this->db->select(
            "SELECT v.`id`, v.`code`, v.`title`, v.`opening_date`, v.`status`,
                    o.`name` AS org_name, o.`code` AS org_code,
                    DATEDIFF(CURDATE(), v.`opening_date`) AS days_open
             FROM `recruit_vacancies` v
             JOIN `recruit_client_organisations` o ON o.`id` = v.`client_organisation_id`
             WHERE v.`tenant_id` = ?
               AND v.`deleted_at` IS NULL
               AND v.`opening_date` IS NOT NULL
               AND v.`status` NOT IN ('filled','cancelled','closed','draft')
               AND DATEDIFF(CURDATE(), v.`opening_date`) > 30
             ORDER BY v.`opening_date` ASC
             LIMIT {$limit}",
            PDOParams::positional([$this->tenant->id()])
        );
    }

    /** @return array<int, array<string,mixed>> */
    public function engagementsNearCapacity(int $limit = 5): array
    {
        return $this->db->select(
            "SELECT e.`id`, e.`code`, e.`name`, e.`agreed_active_role_capacity`,
                    o.`name` AS org_name, o.`code` AS org_code,
                    (SELECT COUNT(*) FROM `recruit_vacancies` v
                     WHERE v.`engagement_id` = e.`id`
                       AND v.`tenant_id` = e.`tenant_id`
                       AND v.`status` NOT IN ('draft','cancelled','closed')
                       AND v.`deleted_at` IS NULL) AS used_capacity
             FROM `recruit_engagements` e
             JOIN `recruit_client_organisations` o ON o.`id` = e.`client_organisation_id`
             WHERE e.`tenant_id` = ?
               AND e.`deleted_at` IS NULL
               AND e.`status` = 'active'
             HAVING used_capacity > 0
                AND used_capacity >= (e.`agreed_active_role_capacity` * 0.8)
             ORDER BY (used_capacity / e.`agreed_active_role_capacity`) DESC
             LIMIT {$limit}",
            PDOParams::positional([$this->tenant->id()])
        );
    }

    /** @return array<int, array<string,mixed>> */
    public function recentActivity(int $limit = 8): array
    {
        $userId = $this->auth?->user()?->id;
        if ($userId === null) return [];

        return $this->db->select(
            "SELECT a.`id`, a.`action`, a.`module`, a.`description`, a.`created_at`
             FROM `core_activities` a
             WHERE a.`user_id` = ?
             ORDER BY a.`created_at` DESC
             LIMIT {$limit}",
            PDOParams::positional([$userId])
        );
    }

    /** @return array<string, int> */
    public function summaryCounts(): array
    {
        $tenantId = $this->tenant->id();
        return [
            'organisations' => $this->db->scalarInt("SELECT COUNT(*) FROM `recruit_client_organisations` WHERE `tenant_id` = ? AND `deleted_at` IS NULL", PDOParams::positional([$tenantId])),
            'contacts'      => $this->db->scalarInt("SELECT COUNT(*) FROM `recruit_client_contacts` WHERE `tenant_id` = ? AND `deleted_at` IS NULL", PDOParams::positional([$tenantId])),
            'engagements'   => $this->db->scalarInt("SELECT COUNT(*) FROM `recruit_engagements` WHERE `tenant_id` = ? AND `deleted_at` IS NULL", PDOParams::positional([$tenantId])),
            'vacancies'     => $this->db->scalarInt("SELECT COUNT(*) FROM `recruit_vacancies` WHERE `tenant_id` = ? AND `deleted_at` IS NULL", PDOParams::positional([$tenantId])),
        ];
    }

    /** @return array<string, mixed> */
    public function forCurrentUser(): array
    {
        return [
            'summary'         => $this->summaryCounts(),
            'deadlines'       => $this->upcomingVacancyDeadlines(5),
            'aging'           => $this->agingVacancies(5),
            'capacityAlerts'  => $this->engagementsNearCapacity(5),
            'recentActivity'  => $this->recentActivity(8),
        ];
    }
}

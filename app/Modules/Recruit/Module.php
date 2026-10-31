<?php
declare(strict_types=1);

namespace CorporateAid\Modules\Recruit;

use CorporateAid\Core\Container;
use CorporateAid\Core\Database;
use CorporateAid\Core\PDOParams;
use CorporateAid\Platform\Modules\Contracts\ModuleContract;

final class Module implements ModuleContract
{
    public function getKey(): string { return 'recruit'; }
    public function getName(): string { return 'Recruit Aid'; }
    public function getVersion(): string { return '0.6.0-alpha'; }
    public function getMigrations(): array { return []; }
    public function getPermissions(): array { return []; }
    public function getEvents(): array { return []; }
    
    public function getDashboardMetrics(int $tenantId, Container $c): array
    {
        /** @var Database $db */
        $db = $c->get(Database::class);
        
        $candidates = $db->scalarInt("SELECT COUNT(*) FROM `recruit_candidates` WHERE `tenant_id` = ? AND `deleted_at` IS NULL", PDOParams::positional([$tenantId]));
        $vacancies = $db->scalarInt("SELECT COUNT(*) FROM `recruit_vacancies` WHERE `tenant_id` = ? AND `deleted_at` IS NULL", PDOParams::positional([$tenantId]));
        $applications = $db->scalarInt("SELECT COUNT(*) FROM `recruit_applications` WHERE `tenant_id` = ?", PDOParams::positional([$tenantId]));

        return [
            'Total Candidates' => $candidates,
            'Active Vacancies' => $vacancies,
            'Applications'     => $applications,
        ];
    }
    
    public function register(Container $c): void {}
}

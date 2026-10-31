<?php
declare(strict_types=1);

namespace CorporateAid\Modules\HR;

use CorporateAid\Core\Container;
use CorporateAid\Core\Database;
use CorporateAid\Core\PDOParams;
use CorporateAid\Platform\Modules\Contracts\ModuleContract;
use CorporateAid\Platform\Modules\EventBus;
use CorporateAid\Platform\Modules\Events\PersonHiredEvent;
use CorporateAid\Modules\HR\Handlers\PersonHiredHandler;

final class Module implements ModuleContract
{
    public function getKey(): string { return 'hr'; }
    public function getName(): string { return 'HR Aid'; }
    public function getVersion(): string { return '0.1.0-alpha'; }
    
    /** @return string[] */
    public function getMigrations(): array { return ['0043_create_hr_employees.sql']; }
    
    /** @return string[] */
    public function getPermissions(): array { return []; }
    
    /** @return string[] */
    public function getEvents(): array { return [PersonHiredEvent::TYPE]; }
    
    public function getDashboardMetrics(int $tenantId, Container $c): array
    {
        /** @var Database $db */
        $db = $c->get(Database::class);
        $employees = $db->scalarInt(
            "SELECT COUNT(*) FROM `hr_employees` WHERE `tenant_id` = ? AND `status` = 'active'", 
            PDOParams::positional([$tenantId])
        );
        return ['Active Employees' => $employees];
    }
    
    public function register(Container $c): void
    {
        /** @var EventBus $bus */
        $bus = $c->get(EventBus::class);
        $handler = new PersonHiredHandler($c->get(Database::class));
        
        $bus->subscribe(PersonHiredEvent::TYPE, $handler);
    }
}

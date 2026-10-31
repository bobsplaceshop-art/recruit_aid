<?php
declare(strict_types=1);

namespace CorporateAid\Modules\HR;

use CorporateAid\Core\Container;
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
    
    public function register(Container $c): void
    {
        /** @var EventBus $bus */
        $bus = $c->get(EventBus::class);
        $handler = new PersonHiredHandler($c->get(\CorporateAid\Core\Database::class));
        
        $bus->subscribe(PersonHiredEvent::TYPE, $handler);
    }
}

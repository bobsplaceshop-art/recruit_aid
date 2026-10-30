<?php
declare(strict_types=1);

namespace CorporateAid\Modules\Recruit;

use CorporateAid\Core\Container;
use CorporateAid\Platform\Modules\Contracts\ModuleContract;

final class Module implements ModuleContract
{
    public function getKey(): string { return 'recruit'; }
    public function getName(): string { return 'Recruit Aid'; }
    public function getVersion(): string { return '0.6.0-alpha'; }
    public function getMigrations(): array { return []; }
    public function getPermissions(): array { return []; }
    public function getEvents(): array { return []; }
    
    public function register(Container $c): void
    {
        // Module-specific bindings go here in future batches.
    }
}

<?php
declare(strict_types=1);

namespace CorporateAid\Platform\Modules\Contracts;

interface ModuleContract
{
    public function getKey(): string;
    public function getName(): string;
    public function getVersion(): string;
    
    /** @return string[] */
    public function getMigrations(): array;
    
    /** @return string[] */
    public function getPermissions(): array;
    
    /** @return string[] */
    public function getEvents(): array;
    
    /**
     * Returns aggregated metrics for the CAS Dashboard.
     * @return array<string, int|string>
     */
    public function getDashboardMetrics(int $tenantId, \CorporateAid\Core\Container $c): array;

    public function register(\CorporateAid\Core\Container $c): void;
}

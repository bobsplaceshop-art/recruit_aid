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
     * Structured permission definitions for the RBAC registry.
     * Each definition MUST declare the sensitivity class so the
     * AuthorizationService knows how to evaluate it.
     *
     * @return array<int, array{
     *   slug:        string,
     *   name:        string,
     *   description: string,
     *   sensitivity: 'public'|'sensitive'|'restricted'
     * }>
     */
    public function getPermissionDefinitions(): array;
    
    public function getDashboardMetrics(int $tenantId, \CorporateAid\Core\Container $c): array;

    public function register(\CorporateAid\Core\Container $c): void;
}
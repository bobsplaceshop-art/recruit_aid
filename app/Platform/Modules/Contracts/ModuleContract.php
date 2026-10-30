<?php
declare(strict_types=1);

namespace CorporateAid\Platform\Modules\Contracts;

interface ModuleContract
{
    public function getKey(): string;
    public function getName(): string;
    public function getVersion(): string;
    public function getMigrations(): array;
    public function getPermissions(): array;
    public function getEvents(): array;
    public function register(\CorporateAid\Core\Container $c): void;
}

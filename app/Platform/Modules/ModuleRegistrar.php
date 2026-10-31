<?php
declare(strict_types=1);

namespace CorporateAid\Platform\Modules;

use CorporateAid\Core\Container;
use CorporateAid\Core\Database;
use CorporateAid\Core\PDOParams;
use CorporateAid\Platform\Modules\Contracts\ModuleContract;
use RuntimeException;

final class ModuleRegistrar
{
    /** @var array<string, ModuleContract> */
    private static array $modules = [];

    public static function boot(Container $c): void
    {
        $modulesDir = dirname(__DIR__, 3) . '/app/Modules';
        if (!is_dir($modulesDir)) {
            return;
        }

        $dirs = scandir($modulesDir);
        if ($dirs === false) return;

        $db = $c->get(Database::class);

        foreach ($dirs as $dir) {
            if ($dir === '.' || $dir === '..') continue;
            
            $class = "CorporateAid\\Modules\\{$dir}\\Module";
            if (!class_exists($class)) continue;

            /** @var ModuleContract $module */
            $module = new $class();
            
            self::$modules[$module->getKey()] = $module;

            $db->execute(
                "INSERT IGNORE INTO `core_modules` (`module_key`, `name`, `current_version`, `is_core`) VALUES (?, ?, ?, 1)",
                PDOParams::positional([$module->getKey(), $module->getName(), $module->getVersion()])
            );

            $db->execute(
                "INSERT INTO `core_module_manifests` 
                 (`module_key`, `version`, `migrations`, `permissions`, `events`, `dependencies`) 
                 VALUES (?, ?, ?, ?, ?, ?) 
                 ON DUPLICATE KEY UPDATE 
                    `version` = VALUES(`version`), 
                    `migrations` = VALUES(`migrations`), 
                    `permissions` = VALUES(`permissions`), 
                    `events` = VALUES(`events`), 
                    `dependencies` = VALUES(`dependencies`)",
                PDOParams::positional([
                    $module->getKey(),
                    $module->getVersion(),
                    json_encode($module->getMigrations()),
                    json_encode($module->getPermissions()),
                    json_encode($module->getEvents()),
                    json_encode([])
                ])
            );

            $module->register($c);
        }
    }

    public static function get(string $key): ModuleContract
    {
        if (!isset(self::$modules[$key])) {
            throw new RuntimeException("Module '{$key}' not registered.");
        }
        return self::$modules[$key];
    }

    /** @return array<string, ModuleContract> */
    public static function all(): array
    {
        return self::$modules;
    }
}

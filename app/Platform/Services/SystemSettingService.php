<?php
declare(strict_types=1);

namespace CorporateAid\Platform\Services;

use CorporateAid\Core\Auth;
use CorporateAid\Core\Database;
use CorporateAid\Core\PDOParams;
use CorporateAid\Core\Row;
use CorporateAid\Platform\Support\ActivityLogger;
use CorporateAid\Platform\Support\Logger;

/**
 * Reads/writes system settings.
 * Typed accessors: string, int, bool, json.
 * Caches values per-request to avoid repeated DB hits.
 * Audits changes.
 */
final class SystemSettingService
{
    private static function toStr(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    private static function toInt(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private static function toBool(mixed $value): bool
    {
        return (bool) self::toInt($value);
    }

    /** @var array<string, mixed> per-request cache */
    private array $cache = [];

    private ?object $notifications = null;

    public function __construct(
        private Database $db,
        private ActivityLogger $activity,
        private ?Auth $auth = null
    ) {
    }

    public function setNotificationService(?object $notifications): void
    {
        $this->notifications = $notifications;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }

        $row = $this->db->selectOne(
            "SELECT `value`, `type` FROM `core_system_settings` WHERE `key_slug` = ? LIMIT 1",
            PDOParams::positional([$key])
        );

        if ($row === null) {
            $this->cache[$key] = $default;
            return $default;
        }

        $value = $this->cast($row['value'], Row::str($row, 'type', 'string'));
        $this->cache[$key] = $value;
        return $value;
    }

    public function getInt(string $key, int $default = 0): int
    {
        return self::toInt($this->get($key, $default));
    }

    public function getBool(string $key, bool $default = false): bool
    {
        return self::toBool($this->get($key, $default));
    }

    public function getString(string $key, string $default = ''): string
    {
        return self::toStr($this->get($key, $default));
    }

    /** @return array<string, mixed> */
    public function all(string $prefix = ''): array
    {
        $sql = "SELECT `key_slug`, `value`, `type` FROM `core_system_settings`"
             . ($prefix !== '' ? " WHERE `key_slug` LIKE ?" : "")
             . " ORDER BY `key_slug`";

        $rows = $prefix !== ''
            ? $this->db->select($sql, PDOParams::positional([$prefix . '%']))
            : $this->db->select($sql);

        $out = [];
        foreach ($rows as $r) {
            $out[Row::str($r, 'key_slug')] = $this->cast($r['value'], Row::str($r, 'type', 'string'));
        }
        return $out;
    }

    public function set(string $key, mixed $value, ?string $type = null): void
    {
        if (!preg_match('/^[a-z0-9._-]+$/i', $key)) {
            throw new \InvalidArgumentException("Invalid setting key: {$key}");
        }

        $existing = $this->db->selectOne(
            "SELECT `value`, `type` FROM `core_system_settings` WHERE `key_slug` = ? LIMIT 1",
            PDOParams::positional([$key])
        );

        $oldValue = $existing !== null
            ? $this->cast($existing['value'], Row::str($existing, 'type', 'string'))
            : null;
        $type = $type ?? ($existing !== null ? Row::str($existing, 'type', $this->inferType($value)) : $this->inferType($value));
        $storedValue = $this->serialize($value, (string) $type);

        $userId = $this->auth?->user()?->id;

        $this->db->execute(
            "INSERT INTO `core_system_settings` (`key_slug`, `value`, `type`, `updated_by`)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `type` = VALUES(`type`),
                 `updated_by` = VALUES(`updated_by`)",
            PDOParams::positional([$key, $storedValue, $type, $userId])
        );

        unset($this->cache[$key]);

        if ($oldValue !== $value) {
            $label = self::getHumanLabel($key);
            $oldDisplay = self::formatValue($key, $oldValue);
            $newDisplay = self::formatValue($key, $value);

            $this->activity->log(
                'settings.changed',
                'settings',
                "{$label} changed from {$oldDisplay} to {$newDisplay}",
                'setting',
                null,
                [
                    'key' => $key,
                    'old' => $oldValue,
                    'new' => $value,
                    'type' => $type,
                ]
            );

            if ($this->notifications !== null && method_exists($this->notifications, 'settingsChanged')) {
                try {
                    $this->notifications->settingsChanged($key, $oldValue, $value);
                } catch (\Throwable $e) {
                    Logger::warning('Setting change notification failed: ' . $e->getMessage());
                }
            }
        }
    }

    /** @param array<string, mixed> $values */
    public function setMany(array $values, ?string $type = null): int
    {
        $count = 0;
        foreach ($values as $key => $value) {
            try {
                $this->set((string) $key, $value, $type);
                $count++;
            } catch (\Throwable $e) {
                Logger::warning("Failed to save setting {$key}: " . $e->getMessage());
            }
        }
        return $count;
    }

    public function forget(string $key): void
    {
        $this->db->execute(
            "DELETE FROM `core_system_settings` WHERE `key_slug` = ?",
            PDOParams::positional([$key])
        );
        unset($this->cache[$key]);
    }

    private function cast(mixed $value, string $type): mixed
    {
        return match ($type) {
            'int'    => self::toInt($value),
            'bool'   => self::toBool($value),
            'json'   => json_decode(self::toStr($value), true) ?: [],
            'secret' => self::toStr($value),
            default  => self::toStr($value),
        };
    }

    private function serialize(mixed $value, string $type): string
    {
        return match ($type) {
            'int'   => (string) self::toInt($value),
            'bool'  => !empty($value) ? '1' : '0',
            'json'  => json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}',
            default => self::toStr($value),
        };
    }

    private function inferType(mixed $value): string
    {
        return match (true) {
            is_bool($value)  => 'bool',
            is_int($value)   => 'int',
            is_array($value) => 'json',
            default          => 'string',
        };
    }

    public static function getHumanLabel(string $key): string
    {
        static $labels = [
            'security.login.max_attempts'                    => 'Max login attempts',
            'security.login.lockout_minutes'                 => 'Login lockout duration',
            'security.password_reset.max_attempts'           => 'Password reset request limit',
            'security.password_reset.window_minutes'         => 'Password reset request window',
            'security.password_reset.submit_max_attempts'    => 'Password reset submit limit',
            'security.password_reset.submit_window_minutes'  => 'Password reset submit window',
            'security.password.min_length'                   => 'Password minimum length',
            'security.password.min_length_relaxed'           => 'Password minimum length (relaxed)',
            'security.password.require_classes'              => 'Required character classes',
            'security.session.idle_timeout'                  => 'Session idle timeout',
            'security.session.reauth_window_minutes'         => 'Re-authentication window',
            'security.confirm_password.max_attempts'         => 'Password confirmation attempt limit',
            'security.confirm_password.window_minutes'       => 'Password confirmation window',
            'api.enabled'                                    => 'API access',
            'webhooks.enabled'                               => 'Webhook delivery',
        ];

        if (isset($labels[$key])) {
            return $labels[$key];
        }

        $parts = explode('.', $key);
        return ucwords(str_replace('_', ' ', end($parts)));
    }

    public static function formatValue(string $key, mixed $value): string
    {
        if ($value === null) return '(not set)';
        if (is_bool($value)) return $value ? 'Enabled' : 'Disabled';

        $secondsKeys = ['security.session.idle_timeout'];
        if (in_array($key, $secondsKeys, true)) {
            return self::humanDuration(self::toInt($value));
        }

        $minutesKeys = [
            'security.login.lockout_minutes',
            'security.password_reset.window_minutes',
            'security.password_reset.submit_window_minutes',
            'security.session.reauth_window_minutes',
            'security.confirm_password.window_minutes',
        ];
        if (in_array($key, $minutesKeys, true)) {
            return self::humanMinutes(self::toInt($value));
        }

        $countKeys = [
            'security.login.max_attempts',
            'security.password_reset.max_attempts',
            'security.password_reset.submit_max_attempts',
            'security.password.require_classes',
            'security.confirm_password.max_attempts',
        ];
        if (in_array($key, $countKeys, true)) {
            $n = self::toInt($value);
            return $n . ($n === 1 ? ' attempt' : ' attempts');
        }

        if (str_contains($key, 'min_length')) {
            return self::toInt($value) . ' characters';
        }

        if (is_array($value)) return json_encode($value) ?: '';
        return self::toStr($value);
    }

    private static function humanDuration(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds . ' second' . ($seconds === 1 ? '' : 's');
        }

        if ($seconds < 3600) {
            $minutes = intdiv($seconds, 60);
            $remainder = $seconds % 60;
            if ($remainder === 0) {
                return $minutes . ' minute' . ($minutes === 1 ? '' : 's');
            }
            return $minutes . 'm ' . $remainder . 's';
        }

        $hours = intdiv($seconds, 3600);
        $remainder = $seconds % 3600;
        if ($remainder === 0) {
            return $hours . ' hour' . ($hours === 1 ? '' : 's');
        }
        $minutes = intdiv($remainder, 60);
        return $hours . 'h ' . $minutes . 'm';
    }

    private static function humanMinutes(int $minutes): string
    {
        if ($minutes < 60) {
            return $minutes . ' minute' . ($minutes === 1 ? '' : 's');
        }

        if ($minutes < 1440) {
            $hours = intdiv($minutes, 60);
            $remainder = $minutes % 60;
            if ($remainder === 0) {
                return $hours . ' hour' . ($hours === 1 ? '' : 's');
            }
            return $hours . 'h ' . $remainder . 'm';
        }

        $days = intdiv($minutes, 1440);
        $remainder = $minutes % 1440;
        if ($remainder === 0) {
            return $days . ' day' . ($days === 1 ? '' : 's');
        }
        $hours = intdiv($remainder, 60);
        return $days . 'd ' . $hours . 'h';
    }
}

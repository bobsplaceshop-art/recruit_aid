<?php
declare(strict_types=1);

namespace CorporateAid\Platform\Services;

use CorporateAid\Core\Auth;
use CorporateAid\Core\Database;
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

    // ---------- scalar coercion helpers ----------
    // These are what PHPStan Level 9 wants: explicit proof that a mixed
    // value is safe to cast. Each helper checks the type first. This
    // replaces the previous "(int) $value" / "(string) $value" pattern,
    // which triggered "Cannot cast mixed to int/string" errors.

    private static function toStr(mixed $value): string
    {
        if (is_scalar($value)) {
            return (string) $value;
        }
        return '';
    }

    private static function toInt(mixed $value): int
    {
        if (is_numeric($value)) {
            return (int) $value;
        }
        return 0;
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

    /**
     * Setter injection for NotificationService to avoid a circular
     * dependency in the container (NotificationService itself uses Auth).
     */
    public function setNotificationService(?object $notifications): void
    {
        $this->notifications = $notifications;
    }

    /**
     * Read a setting with the given default if not present.
     * Returns typed value based on the stored `type` column.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }

        $row = $this->db->selectOne(
            "SELECT `value`, `type` FROM `core_system_settings` WHERE `key_slug` = ? LIMIT 1",
            [$key]
        );

        if ($row === null) {
            $this->cache[$key] = $default;
            return $default;
        }

        $value = $this->cast($row['value'], (string) $row['type']);
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

    /**
     * Get all settings whose key starts with $prefix.
     * Returns ['key_slug' => typedValue].
     *
     * @return array<string, mixed>
     */
    public function all(string $prefix = ''): array
    {
        $rows = $this->db->select(
            "SELECT `key_slug`, `value`, `type` FROM `core_system_settings`" .
            ($prefix !== '' ? " WHERE `key_slug` LIKE ?" : "") .
            " ORDER BY `key_slug`",
            $prefix !== '' ? [$prefix . '%'] : []
        );

        $out = [];
        foreach ($rows as $r) {
            $out[$r['key_slug']] = $this->cast($r['value'], (string) $r['type']);
        }
        return $out;
    }

    /**
     * Save a setting. Audits the change with old and new values.
     * Throws if the key format is invalid.
     */
    public function set(string $key, mixed $value, ?string $type = null): void
    {
        if (!preg_match('/^[a-z0-9._-]+$/i', $key)) {
            throw new \InvalidArgumentException("Invalid setting key: {$key}");
        }

        $existing = $this->db->selectOne(
            "SELECT `value`, `type` FROM `core_system_settings` WHERE `key_slug` = ? LIMIT 1",
            [$key]
        );

        $oldValue = $existing ? $this->cast($existing['value'], (string) $existing['type']) : null;
        $type = $type ?? ($existing['type'] ?? $this->inferType($value));
        $storedValue = $this->serialize($value, $type);

        $userId = $this->auth?->user()?->id;

        $this->db->execute(
            "INSERT INTO `core_system_settings` (`key_slug`, `value`, `type`, `updated_by`)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `type` = VALUES(`type`),
                 `updated_by` = VALUES(`updated_by`)",
            [$key, $storedValue, $type, $userId]
        );

        // Clear cache for this key
        unset($this->cache[$key]);

        // Audit only if the value actually changed
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

            // Notify super admins (best-effort; never breaks the save)
            if ($this->notifications !== null && method_exists($this->notifications, 'settingsChanged')) {
                try {
                    $this->notifications->settingsChanged($key, $oldValue, $value);
                } catch (\Throwable $e) {
                    Logger::warning('Setting change notification failed: ' . $e->getMessage());
                }
            }
        }
    }

    /**
     * Set multiple settings at once. Fails atomically per-key but
     * does not roll back on partial failure (settings are not a
     * transactional concern).
     *
     * @param array<string, mixed> $values
     */
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

    /**
     * Delete a setting.
     */
    public function forget(string $key): void
    {
        $this->db->execute("DELETE FROM `core_system_settings` WHERE `key_slug` = ?", [$key]);
        unset($this->cache[$key]);
    }

    // ---------- helpers ----------

    private function cast(mixed $value, string $type): mixed
    {
        return match ($type) {
            'int'    => self::toInt($value),
            'bool'   => self::toBool($value),
            'json'   => json_decode(self::toStr($value), true) ?: [],
            'secret' => self::toStr($value), // encryption handled at a higher layer, deferred
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

    // ============================================================
    // Human-readable labels and value formatting
    // ============================================================

    /**
     * Human-readable label for a setting key. Falls back to the key
     * with dots replaced by spaces if no label is defined.
     */
    public static function getHumanLabel(string $key): string
    {
        static $labels = [
            // Login
            'security.login.max_attempts'                    => 'Max login attempts',
            'security.login.lockout_minutes'                 => 'Login lockout duration',

            // Password reset
            'security.password_reset.max_attempts'           => 'Password reset request limit',
            'security.password_reset.window_minutes'         => 'Password reset request window',
            'security.password_reset.submit_max_attempts'    => 'Password reset submit limit',
            'security.password_reset.submit_window_minutes'  => 'Password reset submit window',

            // Password policy
            'security.password.min_length'                   => 'Password minimum length',
            'security.password.min_length_relaxed'           => 'Password minimum length (relaxed)',
            'security.password.require_classes'              => 'Required character classes',

            // Sessions
            'security.session.idle_timeout'                  => 'Session idle timeout',
            'security.session.reauth_window_minutes'         => 'Re-authentication window',

            // Confirm password
            'security.confirm_password.max_attempts'         => 'Password confirmation attempt limit',
            'security.confirm_password.window_minutes'       => 'Password confirmation window',

            // API
            'api.enabled'                                    => 'API access',
            'webhooks.enabled'                               => 'Webhook delivery',
        ];

        if (isset($labels[$key])) {
            return $labels[$key];
        }

        // Fallback: last segment, prettified
        $parts = explode('.', $key);
        return ucwords(str_replace('_', ' ', end($parts)));
    }

    /**
     * Format a value for human display. Knows about specific keys
     * that need special formatting (timeouts → "2 hours", etc).
     */
    public static function formatValue(string $key, mixed $value): string
    {
        if ($value === null) return '(not set)';
        if (is_bool($value)) return $value ? 'Enabled' : 'Disabled';

        // Seconds → duration
        $secondsKeys = [
            'security.session.idle_timeout',
        ];
        if (in_array($key, $secondsKeys, true)) {
            return self::humanDuration(self::toInt($value));
        }

        // Minutes → duration
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

        // Counts
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

        // Password length
        if (str_contains($key, 'min_length')) {
            return self::toInt($value) . ' characters';
        }

        // Default: raw value
        if (is_array($value)) return json_encode($value) ?: '';
        return self::toStr($value);
    }

    private static function humanDuration(int $seconds): string
    {
        // Always show the raw seconds when not an exact multiple of a bigger unit,
        // so audits capture changes like "180s → 200s" even if both round to 3 minutes.
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

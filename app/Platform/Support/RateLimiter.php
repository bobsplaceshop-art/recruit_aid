<?php
declare(strict_types=1);

namespace CorporateAid\Platform\Support;

use CorporateAid\Platform\Repositories\RateLimitRepository;
use CorporateAid\Platform\Services\SystemSettingService;

/**
 * Rate limiter service.
 *
 * Reads limits from SystemSettingService when available, using the
 * action name to derive the setting key prefix. Falls back to hardcoded
 * defaults if settings are missing.
 *
 * Actions and their setting prefixes:
 *   login              → security.login.*
 *   password_forgot    → security.password_reset.*  (request)
 *   password_reset     → security.password_reset.*  (submit)
 *   confirm_password   → security.confirm_password.*
 */
final class RateLimiter
{
    /** @var array<string, array{max_key:string, window_key:string, default_max:int, default_window:int}> */
    private const LIMITS = [
        'login' => [
            'max_key'      => 'security.login.max_attempts',
            'window_key'   => 'security.login.lockout_minutes',
            'default_max'  => 5,
            'default_window' => 15,
        ],
        'password_forgot' => [
            'max_key'      => 'security.password_reset.max_attempts',
            'window_key'   => 'security.password_reset.window_minutes',
            'default_max'  => 3,
            'default_window' => 60,
        ],
        'password_reset' => [
            'max_key'      => 'security.password_reset.submit_max_attempts',
            'window_key'   => 'security.password_reset.submit_window_minutes',
            'default_max'  => 5,
            'default_window' => 60,
        ],
        'confirm_password' => [
            'max_key'      => 'security.confirm_password.max_attempts',
            'window_key'   => 'security.confirm_password.window_minutes',
            'default_max'  => 3,
            'default_window' => 15,
        ],
    ];

    public function __construct(
        private RateLimitRepository $repo,
        private ?SystemSettingService $settings = null
    ) {
    }

    /**
     * Increment attempt count and return the new value.
     * Window length is derived from settings (falling back to defaults).
     */
    public function hit(string $action, string $ip, int $windowMinutes = 0): int
    {
        if ($windowMinutes <= 0) {
            $windowMinutes = $this->windowFor($action);
        }

        $key = $this->bucketKey($action, $ip);
        return $this->repo->hit($key, $windowMinutes);
    }

    /**
     * Check if the action is allowed given the current settings.
     * The explicit args are fallbacks — settings take precedence.
     */
    /**
     * @return array{allowed: bool, attempts: int, max: int, seconds_until_reset: int}
     */
    public function check(string $action, string $ip, int $maxAttempts = 0, int $windowMinutes = 0): array
    {
        $maxAttempts = $maxAttempts > 0 ? $maxAttempts : $this->maxFor($action);
        $windowMinutes = $windowMinutes > 0 ? $windowMinutes : $this->windowFor($action);

        $key = $this->bucketKey($action, $ip);
        $attempts = $this->repo->currentAttempts($key);
        $seconds = $this->repo->secondsUntilReset($key);

        return [
            'allowed'             => $attempts < $maxAttempts,
            'attempts'            => $attempts,
            'max'                 => $maxAttempts,
            'seconds_until_reset' => $seconds,
        ];
    }

    public function reset(string $action, string $ip): void
    {
        $this->repo->clear($this->bucketKey($action, $ip));
    }

    public function humanWait(int $seconds): string
    {
        if ($seconds <= 0) return 'a moment';
        if ($seconds < 60) return $seconds . ' second' . ($seconds === 1 ? '' : 's');
        $minutes = (int) ceil($seconds / 60);
        return $minutes . ' minute' . ($minutes === 1 ? '' : 's');
    }

    // ---------- helpers ----------

    private function bucketKey(string $action, string $ip): string
    {
        return $action . ':' . $ip;
    }

    private function maxFor(string $action): int
    {
        $def = self::LIMITS[$action] ?? null;
        if ($def === null || $this->settings === null) {
            return $def['default_max'] ?? 5;
        }
        return $this->settings->getInt($def['max_key'], $def['default_max']);
    }

    private function windowFor(string $action): int
    {
        $def = self::LIMITS[$action] ?? null;
        if ($def === null || $this->settings === null) {
            return $def['default_window'] ?? 15;
        }
        return $this->settings->getInt($def['window_key'], $def['default_window']);
    }
}

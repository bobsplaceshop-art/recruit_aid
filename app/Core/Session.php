<?php
declare(strict_types=1);

namespace CorporateAid\Core;

/**
 * Secure session wrapper. Owns the session lifecycle.
 * All reads/writes to $_SESSION go through here.
 */
final class Session
{

    // ---------- scalar coercion helper (PHPStan Level 9) ----------

    private static function toInt(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private array $config;
    private bool $started = false;

    /** @var array<string, mixed> one-shot flash data for the next request */
    private array $flash = [];

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'name'              => 'recruitaid_session',
            'lifetime'          => 7200,
            'cookie_path'       => '/',
            'cookie_domain'     => '',
            'cookie_secure'     => false,
            'cookie_httponly'   => true,
            'cookie_samesite'   => 'Lax',
        ], $config);
    }

    public function start(): void
    {
        if ($this->started || session_status() === PHP_SESSION_ACTIVE) {
            $this->started = true;
            return;
        }

        $secure = (bool) $this->config['cookie_secure'];
        // Auto-upgrade to secure on HTTPS
        if (!$secure && !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            $secure = true;
        }

        session_name((string) $this->config['name']);
        session_set_cookie_params([
            'lifetime' => (int) $this->config['lifetime'],
            'path'     => (string) $this->config['cookie_path'],
            'domain'   => (string) $this->config['cookie_domain'],
            'secure'   => $secure,
            'httponly' => (bool) $this->config['cookie_httponly'],
            'samesite' => (string) $this->config['cookie_samesite'],
        ]);

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');

        session_start();
        $this->started = true;

        $this->enforceIdleTimeout();
        $this->rotateIfStale();
    }

    public function isStarted(): bool
    {
        return $this->started || session_status() === PHP_SESSION_ACTIVE;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $_SESSION);
    }

    public function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public function regenerate(bool $deleteOld = true): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id($deleteOld);
        }
    }

    public function destroy(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(
                    session_name(),
                    '',
                    time() - 42000,
                    $params['path'],
                    $params['domain'],
                    $params['secure'],
                    $params['httponly']
                );
            }
            session_destroy();
        }
        $this->started = false;
    }

    // ---------- Flash messages (survive one redirect) ----------

    public function flash(string $key, mixed $value): void
    {
        $flash = isset($_SESSION['_flash']) && is_array($_SESSION['_flash']) ? $_SESSION['_flash'] : [];
        $flash[$key] = $value;
        $_SESSION['_flash'] = $flash;
    }

    public function getFlash(string $key, mixed $default = null): mixed
    {
        $flash = isset($_SESSION['_flash']) && is_array($_SESSION['_flash']) ? $_SESSION['_flash'] : [];
        if (!array_key_exists($key, $flash)) {
            return $default;
        }
        $value = $flash[$key];
        unset($flash[$key]);
        $_SESSION['_flash'] = $flash;
        return $value;
    }

    public function hasFlash(string $key): bool
    {
        $flash = isset($_SESSION['_flash']) && is_array($_SESSION['_flash']) ? $_SESSION['_flash'] : [];
        return array_key_exists($key, $flash);
    }

    // ---------- Old input (after validation failure) ----------

    public function flashOldInput(array $input): void
    {
        unset($input['password'], $input['password_confirmation'], $input['_csrf']);
        $_SESSION['_old'] = $input;
    }

    public function old(string $key, mixed $default = ''): mixed
    {
        $old = isset($_SESSION['_old']) && is_array($_SESSION['_old']) ? $_SESSION['_old'] : [];
        return array_key_exists($key, $old) ? $old[$key] : $default;
    }

    public function clearOldInput(): void
    {
        unset($_SESSION['_old']);
    }

    // ---------- Idle timeout & session ID rotation ----------

    private function enforceIdleTimeout(): void
    {
        $lifetime = self::toInt($this->config['lifetime'] ?? 0);
        if ($lifetime <= 0) {
            return;
        }

        $last = self::toInt($_SESSION['_last_activity'] ?? 0);
        if ($last > 0 && (time() - $last) > $lifetime) {
            $this->destroy();
            $this->start();
        }
        $_SESSION['_last_activity'] = time();
    }

    private function rotateIfStale(): void
    {
        $interval = 300; // rotate session id every 5 minutes
        $last = self::toInt($_SESSION['_rotated_at'] ?? 0);
        if ($last > 0 && (time() - $last) > $interval) {
            $this->regenerate(true);
        }
        $_SESSION['_rotated_at'] = time();
    }

    // ---------- Re-authentication tracking ----------

    /**
     * Mark the current session as freshly re-authenticated.
     */
    public function markReauth(): void
    {
        $this->set('_reauth_at', time());
    }

    /**
     * Return the timestamp of the last re-auth (or 0 if never).
     */
    public function reauthAt(): int
    {
        return self::toInt($this->get('_reauth_at', 0));
    }

    /**
     * True if the user has re-authenticated within the given window.
     */
    public function hasRecentReauth(int $windowMinutes): bool
    {
        $at = $this->reauthAt();
        if ($at === 0) return false;
        return (time() - $at) < ($windowMinutes * 60);
    }

    /**
     * Clear re-auth state (called on logout).
     */
    public function clearReauth(): void
    {
        $this->forget('_reauth_at');
    }
}

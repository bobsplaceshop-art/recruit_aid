<?php
declare(strict_types=1);

namespace CorporateAid\Platform\Support;

final class PasswordPolicy
{
    public const MIN_LENGTH = 12;
    public const MIN_LENGTH_RELAXED = 10;
    public const MIN_CLASSES_RELAXED = 3; // of 4

    /**
     * Validate a proposed password against the policy.
     * Returns a list of error messages (empty = valid).
     *
     * @return string[]
     */
    public static function validate(string $password, ?string $email = null, ?string $name = null): array
    {
        $errors = [];

        // 1. Length
        $len = strlen($password);
        if ($len < self::MIN_LENGTH_RELAXED) {
            $errors[] = 'Password must be at least ' . self::MIN_LENGTH_RELAXED . ' characters.';
            return $errors; // early return, no point checking further
        }

        // 2. Class diversity
        $classes = self::classesPresent($password);
        $hasEnoughClasses = count($classes) >= self::MIN_CLASSES_RELAXED;
        $hasFullLength = $len >= self::MIN_LENGTH;

        if (!$hasFullLength && !$hasEnoughClasses) {
            $errors[] = 'Password must either be at least ' . self::MIN_LENGTH . ' characters, '
                . 'or at least ' . self::MIN_LENGTH_RELAXED . ' characters with '
                . self::MIN_CLASSES_RELAXED . ' of 4 character types (uppercase, lowercase, digit, symbol).';
        }

        // 3. Common password blacklist
        if (self::isCommon($password)) {
            $errors[] = 'That password is too common. Please choose something less predictable.';
        }

        // 4. Contains email local-part
        if ($email !== null && $email !== '') {
            $local = strtolower(explode('@', $email)[0] ?? '');
            if ($local !== '' && strlen($local) >= 3 && stripos($password, $local) !== false) {
                $errors[] = 'Password must not contain your email address.';
            }
        }

        // 5. Contains name (first or last)
        if ($name !== null && $name !== '') {
            $parts = preg_split('/\s+/', trim($name)) ?: [];
            foreach ($parts as $part) {
                if (strlen($part) >= 3 && stripos($password, $part) !== false) {
                    $errors[] = 'Password must not contain your name.';
                    break;
                }
            }
        }

        return $errors;
    }

    /**
     * Compute a score from 0 to 100 for strength meter display.
     */
    public static function score(string $password): int
    {
        $score = 0;
        $len = strlen($password);

        // Length component (up to 50)
        $score += min(50, $len * 3);

        // Class diversity (up to 30)
        $score += count(self::classesPresent($password)) * 8;

        // Bonus for length beyond 12 (up to 20)
        if ($len > 12) {
            $score += min(20, ($len - 12) * 2);
        }

        // Penalties
        if (self::isCommon($password)) $score -= 40;
        if (preg_match('/(.)\1{2,}/', $password)) $score -= 10; // repeated chars
        if (preg_match('/(012|123|234|345|456|567|678|789|890)/', $password)) $score -= 10; // sequences

        return max(0, min(100, $score));
    }

    /**
     * Human label for a strength score.
     */
    public static function scoreLabel(int $score): string
    {
        if ($score < 30) return 'Weak';
        if ($score < 60) return 'Fair';
        if ($score < 80) return 'Good';
        return 'Strong';
    }

    public static function scoreColor(int $score): string
    {
        if ($score < 30) return '#dc2626'; // red
        if ($score < 60) return '#f59e0b'; // amber
        if ($score < 80) return '#10b981'; // green
        return '#059669'; // darker green
    }

    // ---------- helpers ----------

    private static function classesPresent(string $password): array
    {
        $classes = [];
        if (preg_match('/[a-z]/', $password)) $classes[] = 'lower';
        if (preg_match('/[A-Z]/', $password)) $classes[] = 'upper';
        if (preg_match('/[0-9]/', $password)) $classes[] = 'digit';
        if (preg_match('/[^A-Za-z0-9]/', $password)) $classes[] = 'symbol';
        return $classes;
    }

    private static function isCommon(string $password): bool
    {
        static $list = null;
        if ($list === null) {
            $path = base_path('storage/config/common-passwords.txt');
            $list = [];
            if (is_file($path)) {
                $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                if (is_array($lines)) {
                    foreach ($lines as $line) {
                        if (is_string($line)) {
                            $list[] = strtolower(trim($line));
                        }
                    }
                }
            }
        }
        if (empty($list)) return false;

        $lower = strtolower($password);
        return in_array($lower, $list, true);
    }
}

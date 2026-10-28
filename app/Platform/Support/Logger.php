<?php

declare(strict_types=1);

namespace CorporateAid\Platform\Support;

use Throwable;

/**
 * Simple file logger. Writes to storage/logs/.
 * No external dependencies.
 *
 * Exceptions may be logged with a short reference ID (e.g. "ERR-4a2f9c")
 * that is also shown to the user on the error page. Support can grep
 * the log for that ID to find the exact stack trace without needing the
 * user to describe what they were doing.
 */
final class Logger
{
    private static ?string $basePath = null;

    public static function setBasePath(string $path): void
    {
        self::$basePath = rtrim($path, '/\\');
    }

    /** @param array<string, mixed> $context */
    public static function log(string $level, string $message, array $context = []): void
    {
        $dir = self::logDir();
        if ($dir === null) {
            return; // fail silently; logging should never break the app
        }

        $file = $dir . '/app.log';
        $ts = date('Y-m-d H:i:s');
        $line = sprintf('[%s] %s: %s', $ts, strtoupper($level), $message);

        if (!empty($context)) {
            $line .= ' ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        $line .= PHP_EOL;

        @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
    }

    /** @param array<string, mixed> $context */
    public static function info(string $message, array $context = []): void
    {
        self::log('info', $message, $context);
    }

    /** @param array<string, mixed> $context */
    public static function warning(string $message, array $context = []): void
    {
        self::log('warning', $message, $context);
    }

    /** @param array<string, mixed> $context */
    public static function error(string $message, array $context = []): void
    {
        self::log('error', $message, $context);
    }

    /**
     * Log an exception. If a $reference is provided, it is prepended to
     * the message and included in the context so both `grep <ref>` and
     * structured log tooling can find it.
     */
    public static function exception(Throwable $e, ?string $reference = null): void
    {
        $context = [
            'file'  => $e->getFile(),
            'line'  => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ];

        if ($reference !== null) {
            $context['ref'] = $reference;
        }

        $message = $reference !== null
            ? "[{$reference}] " . $e->getMessage()
            : $e->getMessage();

        self::log('exception', $message, $context);
    }

    private static function logDir(): ?string
    {
        if (self::$basePath === null) {
            return null;
        }
        $dir = self::$basePath . '/storage/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        return is_writable($dir) ? $dir : null;
    }
}

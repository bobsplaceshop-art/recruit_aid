<?php
declare(strict_types=1);

namespace CorporateAid\Platform\Support;

/**
 * Request-scoped holder for entity context.
 *
 * Controllers call AuditContext::set('candidate', 42) after
 * creating or updating a record. AuditLoggerMiddleware reads it
 * at the end of the request and writes entity_type + entity_id
 * to the audit_logs row.
 *
 * Reset at the start of each request by the middleware so state
 * cannot leak between requests in long-running workers (cron,
 * queue processors).
 */
final class AuditContext
{
    private static ?string $entityType = null;
    private static ?int $entityId = null;

    public static function set(string $type, ?int $id = null): void
    {
        self::$entityType = $type;
        self::$entityId = $id;
    }

    public static function clear(): void
    {
        self::$entityType = null;
        self::$entityId = null;
    }

    public static function entityType(): ?string
    {
        return self::$entityType;
    }

    public static function entityId(): ?int
    {
        return self::$entityId;
    }
}

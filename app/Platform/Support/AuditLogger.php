<?php
declare(strict_types=1);

namespace CorporateAid\Platform\Support;

use CorporateAid\Core\Database;

/**
 * Writes an immutable audit_logs row for a state-changing request.
 *
 * Never called by controllers directly — AuditLoggerMiddleware
 * invokes it. Controllers set entity context via AuditContext.
 *
 * Failures are swallowed (with a warning) so a broken audit
 * insert can never break a user-facing request.
 */
final class AuditLogger
{
    public function __construct(private Database $db) {}

    /**
     * @param string      $method          POST | PUT | PATCH | DELETE
     * @param string      $path            Request path (may include query string)
     * @param int|null    $userId          Authenticated user ID, or null
     * @param string|null $ipAddress       Client IP (IPv4 or IPv6)
     * @param string|null $userAgent       Raw User-Agent header (truncated to 255)
     * @param string|null $requestHash     SHA-256 of the request payload
     * @param int|null    $responseStatus  HTTP response code
     * @param string|null $entityType      From AuditContext
     * @param int|null    $entityId        From AuditContext
     */
    public function log(
        string $method,
        string $path,
        ?int $userId,
        ?string $ipAddress,
        ?string $userAgent,
        ?string $requestHash,
        ?int $responseStatus,
        ?string $entityType,
        ?int $entityId
    ): void {
        try {
            $this->db->execute(
                "INSERT INTO `core_audit_logs`
                    (`user_id`, `method`, `path`, `ip_address`, `user_agent`,
                     `request_hash`, `response_status`, `entity_type`, `entity_id`)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $userId,
                    $method,
                    $path,
                    $ipAddress,
                    $userAgent !== null ? substr($userAgent, 0, 255) : null,
                    $requestHash,
                    $responseStatus,
                    $entityType,
                    $entityId,
                ]
            );
        } catch (\Throwable $e) {
            Logger::warning('AuditLogger insert failed: ' . $e->getMessage(), [
                'method' => $method,
                'path'   => $path,
            ]);
        }
    }
}

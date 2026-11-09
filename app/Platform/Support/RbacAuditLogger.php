<?php
declare(strict_types=1);

namespace CorporateAid\Platform\Support;

use CorporateAid\Core\Database;
use CorporateAid\Core\PDOParams;

/**
 * Writes immutable audit rows for RBAC mutations.
 *
 * Companion to ActivityLogger (business timeline) and AuditLogger (HTTP
 * audit trail). This one records security-critical RBAC events:
 * context activation, delegation grants, revocations, cancellations.
 *
 * The table core_rbac_audit_logs is created by migration 0046b_fix_rbac_schema
 * (the migration source has a comment typo naming the singular form; the
 * actual DDL and DB use the plural).
 *
 * Append-only enforcement is application-level: this class never issues
 * UPDATE or DELETE. Failures are swallowed with a warning — a broken audit
 * insert must never break a user-facing action.
 */
final class RbacAuditLogger
{
    public function __construct(private Database $db) {}

    /**
     * @param int         $tenantId    The tenant the action affects.
     * @param string      $action      e.g. 'context.switch', 'delegation.grant'
     * @param int|null    $actorId     The acting user (null if system action).
     * @param string|null $targetType  e.g. 'support_delegation'
     * @param int|null    $targetId    The id of the target row.
     * @param string|null $moduleKey   The module the action applies to, if any.
     * @param array<string, mixed>|null $before  State before (optional).
     * @param array<string, mixed>|null $after   State after (optional).
     */
    public function log(
        int $tenantId,
        string $action,
        ?int $actorId = null,
        ?string $targetType = null,
        ?int $targetId = null,
        ?string $moduleKey = null,
        ?array $before = null,
        ?array $after = null
    ): void {
        try {
            $ip = null;
            if (!empty($_SERVER['REMOTE_ADDR'])) {
                $remoteAddr = $_SERVER['REMOTE_ADDR'];
                if (is_string($remoteAddr) && strlen($remoteAddr) <= 45) {
                    $ip = $remoteAddr;
                }
            }

            $ua = null;
            if (!empty($_SERVER['HTTP_USER_AGENT'])) {
                $raw = $_SERVER['HTTP_USER_AGENT'];
                if (is_string($raw)) {
                    $ua = substr($raw, 0, 255);
                }
            }

            $this->db->execute(
                "INSERT INTO `core_rbac_audit_logs`
                    (`tenant_id`, `actor_id`, `action`, `target_type`, `target_id`,
                     `module_key`, `before_json`, `after_json`,
                     `ip_address`, `user_agent`, `created_at`)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
                PDOParams::positional([
                    $tenantId,
                    $actorId,
                    $action,
                    $targetType,
                    $targetId,
                    $moduleKey,
                    $before !== null ? json_encode($before, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null,
                    $after !== null ? json_encode($after, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null,
                    $ip,
                    $ua,
                ])
            );
        } catch (\Throwable $e) {
            Logger::warning('RbacAuditLogger insert failed: ' . $e->getMessage(), [
                'action' => $action,
                'actor'  => $actorId,
            ]);
        }
    }
}

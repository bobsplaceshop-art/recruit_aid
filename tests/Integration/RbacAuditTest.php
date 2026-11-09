<?php
declare(strict_types=1);

use CorporateAid\Core\App;
use CorporateAid\Core\Database;
use CorporateAid\Core\Row;
use CorporateAid\Platform\Support\RbacAuditLogger;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance criteria:
 *   RBAC-AUDIT-01: RbacAuditLogger.log() writes a row to core_rbac_audit_logs.
 *   RBAC-AUDIT-02: The row contains the expected tenant, actor, action.
 *   RBAC-AUDIT-03: Append-only — log() inserts, never updates or deletes.
 *   RBAC-AUDIT-04: Optional fields (target, before/after) are NULL when omitted.
 */
final class RbacAuditTest extends TestCase
{
    private static Database $db;
    private static RbacAuditLogger $audit;

    public static function setUpBeforeClass(): void
    {
        App::boot(dirname(__DIR__, 2));
        $c = App::instance()->container();
        self::$db    = $c->get(Database::class);
        self::$audit = $c->get(RbacAuditLogger::class);

        self::$db->beginTransaction();
    }

    public static function tearDownAfterClass(): void
    {
        self::$db->rollBack();
    }

    public function testAuditWritesExpectedFields(): void
    {
        self::$db->execute("DELETE FROM `core_rbac_audit_logs`");
        self::$audit->log(
            tenantId: 42,
            action: 'context.switch',
            actorId: 999,
            before: ['effective_tenant' => 1],
            after: ['effective_tenant' => 42]
        );

        $row = self::$db->selectOne(
            "SELECT * FROM `core_rbac_audit_logs` WHERE `action` = 'context.switch' ORDER BY `id` DESC LIMIT 1"
        );
        self::assertNotNull($row);
        self::assertSame(42, Row::int($row, 'tenant_id'));
        self::assertSame(999, Row::int($row, 'actor_id'));
        self::assertSame('context.switch', Row::str($row, 'action'));

        $after = json_decode(Row::str($row, 'after_json'), true);
        self::assertSame(42, $after['effective_tenant']);
    }

    public function testAuditOmitsOptionalFields(): void
    {
        self::$db->execute("DELETE FROM `core_rbac_audit_logs`");
        self::$audit->log(tenantId: 1, action: 'delegation.revoke', actorId: 998);

        $row = self::$db->selectOne(
            "SELECT * FROM `core_rbac_audit_logs` WHERE `action` = 'delegation.revoke' ORDER BY `id` DESC LIMIT 1"
        );
        self::assertNotNull($row);
        self::assertNull(Row::nullableStr($row, 'target_type'));
        self::assertNull(Row::nullableStr($row, 'before_json'));
        self::assertNull(Row::nullableStr($row, 'after_json'));
    }

    public function testAuditRowsAreAppendOnly(): void
    {
        self::$db->execute("DELETE FROM `core_rbac_audit_logs`");
        self::$audit->log(
            tenantId: 1,
            action: 'delegation.grant',
            actorId: 1,
            targetType: 'support_delegation',
            targetId: 99
        );

        $before = self::$db->scalarInt("SELECT COUNT(*) FROM `core_rbac_audit_logs`");
        self::$audit->log(
            tenantId: 1,
            action: 'delegation.grant',
            actorId: 2,
            targetType: 'support_delegation',
            targetId: 100
        );
        $after = self::$db->scalarInt("SELECT COUNT(*) FROM `core_rbac_audit_logs`");

        self::assertSame($before + 1, $after, 'log() appends, never replaces.');

        $first = self::$db->selectOne(
            "SELECT * FROM `core_rbac_audit_logs` WHERE `actor_id` = 1 ORDER BY `id` ASC LIMIT 1"
        );
        self::assertSame(99, Row::int($first, 'target_id'));
    }
}

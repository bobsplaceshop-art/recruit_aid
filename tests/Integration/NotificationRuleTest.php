<?php
declare(strict_types=1);

use CorporateAid\Core\App;
use CorporateAid\Core\Database;
use CorporateAid\Core\PDOParams;
use CorporateAid\Core\TenantContext;
use CorporateAid\Platform\Services\NotificationRuleService;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance criteria:
 *   NR-01: A notification rule created in tenant 1 is not returned by
 *          resolveRecipients() when the active tenant is tenant 2.
 */
final class NotificationRuleTest extends TestCase
{
    private static Database $db;
    private static TenantContext $tenant;
    private static NotificationRuleService $rules;
    private static int $originalTenantId;

    public static function setUpBeforeClass(): void
    {
        App::boot(dirname(__DIR__, 2));
        $c = App::instance()->container();
        self::$db     = $c->get(Database::class);
        self::$tenant = $c->get(TenantContext::class);
        self::$rules  = $c->get(NotificationRuleService::class);
        self::$originalTenantId = self::$tenant->id();
    }

    public static function tearDownAfterClass(): void
    {
        self::$tenant->set(self::$originalTenantId);
    }

    /** NR-01: Rules are isolated by tenant. */
    public function testRulesAreIsolatedByTenant(): void
    {
        $original = self::$tenant->id();
        self::$db->beginTransaction();
        try {
            $suffix = bin2hex(random_bytes(4));
            $event = 'candidate.created';

            // Insert one rule in tenant 1 for this event type.
            self::$db->execute(
                "INSERT INTO `core_notification_rules`
                    (`tenant_id`, `event_type`, `recipient_type`, `recipient_value`,
                     `channel_inapp`, `channel_email`, `is_system`, `is_active`, `created_by`)
                 VALUES (1, ?, 'role', 'admin', 1, 0, 0, 1, NULL)",
                PDOParams::positional([$event])
            );

            // Tenant 1 sees its own rule.
            self::$tenant->set(1);
            $before = count(self::$rules->resolveRecipients($event, [], false));
            self::assertGreaterThan(0, $before, 'Tenant 1 must see its own rule.');

            // Tenant 2 must NOT see tenant 1's rule.
            $tenant2 = self::$db->insert(
                "INSERT INTO `core_tenants` (`code`, `name`, `status`)
                 VALUES (?, ?, 'active')",
                PDOParams::positional(["NR-{$suffix}", "NotificationTest {$suffix}"])
            );
            self::$tenant->set($tenant2);
            $after = count(self::$rules->resolveRecipients($event, [], false));
            self::assertSame(0, $after, 'Tenant 2 must not see tenant 1 rules.');
        } finally {
            self::$tenant->set($original);
            self::$db->rollBack();
        }
    }
}

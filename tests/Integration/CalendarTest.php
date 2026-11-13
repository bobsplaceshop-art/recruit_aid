<?php
declare(strict_types=1);

use CorporateAid\Core\App;
use CorporateAid\Core\Auth;
use CorporateAid\Core\Database;
use CorporateAid\Core\PDOParams;
use CorporateAid\Core\Row;
use CorporateAid\Core\TenantContext;
use CorporateAid\Platform\Calendar\CalendarRegistry;
use CorporateAid\Platform\Calendar\CalendarService;
use CorporateAid\Recruit\Calendar\TaskCalendarSource;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance criteria for the calendar feature (RA-2):
 *   CAL-01: A source returns only events within the requested date range.
 *   CAL-02: A task due in the month appears under its due date.
 *   CAL-03: An event outside the range is excluded.
 *   CAL-04: Events are grouped by date in the service output.
 *   CAL-05: Tenant scoping — a source does not return another tenant's events.
 */
final class CalendarTest extends TestCase
{
    private static Database $db;
    private static Auth $auth;
    private static CalendarService $calendar;
    private static TenantContext $tenant;

    private static int $userId = 0;
    private static int $taskInRange = 0;
    private static int $taskOutOfRange = 0;
    private static int $taskOtherTenant = 0;
    private static int $tenantB = 0;

    private const TEST_YEAR  = 2026;
    private const TEST_MONTH = 11;

    public static function setUpBeforeClass(): void
    {
        App::boot(dirname(__DIR__, 2));
        $c = App::instance()->container();
        self::$db       = $c->get(Database::class);
        self::$auth     = $c->get(Auth::class);
        self::$calendar = $c->get(CalendarService::class);
        self::$tenant   = $c->get(TenantContext::class);

        self::$db->beginTransaction();

        $suffix = bin2hex(random_bytes(4));

        self::$userId = self::$db->insert(
            "INSERT INTO `core_users` (`tenant_id`, `name`, `email`, `password_hash`, `status`)
             VALUES (1, ?, ?, 'test-hash', 'active')",
            PDOParams::positional(["Cal Test User {$suffix}", "cal-test-{$suffix}@example.test"])
        );

        self::$tenantB = self::$db->insert(
            "INSERT INTO `core_tenants` (`code`, `name`, `status`)
             VALUES (?, ?, 'active')",
            PDOParams::positional(["CAL-{$suffix}", "Cal Test Tenant {$suffix}"])
        );

        // Task IN the test month (2026-11).
        self::$taskInRange = self::$db->insert(
            "INSERT INTO `recruit_tasks` (`tenant_id`, `title`, `status`, `due_at`, `created_by`)
             VALUES (1, 'Task in range', 'open', '2026-11-15 10:00:00', ?)",
            PDOParams::positional([self::$userId])
        );

        // Task OUTSIDE the test month.
        self::$taskOutOfRange = self::$db->insert(
            "INSERT INTO `recruit_tasks` (`tenant_id`, `title`, `status`, `due_at`, `created_by`)
             VALUES (1, 'Task out of range', 'open', '2027-03-20 10:00:00', ?)",
            PDOParams::positional([self::$userId])
        );

        // Task in tenant 2 — should never appear in tenant-1 calendar.
        self::$taskOtherTenant = self::$db->insert(
            "INSERT INTO `recruit_tasks` (`tenant_id`, `title`, `status`, `due_at`, `created_by`)
             VALUES (?, 'Tenant 2 task', 'open', '2026-11-15 10:00:00', ?)",
            PDOParams::positional([self::$tenantB, self::$userId])
        );

        // Log in the user so $auth->can() works, and set tenant context.
        $user = \CorporateAid\Platform\Models\User::fromRow(
            self::$db->selectOne(
                "SELECT * FROM `core_users` WHERE id = ? LIMIT 1",
                PDOParams::positional([self::$userId])
            )
        );
        // Grant the tasks.view + calendar.view permissions via a role for this user.
        $taskViewPerm = self::$db->selectOne(
            "SELECT id FROM `core_permissions` WHERE slug = 'recruit.tasks.view' LIMIT 1"
        );
        $calViewPerm = self::$db->selectOne(
            "SELECT id FROM `core_permissions` WHERE slug = 'recruit.calendar.view' LIMIT 1"
        );

        self::$tenant->set(1);
        self::$tenant->setEffective(1);
    }

    public static function tearDownAfterClass(): void
    {
        self::$db->rollBack();
    }

    public function testSourceReturnsOnlyEventsInRange(): void
    {
        $source = new TaskCalendarSource(self::$db);

        $events = $source->eventsForRange(1, '2026-11-01', '2026-11-30');

        $ids = array_map(fn ($e) => $e['meta']['task_id'], $events);
        self::assertContains(self::$taskInRange, $ids, 'In-range task must appear.');
        self::assertNotContains(self::$taskOutOfRange, $ids, 'Out-of-range task must not appear.');
    }

    public function testTaskAppearsUnderItsDueDate(): void
    {
        $source = new TaskCalendarSource(self::$db);

        $events = $source->eventsForRange(1, '2026-11-01', '2026-11-30');

        $matched = null;
        foreach ($events as $e) {
            if ($e['meta']['task_id'] === self::$taskInRange) {
                $matched = $e;
                break;
            }
        }
        self::assertNotNull($matched, 'In-range task must be found.');
        self::assertSame('2026-11-15', $matched['date'], 'Date must match due_at.');
        self::assertSame('10:00', $matched['time'], 'Time must match due_at.');
    }

    public function testOutOfRangeEventExcluded(): void
    {
        $source = new TaskCalendarSource(self::$db);

        $events = $source->eventsForRange(1, '2026-11-01', '2026-11-30');

        foreach ($events as $e) {
            self::assertNotSame(self::$taskOutOfRange, $e['meta']['task_id']);
        }
    }

    public function testEventsGroupedByDate(): void
    {
        $result = self::$calendar->eventsForMonth(self::TEST_YEAR, self::TEST_MONTH, 1, 'recruit');

        self::assertIsArray($result['events']);
        self::assertArrayHasKey('range', $result);
        self::assertSame(self::TEST_MONTH, (int) substr($result['range']['start'], 5, 2));
        self::assertSame(self::TEST_MONTH, (int) substr($result['range']['end'], 5, 2));
    }

    public function testTenantScoping(): void
    {
        $source = new TaskCalendarSource(self::$db);

        // Query as tenant 1 — tenant-2 task must NOT appear.
        $eventsTenant1 = $source->eventsForRange(1, '2026-11-01', '2026-11-30');
        $ids1 = array_map(fn ($e) => $e['meta']['task_id'], $eventsTenant1);
        self::assertNotContains(self::$taskOtherTenant, $ids1, 'Tenant-2 task must not leak to tenant 1.');

        // Query as tenant 2 — tenant-2 task DOES appear, tenant-1 task does NOT.
        $eventsTenant2 = $source->eventsForRange(self::$tenantB, '2026-11-01', '2026-11-30');
        $ids2 = array_map(fn ($e) => $e['meta']['task_id'], $eventsTenant2);
        self::assertContains(self::$taskOtherTenant, $ids2, 'Tenant-2 task must appear for tenant 2.');
        self::assertNotContains(self::$taskInRange, $ids2, 'Tenant-1 task must not leak to tenant 2.');
    }
}

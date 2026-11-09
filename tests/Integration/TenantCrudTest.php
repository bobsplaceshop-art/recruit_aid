<?php
declare(strict_types=1);

use CorporateAid\Core\App;
use CorporateAid\Core\Database;
use CorporateAid\Core\Row;
use CorporateAid\Platform\Repositories\TenantRepository;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance criteria for TenantRepository (the /cas/tenants surface's data layer):
 *   TC-01: create() inserts a new active tenant.
 *   TC-02: create() rejects duplicate codes.
 *   TC-03: update() changes mutable fields; code remains unchanged.
 *   TC-04: suspend() transitions active -> suspended.
 *   TC-05: archive() transitions active -> archived AND sets deleted_at.
 *   TC-06: restore() transitions archived -> active AND clears deleted_at.
 */
final class TenantCrudTest extends TestCase
{
    private static Database $db;
    private static TenantRepository $tenants;

    public static function setUpBeforeClass(): void
    {
        App::boot(dirname(__DIR__, 2));
        $c = App::instance()->container();
        self::$db      = $c->get(Database::class);
        self::$tenants = $c->get(TenantRepository::class);

        self::$db->beginTransaction();
    }

    public static function tearDownAfterClass(): void
    {
        self::$db->rollBack();
    }

    private function uniqueCode(): string
    {
        return 'TCT-' . bin2hex(random_bytes(4));
    }

    public function testCreateInsertsActiveTenant(): void
    {
        $code = $this->uniqueCode();
        $id = self::$tenants->create([
            'code' => $code,
            'name' => 'Tenant CRUD Test ' . $code,
        ]);

        self::assertGreaterThan(0, $id);
        $row = self::$tenants->findById($id);
        self::assertNotNull($row);
        self::assertSame($code, Row::str($row, 'code'));
        self::assertSame('active', Row::str($row, 'status'));
        self::assertNull(Row::nullableStr($row, 'deleted_at'));
    }

    public function testCreateRejectsDuplicateCode(): void
    {
        $code = $this->uniqueCode();
        self::$tenants->create(['code' => $code, 'name' => 'First']);

        $this->expectException(InvalidArgumentException::class);
        self::$tenants->create(['code' => $code, 'name' => 'Second']);
    }

    public function testUpdateChangesNameButNotCode(): void
    {
        $code = $this->uniqueCode();
        $id = self::$tenants->create(['code' => $code, 'name' => 'Original Name']);

        self::$tenants->update($id, [
            'name' => 'Renamed',
            'primary_color' => '#ff0000',
        ]);

        $row = self::$tenants->findById($id);
        self::assertSame('Renamed', Row::str($row, 'name'));
        self::assertSame('#ff0000', Row::str($row, 'primary_color'));
        self::assertSame($code, Row::str($row, 'code'), 'Code must be immutable.');
    }

    public function testSuspendTransitionsActiveToSuspended(): void
    {
        $id = self::$tenants->create(['code' => $this->uniqueCode(), 'name' => 'To Suspend']);

        self::$tenants->suspend($id);

        $row = self::$tenants->findById($id);
        self::assertSame('suspended', Row::str($row, 'status'));
    }

    public function testArchiveSetsStatusAndDeletedAt(): void
    {
        $id = self::$tenants->create(['code' => $this->uniqueCode(), 'name' => 'To Archive']);

        self::$tenants->archive($id);

        $row = self::$tenants->findById($id);
        self::assertSame('archived', Row::str($row, 'status'));
        self::assertNotNull(Row::nullableStr($row, 'deleted_at'));

        // findAll() default excludes soft-deleted.
        $all = self::$tenants->findAll();
        $found = false;
        foreach ($all as $t) {
            if ((int) Row::int($t, 'id') === $id) {
                $found = true;
                break;
            }
        }
        self::assertFalse($found, 'Archived tenant must not appear in default findAll().');
    }

    public function testRestoreClearsDeletedAt(): void
    {
        $id = self::$tenants->create(['code' => $this->uniqueCode(), 'name' => 'To Restore']);
        self::$tenants->archive($id);

        self::$tenants->restore($id);

        $row = self::$tenants->findById($id);
        self::assertSame('active', Row::str($row, 'status'));
        self::assertNull(Row::nullableStr($row, 'deleted_at'));

        // findAll() default now includes it again.
        $all = self::$tenants->findAll();
        $found = false;
        foreach ($all as $t) {
            if ((int) Row::int($t, 'id') === $id) {
                $found = true;
                break;
            }
        }
        self::assertTrue($found, 'Restored tenant must appear in default findAll().');
    }
}

<?php
declare(strict_types=1);

use CorporateAid\Core\App;
use CorporateAid\Core\Database;
use CorporateAid\Core\PDOParams;
use CorporateAid\Core\Row;
use CorporateAid\Core\TenantContext;
use CorporateAid\Recruit\Repositories\TaskRepository;
use CorporateAid\Recruit\Services\TaskService;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance criteria for the task module (RA-1):
 *   TC-01: create() inserts a task with status='open', tenant-scoped.
 *   TC-02: create() with a valid entity (candidate) succeeds.
 *   TC-03: create() with a non-existent entity id throws.
 *   TC-04: complete() sets status='completed', completed_at is populated.
 *   TC-05: reopen() returns to status='open', completed_at cleared.
 *   TC-06: delete() soft-deletes; the row is excluded from findAll().
 */
final class TaskCrudTest extends TestCase
{
    private static Database $db;
    private static TaskRepository $tasks;
    private static TaskService $service;
    private static TenantContext $tenant;

    private static int $userId = 0;
    private static int $candidateId = 0;

    public static function setUpBeforeClass(): void
    {
        App::boot(dirname(__DIR__, 2));
        $c = App::instance()->container();
        self::$db      = $c->get(Database::class);
        self::$tasks   = $c->get(TaskRepository::class);
        self::$service = $c->get(TaskService::class);
        self::$tenant  = $c->get(TenantContext::class);

        self::$db->beginTransaction();

        $suffix = bin2hex(random_bytes(4));

        // Create a fresh tenant-1 test user.
        self::$userId = self::$db->insert(
            "INSERT INTO `core_users` (`tenant_id`, `name`, `email`, `password_hash`, `status`)
             VALUES (1, ?, ?, 'test-hash', 'active')",
            PDOParams::positional(["Task Test User {$suffix}", "task-test-{$suffix}@example.test"])
        );

        // Create a fresh candidate in tenant 1.
        $orgRow = self::$db->selectOne(
            "SELECT id FROM recruit_client_organisations WHERE tenant_id = 1 AND deleted_at IS NULL LIMIT 1"
        );
        $orgId = $orgRow !== null ? Row::int($orgRow, 'id') : null;

        self::$candidateId = self::$db->insert(
            "INSERT INTO `recruit_candidates`
                (`tenant_id`, `code`, `first_name`, `last_name`, `email`, `status`)
             VALUES (1, ?, 'Test', 'Candidate', ?, 'active')",
            PDOParams::positional([
                'TC-' . $suffix,
                "tc-cand-{$suffix}@example.test",
            ])
        );

        self::$tenant->set(1);
        self::$tenant->setEffective(1);
    }

    public static function tearDownAfterClass(): void
    {
        self::$db->rollBack();
    }

    public function testCreateInsertsOpenTask(): void
    {
        $id = $this->createTask(['title' => 'Plain task, no entity']);

        self::assertGreaterThan(0, $id);
        $task = self::$tasks->findById($id);
        self::assertNotNull($task);
        self::assertSame('Plain task, no entity', Row::str($task, 'title'));
        self::assertSame('open', Row::str($task, 'status'));
        self::assertSame(1, Row::int($task, 'tenant_id'));
        self::assertNull(Row::nullableStr($task, 'completed_at'));
    }

    public function testCreateWithValidEntity(): void
    {
        $id = $this->createTask([
            'title'       => 'Follow up on candidate',
            'entity_type' => 'candidate',
            'entity_id'   => self::$candidateId,
        ]);

        $task = self::$tasks->findById($id);
        self::assertNotNull($task);
        self::assertSame('candidate', Row::str($task, 'entity_type'));
        self::assertSame(self::$candidateId, Row::int($task, 'entity_id'));
    }

    public function testCreateWithNonExistentEntityThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('not found');

        self::$service->create([
            'title'       => 'Task on missing entity',
            'entity_type' => 'candidate',
            'entity_id'   => 999999999,
        ], self::$userId);
    }

    public function testCompleteSetsStatusAndCompletedAt(): void
    {
        $id = $this->createTask(['title' => 'To be completed']);

        self::$service->complete($id);

        $task = self::$tasks->findById($id);
        self::assertSame('completed', Row::str($task, 'status'));
        self::assertNotNull(Row::nullableStr($task, 'completed_at'));
    }

    public function testReopenClearsCompletedAt(): void
    {
        $id = $this->createTask(['title' => 'Complete then reopen']);
        self::$service->complete($id);
        self::$service->reopen($id);

        $task = self::$tasks->findById($id);
        self::assertSame('open', Row::str($task, 'status'));
        self::assertNull(Row::nullableStr($task, 'completed_at'));
    }

    public function testDeleteSoftDeletesAndExcludesFromList(): void
    {
        $id = $this->createTask(['title' => 'To be deleted']);

        self::$service->delete($id);

        // Excluded from findById (its WHERE includes deleted_at IS NULL).
        self::assertNull(self::$tasks->findById($id));

        // The row still exists physically.
        $raw = self::$db->selectOne(
            "SELECT * FROM recruit_tasks WHERE id = ?",
            PDOParams::positional([$id])
        );
        self::assertNotNull($raw);
        self::assertNotNull(Row::nullableStr($raw, 'deleted_at'));

        // Not in findAll().
        $result = self::$tasks->findAll([]);
        foreach ($result['rows'] as $r) {
            self::assertNotSame($id, Row::int($r, 'id'), 'Soft-deleted task must not appear in findAll.');
        }
    }

    // --- helpers ---

    private function createTask(array $fields): int
    {
        $input = array_merge([
            'title'       => 'Default title',
            'description' => '',
            'priority'    => 'normal',
            'due_at'      => '',
            'assigned_to' => 0,
            'entity_type' => '',
            'entity_id'   => 0,
        ], $fields);

        $result = self::$service->create($input, self::$userId);
        self::assertSame([], $result['errors'], 'Task creation must succeed. Errors: ' . json_encode($result['errors']));
        return $result['id'];
    }
}

<?php
declare(strict_types=1);

namespace CorporateAid\Recruit\Services;

use CorporateAid\Core\Auth;
use CorporateAid\Core\Database;
use CorporateAid\Core\PDOParams;
use CorporateAid\Core\Row;
use CorporateAid\Core\TenantContext;
use CorporateAid\Platform\Support\ActivityLogger;
use CorporateAid\Recruit\Repositories\TaskRepository;
use InvalidArgumentException;

/**
 * Business rules for tasks.
 *
 * Handles:
 *   - Input validation (title, priority, due date, assignee, entity type)
 *   - Polymorphic entity existence check (tenant-scoped)
 *   - Activity logging on state transitions
 *
 * The controller calls create/update/complete/reopen/delete. This service
 * is the single place where business rules live.
 */
final class TaskService
{
    /** Entity types that a task may be attached to. */
    private const VALID_ENTITY_TYPES = [
        'candidate', 'vacancy', 'application', 'interview', 'offer',
    ];

    /** Repository map for entity existence checks. */
    private const ENTITY_REPOSITORIES = [
        'candidate'   => \CorporateAid\Recruit\Repositories\CandidateRepository::class,
        'vacancy'     => \CorporateAid\Recruit\Repositories\VacancyRepository::class,
        'application' => \CorporateAid\Recruit\Repositories\ApplicationRepository::class,
        'interview'   => \CorporateAid\Recruit\Repositories\InterviewRepository::class,
        'offer'       => \CorporateAid\Recruit\Repositories\OfferRepository::class,
    ];

    public function __construct(
        private TaskRepository $tasks,
        private ActivityLogger $activity,
        private Database $db,
        private TenantContext $tenant,
        private ?Auth $auth = null
    ) {}

    /**
     * @param array{title?:string, description?:string, priority?:string, due_at?:string, assigned_to?:int, entity_type?:string, entity_id?:int} $input
     * @return array{id:int, errors:string[]}
     */
    public function create(array $input, int $createdBy): array
    {
        $errors = $this->validate($input, 'create');
        if (!empty($errors)) {
            return ['id' => 0, 'errors' => $errors];
        }

        // Polymorphic entity check.
        $entityType = trim((string) ($input['entity_type'] ?? ''));
        $entityId   = (int) ($input['entity_id'] ?? 0);
        if ($entityType !== '' && $entityId > 0) {
            $this->assertEntityExists($entityType, $entityId);
        } else {
            $entityType = null;
            $entityId = null;
        }

        // Assignee must be an active user in this tenant.
        $assignedTo = (int) ($input['assigned_to'] ?? 0);
        if ($assignedTo > 0) {
            $this->assertAssigneeValid($assignedTo);
        } else {
            $assignedTo = null;
        }

        $id = $this->tasks->create([
            'title'       => trim((string) $input['title']),
            'description' => $this->nullableString($input['description'] ?? null),
            'priority'    => (string) ($input['priority'] ?? 'normal'),
            'due_at'      => $this->nullableString($input['due_at'] ?? null),
            'assigned_to' => $assignedTo,
            'entity_type' => $entityType,
            'entity_id'   => $entityId,
            'created_by'  => $createdBy,
        ]);

        $this->activity->log(
            'task.created',
            'tasks',
            'Task created: ' . trim((string) $input['title']),
            'task',
            $id
        );

        return ['id' => $id, 'errors' => []];
    }

    /**
     * @param array{title?:string, description?:string, priority?:string, due_at?:string, assigned_to?:int} $input
     * @return array{errors:string[]}
     */
    public function update(int $id, array $input): array
    {
        $existing = $this->tasks->findById($id);
        if ($existing === null) {
            return ['errors' => ['Task not found.']];
        }

        $errors = $this->validate($input, 'update');
        if (!empty($errors)) {
            return ['errors' => $errors];
        }

        $assignedTo = array_key_exists('assigned_to', $input) ? (int) $input['assigned_to'] : null;
        if ($assignedTo !== null && $assignedTo > 0) {
            $this->assertAssigneeValid($assignedTo);
        }

        $this->tasks->update($id, [
            'title'       => isset($input['title']) ? trim((string) $input['title']) : null,
            'description' => array_key_exists('description', $input) ? $this->nullableString($input['description']) : null,
            'priority'    => isset($input['priority']) ? (string) $input['priority'] : null,
            'due_at'      => array_key_exists('due_at', $input) ? $this->nullableString($input['due_at']) : null,
            'assigned_to' => $assignedTo,
        ]);

        return ['errors' => []];
    }

    public function complete(int $id): void
    {
        $task = $this->tasks->findById($id);
        if ($task === null) {
            throw new InvalidArgumentException('Task not found.');
        }
        if (Row::str($task, 'status') !== 'open') {
            throw new InvalidArgumentException('Only open tasks can be completed.');
        }
        $this->tasks->complete($id);
        $this->activity->log('task.completed', 'tasks', 'Task completed: ' . Row::str($task, 'title'), 'task', $id);
    }

    public function reopen(int $id): void
    {
        $task = $this->tasks->findById($id);
        if ($task === null) {
            throw new InvalidArgumentException('Task not found.');
        }
        if (!in_array(Row::str($task, 'status'), ['completed', 'cancelled'], true)) {
            throw new InvalidArgumentException('Only completed or cancelled tasks can be reopened.');
        }
        $this->tasks->reopen($id);
        $this->activity->log('task.reopened', 'tasks', 'Task reopened: ' . Row::str($task, 'title'), 'task', $id);
    }

    public function delete(int $id): void
    {
        $task = $this->tasks->findById($id);
        if ($task === null) {
            throw new InvalidArgumentException('Task not found.');
        }
        $this->tasks->softDelete($id);
        $this->activity->log('task.deleted', 'tasks', 'Task deleted: ' . Row::str($task, 'title'), 'task', $id);
    }

    /**
     * @param array<string, mixed> $input
     * @return string[]
     */
    private function validate(array $input, string $mode): array
    {
        $errors = [];

        if ($mode === 'create') {
            $title = trim((string) ($input['title'] ?? ''));
            if ($title === '') {
                $errors[] = 'Title is required.';
            } elseif (strlen($title) > 255) {
                $errors[] = 'Title must be 255 characters or fewer.';
            }
        } elseif ($mode === 'update' && isset($input['title'])) {
            $title = trim((string) $input['title']);
            if ($title === '') {
                $errors[] = 'Title cannot be empty.';
            }
        }

        $priority = (string) ($input['priority'] ?? 'normal');
        if (!in_array($priority, ['low', 'normal', 'high'], true)) {
            $errors[] = 'Priority must be low, normal, or high.';
        }

        $dueAt = trim((string) ($input['due_at'] ?? ''));
        if ($dueAt !== '' && strtotime($dueAt) === false) {
            $errors[] = 'Due date must be a valid date/time.';
        }

        $entityType = trim((string) ($input['entity_type'] ?? ''));
        if ($entityType !== '' && !in_array($entityType, self::VALID_ENTITY_TYPES, true)) {
            $errors[] = 'Entity type must be one of: ' . implode(', ', self::VALID_ENTITY_TYPES) . '.';
        }

        if ($entityType !== '' && (int) ($input['entity_id'] ?? 0) <= 0) {
            $errors[] = 'Entity ID is required when an entity type is selected.';
        }

        return $errors;
    }

    private function assertEntityExists(string $entityType, int $entityId): void
    {
        if (!isset(self::ENTITY_REPOSITORIES[$entityType])) {
            throw new InvalidArgumentException("Unknown entity type: {$entityType}.");
        }
        $repoClass = self::ENTITY_REPOSITORIES[$entityType];

        // Resolve from container? No — service doesn't have the container.
        // Instead, use a direct DB check. Tenant-scoped.
        $table = match ($entityType) {
            'candidate'   => 'recruit_candidates',
            'vacancy'     => 'recruit_vacancies',
            'application' => 'recruit_applications',
            'interview'   => 'recruit_interviews',
            'offer'       => 'recruit_offers',
        };
        $count = $this->db->scalarInt(
            "SELECT COUNT(*) FROM `{$table}`
             WHERE `id` = ? AND `tenant_id` = ? AND `deleted_at` IS NULL",
            PDOParams::positional([$entityId, $this->tenant->id()])
        );
        if ($count === 0) {
            throw new InvalidArgumentException("Entity of type '{$entityType}' with id {$entityId} not found.");
        }
    }

    private function assertAssigneeValid(int $userId): void
    {
        $count = $this->db->scalarInt(
            "SELECT COUNT(*) FROM `core_users`
             WHERE `id` = ? AND `tenant_id` = ? AND `status` = 'active' AND `deleted_at` IS NULL",
            PDOParams::positional([$userId, $this->tenant->id()])
        );
        if ($count === 0) {
            throw new InvalidArgumentException("Assignee user {$userId} not found in this tenant.");
        }
    }

    private function nullableString(mixed $raw): ?string
    {
        if (!is_string($raw)) {
            return null;
        }
        $trim = trim($raw);
        return $trim === '' ? null : $trim;
    }
}

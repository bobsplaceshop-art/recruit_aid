<?php
/**
 * Tasks list.
 *
 * $result   — array{rows, total, page, per_page, total_pages} from TaskRepository::findAll()
 * $filters  — active filters (status, priority, assigned_to, entity_type, entity_id, due_filter)
 * $overdue  — count of overdue tasks for current user
 * $open_mine — count of open tasks for current user
 */
use CorporateAid\Core\Row;

$rows = $result['rows'] ?? [];
$total = $result['total'] ?? 0;
$page = $result['page'] ?? 1;
$totalPages = $result['total_pages'] ?? 1;
$perPage = $result['per_page'] ?? 50;
$start = $total === 0 ? 0 : (($page - 1) * $perPage) + 1;
$end = min($page * $perPage, $total);

$currentStatus = (string) ($filters['status'] ?? '');
$currentPriority = (string) ($filters['priority'] ?? '');
$currentDue = (string) ($filters['due_filter'] ?? '');
?>
<div class="ra-page-band">
    <div class="ra-page-band-row">
        <div>
            <div class="ra-page-band-label">Workspace · Tasks</div>
            <h1 class="ra-page-band-title">Tasks</h1>
            <p class="ra-page-sub">
                <?php if ($overdue > 0): ?>
                    <span class="ra-badge ra-badge-danger"><?= (int) $overdue ?> overdue</span>
                <?php endif; ?>
                <span class="ra-badge"><?= (int) $open_mine ?> open (mine)</span>
            </p>
        </div>
        <div class="ra-page-band-actions">
            <?php if ($user->can('recruit.tasks.create')): ?>
                <a href="/recruit/tasks/create" class="ra-btn ra-btn-primary">
                    <i class="bi bi-plus-lg"></i>
                    <span>New Task</span>
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="ra-card">
    <form method="get" action="/recruit/tasks" class="ra-form-inline">
        <label>Status
            <select name="status">
                <option value="open" <?= $currentStatus === 'open' ? 'selected' : '' ?>>Open</option>
                <option value="completed" <?= $currentStatus === 'completed' ? 'selected' : '' ?>>Completed</option>
                <option value="cancelled" <?= $currentStatus === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                <option value="" <?= $currentStatus === '' ? 'selected' : '' ?>>All</option>
            </select>
        </label>
        <label>Priority
            <select name="priority">
                <option value="">Any</option>
                <option value="high" <?= $currentPriority === 'high' ? 'selected' : '' ?>>High</option>
                <option value="normal" <?= $currentPriority === 'normal' ? 'selected' : '' ?>>Normal</option>
                <option value="low" <?= $currentPriority === 'low' ? 'selected' : '' ?>>Low</option>
            </select>
        </label>
        <label>Due
            <select name="due_filter">
                <option value="">Any</option>
                <option value="overdue" <?= $currentDue === 'overdue' ? 'selected' : '' ?>>Overdue</option>
                <option value="today" <?= $currentDue === 'today' ? 'selected' : '' ?>>Today</option>
                <option value="week" <?= $currentDue === 'week' ? 'selected' : '' ?>>Next 7 days</option>
            </select>
        </label>
        <button type="submit" class="ra-btn ra-btn-outline">Filter</button>
    </form>
</div>

<?php if (empty($rows)): ?>
    <div class="ra-card">
        <p class="muted">No tasks found. <?php if ($user->can('recruit.tasks.create')): ?><a href="/recruit/tasks/create">Create one</a>.<?php endif; ?></p>
    </div>
<?php else: ?>
    <div class="ra-table-card">
        <div class="ra-table-toolbar">
            <div class="ra-table-toolbar-left">
                <span class="ra-section-label">
                    <?= (int) $total ?> task<?= $total === 1 ? '' : 's' ?>
                    <?php if ($total > 0): ?> · showing <?= (int) $start ?>–<?= (int) $end ?><?php endif; ?>
                </span>
            </div>
        </div>
        <table class="ra-table">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Entity</th>
                    <th>Assigned</th>
                    <th>Priority</th>
                    <th>Due</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $task): ?>
                    <?php
                    $taskId = (int) Row::int($task, 'id');
                    $status = Row::str($task, 'status');
                    $priority = Row::str($task, 'priority');
                    $dueAt = Row::nullableStr($task, 'due_at');
                    $isOverdue = $dueAt !== null && $status === 'open' && strtotime($dueAt) < time();
                    $entityType = Row::nullableStr($task, 'entity_type');
                    $entityId = Row::nullableInt($task, 'entity_id');
                    ?>
                    <tr>
                        <td>
                            <strong><?= e(Row::str($task, 'title')) ?></strong>
                            <?php if (Row::nullableStr($task, 'description')): ?>
                                <div class="muted" style="font-size:.85rem;"><?= e(Row::str($task, 'description')) ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($entityType !== null && $entityId !== null): ?>
                                <span class="muted"><?= e($entityType) ?> #<?= (int) $entityId ?></span>
                            <?php else: ?>
                                <span class="muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td><?= e(Row::str($task, 'assigned_name', '—')) ?></td>
                        <td>
                            <span class="ra-badge ra-badge-<?= $priority === 'high' ? 'danger' : ($priority === 'low' ? 'muted' : 'info') ?>">
                                <?= e($priority) ?>
                            </span>
                        </td>
                        <td<?= $isOverdue ? ' style="color:var(--ra-danger-fg);font-weight:600;"' : '' ?>>
                            <?= $dueAt !== null ? e(date('Y-m-d H:i', strtotime($dueAt))) : '—' ?>
                        </td>
                        <td><?= e($status) ?></td>
                        <td>
                            <?php if ($status === 'open' && $user->can('recruit.tasks.edit')): ?>
                                <form method="post" action="/recruit/tasks/<?= $taskId ?>/complete" style="display:inline">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="ra-btn ra-btn-outline" style="padding:.25rem .5rem;font-size:.85rem;">Complete</button>
                                </form>
                            <?php elseif ($status !== 'open' && $user->can('recruit.tasks.edit')): ?>
                                <form method="post" action="/recruit/tasks/<?= $taskId ?>/reopen" style="display:inline">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="ra-btn ra-btn-outline" style="padding:.25rem .5rem;font-size:.85rem;">Reopen</button>
                                </form>
                            <?php endif; ?>
                            <?php if ($user->can('recruit.tasks.delete')): ?>
                                <form method="post" action="/recruit/tasks/<?= $taskId ?>/delete" style="display:inline" onsubmit="return confirm('Delete this task?')">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="ra-btn ra-btn-outline" style="padding:.25rem .5rem;font-size:.85rem;">Delete</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($totalPages > 1): ?>
            <div class="ra-pagination">
                <?php if ($page > 1): ?>
                    <a href="/recruit/tasks?<?= http_build_query(array_merge($filters, ['page' => $page - 1])) ?>">&laquo; Prev</a>
                <?php endif; ?>
                <span>Page <?= (int) $page ?> of <?= (int) $totalPages ?></span>
                <?php if ($page < $totalPages): ?>
                    <a href="/recruit/tasks?<?= http_build_query(array_merge($filters, ['page' => $page + 1])) ?>">Next &raquo;</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

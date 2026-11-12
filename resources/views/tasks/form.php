<?php
/**
 * Task create form.
 *
 * $task   — null (this form is create-only for v1)
 * $old    — submitted values on validation failure
 * $errors — flat array of error messages
 */
use CorporateAid\Core\Row;

$get = function (string $key, string $default = '') use ($old, $task): string {
    if (!empty($old) && array_key_exists($key, $old)) {
        return (string) $old[$key];
    }
    if ($task !== null) {
        return Row::str($task, $key, $default);
    }
    return $default;
};
?>
<div class="ra-page-band">
    <div class="ra-page-band-row">
        <div>
            <div class="ra-page-band-label">Workspace · Tasks · New</div>
            <h1 class="ra-page-band-title">New Task</h1>
            <p class="ra-page-sub">Assign a follow-up to yourself or a colleague.</p>
        </div>
        <div class="ra-page-band-actions">
            <a href="/recruit/tasks" class="ra-btn ra-btn-outline">
                <i class="bi bi-arrow-left"></i>
                <span>Back to tasks</span>
            </a>
        </div>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="ra-alert ra-alert-error">
        <ul class="mb-0" style="padding-left:1.2rem;">
            <?php foreach ($errors as $err): ?>
                <li><?= e((string) $err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="ra-card">
    <form method="post" action="/recruit/tasks" novalidate>
        <?= csrf_field() ?>

        <div class="mb-3">
            <label class="ra-label" for="task-title">Title</label>
            <input type="text" id="task-title" name="title" class="ra-input"
                   value="<?= e($get('title')) ?>" required maxlength="255" autofocus>
        </div>

        <div class="mb-3">
            <label class="ra-label" for="task-description">Description (optional)</label>
            <textarea id="task-description" name="description" class="ra-input" rows="3"><?= e($get('description')) ?></textarea>
        </div>

        <div class="mb-3">
            <label class="ra-label" for="task-priority">Priority</label>
            <select id="task-priority" name="priority" class="ra-input">
                <?php $currentPriority = $get('priority', 'normal'); ?>
                <option value="low" <?= $currentPriority === 'low' ? 'selected' : '' ?>>Low</option>
                <option value="normal" <?= $currentPriority === 'normal' ? 'selected' : '' ?>>Normal</option>
                <option value="high" <?= $currentPriority === 'high' ? 'selected' : '' ?>>High</option>
            </select>
        </div>

        <div class="mb-3">
            <label class="ra-label" for="task-due">Due date (optional)</label>
            <input type="datetime-local" id="task-due" name="due_at" class="ra-input"
                   value="<?= e($get('due_at')) ?>">
        </div>

        <div class="mb-3">
            <label class="ra-label" for="task-assigned">Assign to (optional)</label>
            <input type="number" id="task-assigned" name="assigned_to" class="ra-input"
                   value="<?= e((string) $get('assigned_to')) ?>" min="0"
                   placeholder="User ID">
            <div class="muted" style="font-size:.8rem;">Leave blank to leave unassigned.</div>
        </div>

        <div class="mb-3">
            <label class="ra-label" for="task-entity-type">Attach to entity (optional)</label>
            <select id="task-entity-type" name="entity_type" class="ra-input">
                <?php $currentType = $get('entity_type'); ?>
                <option value="">— None —</option>
                <option value="candidate"   <?= $currentType === 'candidate'   ? 'selected' : '' ?>>Candidate</option>
                <option value="vacancy"     <?= $currentType === 'vacancy'     ? 'selected' : '' ?>>Vacancy</option>
                <option value="application" <?= $currentType === 'application' ? 'selected' : '' ?>>Application</option>
                <option value="interview"   <?= $currentType === 'interview'   ? 'selected' : '' ?>>Interview</option>
                <option value="offer"       <?= $currentType === 'offer'       ? 'selected' : '' ?>>Offer</option>
            </select>
        </div>

        <div class="mb-3">
            <label class="ra-label" for="task-entity-id">Entity ID (if attached)</label>
            <input type="number" id="task-entity-id" name="entity_id" class="ra-input"
                   value="<?= e((string) $get('entity_id')) ?>" min="0" placeholder="Numeric ID">
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="ra-btn ra-btn-primary">
                <i class="bi bi-check-lg"></i>
                <span>Create task</span>
            </button>
            <a href="/recruit/tasks" class="ra-btn ra-btn-outline">Cancel</a>
        </div>
    </form>
</div>

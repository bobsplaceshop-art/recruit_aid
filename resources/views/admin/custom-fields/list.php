<?php
/**
 * List custom fields for one entity.
 *
 * $fields     — CustomField[]
 * $entityKey  — e.g. 'organisation'
 * $entityLabel— e.g. 'Organisations'
 * $typeLabels — [input_type_key => 'Human label']
 */
$total = count($fields);
?>
<div class="ra-page-band">
    <div class="ra-page-band-row">
        <div>
            <div class="ra-page-band-label">Administration · Custom Fields</div>
            <h1 class="ra-page-band-title"><?= e($entityLabel) ?></h1>
        </div>
        <div class="ra-page-band-actions">
            <a href="/admin/custom-fields" class="ra-btn ra-btn-outline">
                <i class="bi bi-arrow-left"></i>
                <span>Back</span>
            </a>
            <a href="/admin/custom-fields/<?= e($entityKey) ?>/create" class="ra-btn ra-btn-primary">
                <i class="bi bi-plus-lg"></i>
                <span>New Field</span>
            </a>
        </div>
    </div>
</div>

<div class="ra-table-card">
    <div class="ra-table-toolbar">
        <div class="ra-table-toolbar-left">
            <span class="ra-section-label">
                <?= $total ?> field<?= $total === 1 ? '' : 's' ?> defined
            </span>
        </div>
        <div class="ra-table-toolbar-right">
            <span class="muted small">
                <i class="bi bi-arrow-up-down"></i> Use the arrows to reorder
            </span>
        </div>
    </div>

    <?php if ($total === 0): ?>
        <div class="text-center muted" style="padding:3rem 1.5rem;">
            <div class="mb-2">No custom fields defined for <?= e($entityLabel) ?> yet.</div>
            <a href="/admin/custom-fields/<?= e($entityKey) ?>/create" class="ra-btn ra-btn-primary">
                <i class="bi bi-plus-lg"></i>
                <span>Add the first field</span>
            </a>
        </div>
    <?php else: ?>
        <table class="ra-table">
            <thead>
                <tr>
                    <th style="width:100px;">Order</th>
                    <th>Label</th>
                    <th>Key</th>
                    <th>Type</th>
                    <th style="width:110px;">Required</th>
                    <th style="width:100px;">Status</th>
                    <th style="width:120px;">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($fields as $i => $field): ?>
                <?php
                    $isFirst = $i === 0;
                    $isLast  = $i === $total - 1;
                    $fieldId = (int) $field->id;
                ?>
                <tr>
                    <td>
                        <div class="d-flex gap-1">
                            <form method="POST"
                                  action="/admin/custom-fields/<?= e($entityKey) ?>/<?= $fieldId ?>/move-up"
                                  style="margin:0;">
                                <?= csrf_field() ?>
                                <button type="submit"
                                        class="ra-row-actions"
                                        title="<?= $isFirst ? 'Already at top' : 'Move up' ?>"
                                        <?= $isFirst ? 'disabled style="opacity:.35;cursor:not-allowed;"' : '' ?>>
                                    <i class="bi bi-arrow-up"></i>
                                </button>
                            </form>
                            <form method="POST"
                                  action="/admin/custom-fields/<?= e($entityKey) ?>/<?= $fieldId ?>/move-down"
                                  style="margin:0;">
                                <?= csrf_field() ?>
                                <button type="submit"
                                        class="ra-row-actions"
                                        title="<?= $isLast ? 'Already at bottom' : 'Move down' ?>"
                                        <?= $isLast ? 'disabled style="opacity:.35;cursor:not-allowed;"' : '' ?>>
                                    <i class="bi bi-arrow-down"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                    <td>
                        <div><?= e($field->label) ?></div>
                        <?php if ($field->helpText): ?>
                            <div class="muted small"><?= e($field->helpText) ?></div>
                        <?php endif; ?>
                    </td>
                    <td><code style="color:var(--ra-muted);font-size:.82rem;"><?= e($field->fieldKey) ?></code></td>
                    <td class="muted small"><?= e($typeLabels[$field->inputType] ?? $field->inputType) ?></td>
                    <td>
                        <form method="POST"
                              action="/admin/custom-fields/<?= e($entityKey) ?>/<?= $fieldId ?>/toggle-required"
                              style="margin:0;">
                            <?= csrf_field() ?>
                            <label class="ra-toggle" title="<?= $field->isRequired ? 'Required' : 'Optional' ?>">
                                <input type="checkbox"
                                       name="required"
                                       value="1"
                                       <?= $field->isRequired ? 'checked' : '' ?>
                                       onchange="this.form.submit()">
                                <span class="ra-toggle-track"></span>
                                <span class="ra-toggle-thumb"></span>
                            </label>
                        </form>
                    </td>
                    <td>
                        <?php if ($field->isActive): ?>
                            <span class="ra-badge ra-badge-success">Active</span>
                        <?php else: ?>
                            <span class="ra-badge">Archived</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="d-flex gap-1">
                            <a href="/admin/custom-fields/<?= e($entityKey) ?>/<?= $fieldId ?>/edit"
                               class="ra-row-actions" title="Edit">
                                <i class="bi bi-pencil"></i>
                            </a>

                            <?php if ($field->isActive): ?>
                                <form method="POST"
                                      action="/admin/custom-fields/<?= e($entityKey) ?>/<?= $fieldId ?>/archive"
                                      style="margin:0;">
                                    <?= csrf_field() ?>
                                    <button type="submit"
                                            class="ra-row-actions"
                                            title="Archive"
                                            onclick="return confirm('Archive this field? It will stop appearing on forms but keep its historical values.');">
                                        <i class="bi bi-archive"></i>
                                    </button>
                                </form>
                            <?php else: ?>
                                <form method="POST"
                                      action="/admin/custom-fields/<?= e($entityKey) ?>/<?= $fieldId ?>/restore"
                                      style="margin:0;">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="ra-row-actions" title="Restore">
                                        <i class="bi bi-arrow-counterclockwise"></i>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

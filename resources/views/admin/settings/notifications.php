<?php
/**
 * Notification rules admin.
 *
 * Data contract (from AdminNotificationRuleController::index()):
 *   $grouped         — [event_type => [rules]] with each rule already
 *                      enriched with 'event_label' and 'recipient_label'
 *   $eventTypes      — catalog [slug => label] for the "add rule" dropdown
 *   $recipientTypes  — catalog [slug => label] for the "add rule" dropdown
 *   $isSuper         — whether the current user can modify rules
 *   $success         — flash message (nullable)
 *   $error           — flash message (nullable)
 */
?>
<div class="ra-page-band">
    <div class="ra-page-band-row">
        <div>
            <div class="ra-page-band-label">Administration · Settings</div>
            <h1 class="ra-page-band-title">Notification Rules</h1>
            <p class="ra-page-sub">Configure who gets notified about what.</p>
        </div>
        <div class="ra-page-band-actions">
            <a href="/admin/settings" class="ra-btn ra-btn-outline">
                <i class="bi bi-arrow-left"></i><span>Back</span>
            </a>
        </div>
    </div>
</div>

<?php if (!$isSuper): ?>
    <div class="ra-alert ra-alert-warn">
        <i class="bi bi-lock me-1"></i>
        You can view these rules, but only super administrators can modify them.
    </div>
<?php endif; ?>

<?php if (!empty($success)): ?>
    <div class="ra-alert ra-alert-success"><?= e((string) $success) ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="ra-alert ra-alert-error"><?= e((string) $error) ?></div>
<?php endif; ?>

<div class="ra-alert ra-alert-warn">
    <i class="bi bi-info-circle me-1"></i>
    <strong>In-app</strong> notifications appear in the bell menu. <strong>Email</strong> notifications
    will be sent as part of a daily digest — not one email per event.
</div>

<?php if (empty($grouped)): ?>
    <div class="ra-card text-center muted" style="padding:3rem;">
        No notification rules defined yet.
    </div>
<?php else: ?>
    <?php foreach ($grouped as $eventType => $rules): ?>
        <?php $eventLabel = $rules[0]['event_label'] ?? 'Event'; ?>
        <div class="ra-card mb-3">
            <div class="mb-3">
                <h3 class="h6 mb-1" style="color:var(--ra-text-strong);">
                    <?= e($eventLabel) ?>
                </h3>
            </div>

            <table class="ra-table">
                <thead>
                    <tr>
                        <th>Recipient</th>
                        <th style="width:100px;">In-app</th>
                        <th style="width:100px;">Email</th>
                        <th style="width:100px;">Active</th>
                        <th style="width:100px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rules as $r): ?>
                    <?php
                        $label = $r['recipient_label'] ?? '—';
                        $isSystem = !empty($r['is_system']);
                    ?>
                    <tr>
                        <td>
                            <?= e($label) ?>
                            <?php if ($isSystem): ?>
                                <span class="ra-badge ms-1">system</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <form method="POST" action="/admin/settings/notifications/<?= (int) $r['id'] ?>/toggle" style="margin:0;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="column" value="channel_inapp">
                                <input type="hidden" name="value" value="<?= !empty($r['channel_inapp']) ? '0' : '1' ?>">
                                <button type="submit" class="ra-row-actions" <?= $isSuper ? '' : 'disabled' ?> title="Toggle in-app">
                                    <i class="bi <?= !empty($r['channel_inapp']) ? 'bi-check-circle-fill' : 'bi-circle' ?>" style="color:<?= !empty($r['channel_inapp']) ? 'var(--ra-success-fg)' : 'var(--ra-muted)' ?>;"></i>
                                </button>
                            </form>
                        </td>
                        <td>
                            <form method="POST" action="/admin/settings/notifications/<?= (int) $r['id'] ?>/toggle" style="margin:0;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="column" value="channel_email">
                                <input type="hidden" name="value" value="<?= !empty($r['channel_email']) ? '0' : '1' ?>">
                                <button type="submit" class="ra-row-actions" <?= $isSuper ? '' : 'disabled' ?> title="Toggle email">
                                    <i class="bi <?= !empty($r['channel_email']) ? 'bi-check-circle-fill' : 'bi-circle' ?>" style="color:<?= !empty($r['channel_email']) ? 'var(--ra-success-fg)' : 'var(--ra-muted)' ?>;"></i>
                                </button>
                            </form>
                        </td>
                        <td>
                            <form method="POST" action="/admin/settings/notifications/<?= (int) $r['id'] ?>/toggle" style="margin:0;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="column" value="is_active">
                                <input type="hidden" name="value" value="<?= !empty($r['is_active']) ? '0' : '1' ?>">
                                <button type="submit" class="ra-row-actions" <?= $isSuper ? '' : 'disabled' ?> title="Toggle active">
                                    <i class="bi <?= !empty($r['is_active']) ? 'bi-toggle-on' : 'bi-toggle-off' ?>" style="color:<?= !empty($r['is_active']) ? 'var(--ra-success-fg)' : 'var(--ra-muted)' ?>;"></i>
                                </button>
                            </form>
                        </td>
                        <td>
                            <?php if (!$isSystem && $isSuper): ?>
                                <form method="POST" action="/admin/settings/notifications/<?= (int) $r['id'] ?>/delete" style="margin:0;"
                                      onsubmit="return confirm('Delete this rule?');">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="ra-row-actions" title="Delete">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            <?php else: ?>
                                <span class="muted small">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php if ($isSuper): ?>
    <div class="ra-card mt-4">
        <h2 class="h5 mb-3">Add a new rule</h2>

        <form method="POST" action="/admin/settings/notifications" novalidate>
            <?= csrf_field() ?>

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="ra-label">Event</label>
                    <select name="event_type" class="ra-input ra-select" style="width:100%;" required>
                        <option value="">— Choose an event —</option>
                        <?php foreach ($eventTypes as $slug => $label): ?>
                            <option value="<?= e($slug) ?>"><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="ra-label">Recipient type</label>
                    <select name="recipient_type" class="ra-input ra-select" style="width:100%;" required>
                        <option value="">— Choose —</option>
                        <?php foreach ($recipientTypes as $slug => $label): ?>
                            <option value="<?= e($slug) ?>"><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="ra-label">Recipient value (for role / permission / user)</label>
                    <input type="text" name="recipient_value" class="ra-input"
                           placeholder="e.g. super_admin, candidates.view, 42">
                </div>
                <div class="col-md-6">
                    <label class="ra-label">Channels</label>
                    <label style="display:flex;align-items:center;gap:.5rem;margin-top:.4rem;">
                        <input type="checkbox" name="channel_inapp" value="1" checked>
                        <span>In-app (bell)</span>
                    </label>
                    <label style="display:flex;align-items:center;gap:.5rem;margin-top:.3rem;">
                        <input type="checkbox" name="channel_email" value="1">
                        <span>Email (daily digest)</span>
                    </label>
                </div>
            </div>

            <div class="mt-3">
                <button type="submit" class="ra-btn ra-btn-primary">
                    <i class="bi bi-plus-lg"></i>
                    <span>Add rule</span>
                </button>
            </div>
        </form>
    </div>
<?php endif; ?>

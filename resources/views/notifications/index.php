<div class="ra-page-band">
    <div class="ra-page-band-row">
        <div>
            <div class="ra-page-band-label">Notifications</div>
            <h1 class="ra-page-band-title">All Notifications</h1>
            <p class="ra-page-sub">Recent activity and alerts on your account.</p>
        </div>
        <div class="ra-page-band-actions">
            <form method="POST" action="/notifications/read-all" style="margin:0;">
                <?= csrf_field() ?>
                <button type="submit" class="ra-btn ra-btn-outline">
                    <i class="bi bi-check2-all"></i>
                    <span>Mark all as read</span>
                </button>
            </form>
        </div>
    </div>
</div>

<?php if (empty($notifications)): ?>
    <div class="ra-card text-center muted" style="padding:3rem;">
        <div style="font-size:3rem;opacity:.3;">
            <i class="bi bi-bell-slash"></i>
        </div>
        <div class="mt-2">No notifications yet.</div>
        <div class="small mt-1">When there's activity on your account, you'll see it here.</div>
    </div>
<?php else: ?>
    <div class="ra-card p-0">
        <?php foreach ($notifications as $n): ?>
            <div class="d-flex gap-3 p-3"
                 style="border-bottom:1px solid var(--ra-border);<?= $n->isRead() ? '' : 'background:rgba(15,76,129,.06);' ?>">
                <div style="font-size:1.25rem;color:var(--ra-primary);flex-shrink:0;">
                    <i class="bi <?= e($n->icon()) ?>"></i>
                </div>
                <div style="flex:1;min-width:0;">
                    <div class="d-flex justify-content-between align-items-start gap-3">
                        <div style="flex:1;min-width:0;">
                            <?php if ($n->link): ?>
                                <a href="<?= e($n->link) ?>" class="text-decoration-none">
                                    <strong><?= e($n->title) ?></strong>
                                </a>
                            <?php else: ?>
                                <strong><?= e($n->title) ?></strong>
                            <?php endif; ?>
                            <?php if ($n->body): ?>
                                <div class="muted small mt-1"><?= e($n->body) ?></div>
                            <?php endif; ?>
                            <div class="muted small mt-1" style="font-size:.72rem;">
                                <?= e($n->relativeTime()) ?>
                                <?php if ($n->createdAt): ?> · <?= e($n->createdAt) ?><?php endif; ?>
                            </div>
                        </div>
                        <?php if (!$n->isRead()): ?>
                            <form method="POST" action="/notifications/<?= (int) $n->id ?>/read" style="margin:0;">
                                <?= csrf_field() ?>
                                <button type="submit" class="ra-row-actions" title="Mark as read">
                                    <i class="bi bi-check2"></i>
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

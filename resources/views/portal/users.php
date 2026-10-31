<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0" style="color:var(--ra-text-strong);">User Management</h1>
        <p class="muted mb-0">Assign users to applications and set their default workspace.</p>
    </div>
    <a href="/" class="ra-btn ra-btn-outline"><i class="bi bi-arrow-left"></i> Back to Portal</a>
</div>

<div class="ra-card p-0" style="overflow:hidden; background:var(--ra-surface);">
    <div style="display:grid; grid-template-columns: 2fr 1fr 1fr 1fr 1fr; padding: 1rem 1.5rem; background:var(--ra-surface-2); border-bottom:1px solid var(--ra-border); font-weight:500; font-size:.85rem; color:var(--ra-text-muted);">
        <div>User</div>
        <div>Status</div>
        <div class="text-center">Recruit Aid</div>
        <div class="text-center">HR Aid</div>
        <div class="text-end">Actions</div>
    </div>

    <?php foreach ($users as $user): ?>
        <?php 
            $uid = \CorporateAid\Core\Row::int($user, 'id');
            $apps = $userApps[$uid] ?? [];
            $name = \CorporateAid\Core\Row::str($user, 'name');
            $email = \CorporateAid\Core\Row::str($user, 'email');
            $status = \CorporateAid\Core\Row::str($user, 'status');
        ?>
        <form method="POST" action="/users/update" style="display:grid; grid-template-columns: 2fr 1fr 1fr 1fr 1fr; padding: 1rem 1.5rem; align-items:center; border-bottom:1px solid var(--ra-border); margin:0;">
            <?= csrf_field() ?>
            <input type="hidden" name="user_id" value="<?= $uid ?>">
            
            <div>
                <div style="font-weight:500; color:var(--ra-text-strong);"><?= e($name) ?></div>
                <div class="muted" style="font-size:.85rem;"><?= e($email) ?></div>
            </div>
            <div>
                <span class="ra-badge <?= $status === 'active' ? 'ra-badge-success' : '' ?>">
                    <?= e(ucfirst($status)) ?>
                </span>
            </div>
            <div class="text-center">
                <input type="checkbox" name="recruit" value="1" class="form-check-input" style="transform: scale(1.2); cursor:pointer;" <?= isset($apps['recruit']) ? 'checked' : '' ?>>
            </div>
            <div class="text-center">
                <input type="checkbox" name="hr" value="1" class="form-check-input" style="transform: scale(1.2); cursor:pointer;" <?= isset($apps['hr']) ? 'checked' : '' ?>>
            </div>
            <div class="text-end">
                <button type="submit" class="ra-btn ra-btn-primary" style="padding: 0.25rem 1rem; font-size: 0.85rem;">Save</button>
            </div>
        </form>
    <?php endforeach; ?>
</div>

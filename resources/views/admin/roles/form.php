<div class="ra-page-band">
    <div class="ra-page-band-row">
        <div>
            <div class="ra-page-band-label">Administration · Roles &amp; Permissions</div>
            <h1 class="ra-page-band-title"><?= e($title) ?></h1>
        </div>
        <div class="ra-page-band-actions">
            <a href="/admin/roles-perm/roles" class="ra-btn ra-btn-outline">
                <i class="bi bi-arrow-left"></i>
                <span>Back</span>
            </a>
        </div>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="ra-alert ra-alert-error mb-3">
        <?php foreach ($errors as $err): ?>
            <div><?= e((string) $err) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<form method="POST" action="<?= $role ? '/admin/roles-perm/roles/' . (int) $role['id'] : '/admin/roles-perm/roles' ?>">
    <?= csrf_field() ?>
    <?php if ($role): ?>
        <?= method_field('PUT') ?>
    <?php endif; ?>

    <div class="ra-card mb-3">
        <h2 class="h5 mb-3">Role Details</h2>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="ra-label" for="role-name">Name</label>
                <input type="text" id="role-name" name="name" class="ra-input" required
                       value="<?= e((string) ($role['name'] ?? '')) ?>">
                <?php if ($role): ?>
                    <div class="muted small mt-1">Slug: <code><?= e((string) $role['slug']) ?></code> (immutable)</div>
                <?php endif; ?>
            </div>
            <div class="col-md-6">
                <label class="ra-label" for="role-desc">Description</label>
                <input type="text" id="role-desc" name="description" class="ra-input"
                       value="<?= e((string) ($role['description'] ?? '')) ?>">
            </div>
        </div>
    </div>

    <div class="ra-alert ra-alert-info mb-3">
        <i class="bi bi-info-circle"></i>
        To assign permissions to this role, go to the <a href="/admin/roles-perm/permissions">Permissions Matrix</a>.
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="ra-btn ra-btn-primary">Save Role</button>
        <a href="/admin/roles-perm/roles" class="ra-btn ra-btn-outline">Cancel</a>
    </div>
</form>

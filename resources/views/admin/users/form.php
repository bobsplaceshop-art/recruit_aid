<div class="ra-page-band">
    <div class="ra-page-band-row">
        <div>
            <div class="ra-page-band-label">Administration · Users</div>
            <h1 class="ra-page-band-title"><?= e($title) ?></h1>
        </div>
        <div class="ra-page-band-actions">
            <a href="/admin/users" class="ra-btn ra-btn-outline">
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

<form method="POST" action="<?= $editing ? '/admin/users/' . (int) $editing->id : '/admin/users' ?>">
    <?= csrf_field() ?>
    <?php if ($editing): ?>
        <?= method_field('PUT') ?>
    <?php endif; ?>

    <div class="ra-card mb-3">
        <h2 class="h5 mb-3">User Details</h2>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="ra-label" for="user-name">Name</label>
                <input type="text" id="user-name" name="name" class="ra-input" required
                       value="<?= e($editing?->name ?? '') ?>">
            </div>
            <div class="col-md-6">
                <label class="ra-label" for="user-email">Email</label>
                <input type="email" id="user-email" name="email" class="ra-input" required
                       value="<?= e($editing?->email ?? '') ?>">
            </div>
            <div class="col-md-6">
                <label class="ra-label" for="user-password">
                    Password
                    <?php if ($editing): ?>
                        <span class="muted small">(leave blank to keep current)</span>
                    <?php endif; ?>
                </label>
                <input type="password" id="user-password" name="password" class="ra-input"
                       <?= $editing ? '' : 'required' ?> minlength="8">
            </div>
            <div class="col-md-6">
                <label class="ra-label" for="user-status">Status</label>
                <select id="user-status" name="status" class="ra-select">
                    <?php foreach (['pending', 'active', 'suspended', 'disabled'] as $s): ?>
                        <option value="<?= $s ?>" <?= ($editing?->status ?? 'active') === $s ? 'selected' : '' ?>>
                            <?= e(ucfirst($s)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <div class="ra-card mb-3">
        <h2 class="h5 mb-3">Roles</h2>
        <p class="muted small mb-3">Tick any roles this user should have. Their permissions are the union of all roles' permissions.</p>

        <?php if (empty($allRoles)): ?>
            <div class="ra-alert ra-alert-info">
                No roles exist yet. <a href="/admin/roles-perm/roles/create">Create a role first</a>.
            </div>
        <?php else: ?>
            <div class="row g-2">
                <?php foreach ($allRoles as $role): ?>
                    <?php $rid = (int) $role['id']; ?>
                    <div class="col-md-6">
                        <label class="d-flex align-items-start gap-2" style="cursor:pointer;">
                            <input type="checkbox" name="roles[]" value="<?= $rid ?>"
                                <?= in_array($rid, $selectedRoles, true) ? 'checked' : '' ?>
                                style="margin-top:.25rem;">
                            <span>
                                <strong><?= e((string) $role['name']) ?></strong>
                                <?php if (!empty($role['is_system'])): ?>
                                    <span class="ra-badge ms-1">system</span>
                                <?php endif; ?>
                                <br>
                                <span class="muted small"><?= e((string) ($role['description'] ?? $role['slug'])) ?></span>
                            </span>
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="ra-btn ra-btn-primary">
            <?= $editing ? 'Save Changes' : 'Create User' ?>
        </button>
        <a href="/admin/users" class="ra-btn ra-btn-outline">Cancel</a>
    </div>
</form>

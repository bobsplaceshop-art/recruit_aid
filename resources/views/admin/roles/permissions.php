<div class="ra-page-band">
    <div class="ra-page-band-row">
        <div>
            <div class="ra-page-band-label">Administration · Roles &amp; Permissions</div>
            <h1 class="ra-page-band-title">Permissions Matrix</h1>
        </div>
        <div class="ra-page-band-actions">
            <a href="/admin/roles-perm" class="ra-btn ra-btn-outline">
                <i class="bi bi-arrow-left"></i>
                <span>Back</span>
            </a>
        </div>
    </div>
</div>

<div class="ra-card mb-3">
    <form method="GET" action="/admin/roles-perm/permissions" class="d-flex align-items-center gap-3">
        <label for="role-select" class="ra-label m-0" style="white-space:nowrap;">Select Role:</label>
        <select id="role-select" name="role_id" class="ra-select" onchange="this.form.submit()" style="width:auto; min-width:250px;">
            <?php if (empty($roles)): ?>
                <option value="">No roles defined</option>
            <?php else: ?>
                <?php foreach ($roles as $role): ?>
                    <option value="<?= (int) $role['id'] ?>" <?= (int) $role['id'] === $selectedRoleId ? 'selected' : '' ?>>
                        <?= e((string) $role['name']) ?> (<?= e((string) $role['slug']) ?>)
                    </option>
                <?php endforeach; ?>
            <?php endif; ?>
        </select>
    </form>
</div>

<?php if ($selectedRoleId > 0): ?>
    <form method="POST" action="/admin/roles-perm/permissions">
        <?= csrf_field() ?>
        <input type="hidden" name="role_id" value="<?= (int) $selectedRoleId ?>">

        <div class="ra-card mb-3">
            <h2 class="h5 mb-3">Assign Permissions</h2>
            <p class="muted small mb-3">Tick the permissions this role should have. Hover over any permission to see its technical slug.</p>

            <?php foreach ($permissions as $module => $perms): ?>
                <div class="mb-4">
                    <div class="ra-section-label mb-2"><?= e(ucfirst((string) $module)) ?></div>
                    <div class="row g-2">
                        <?php foreach ($perms as $perm): ?>
                            <?php 
                                $permId = (int) $perm['id'];
                                $permLabel = trim((string) ($perm['description'] ?? ''));
                                if ($permLabel === '') {
                                    $permLabel = (string) $perm['slug'];
                                }
                                $permSlug = (string) $perm['slug'];
                            ?>
                            <div class="col-md-6">
                                <label class="d-flex align-items-center gap-2" style="cursor:pointer;" title="<?= e($permSlug) ?>">
                                    <input type="checkbox" name="permissions[]" value="<?= $permId ?>"
                                        <?= in_array($permId, $selectedPermissions, true) ? 'checked' : '' ?>>
                                    <span><?= e($permLabel) ?></span>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="ra-btn ra-btn-primary">Save Permissions</button>
            <a href="/admin/roles-perm/roles" class="ra-btn ra-btn-outline">Cancel</a>
        </div>
    </form>
<?php else: ?>
    <div class="ra-alert ra-alert-info">
        Please create a role first before assigning permissions.
    </div>
<?php endif; ?>

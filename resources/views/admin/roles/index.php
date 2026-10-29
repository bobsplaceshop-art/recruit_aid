<div class="ra-page-band">
    <div class="ra-page-band-row">
        <div>
            <div class="ra-page-band-label">Administration · Roles &amp; Permissions</div>
            <h1 class="ra-page-band-title">Roles</h1>
        </div>
        <div class="ra-page-band-actions">
            <a href="/admin/roles-perm/roles/create" class="ra-btn ra-btn-primary">
                <i class="bi bi-plus-lg"></i>
                <span>Create Role</span>
            </a>
            <a href="/admin/roles-perm" class="ra-btn ra-btn-outline">
                <i class="bi bi-arrow-left"></i>
                <span>Back</span>
            </a>
        </div>
    </div>
</div>

<div class="ra-table-card">
    <table class="ra-table">
        <thead>
            <tr>
                <th style="width:60px;">#</th>
                <th>Role</th>
                <th>Slug</th>
                <th>Description</th>
                <th style="width:100px;">Type</th>
                <th style="width:160px;">Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($roles as $i => $role): ?>
            <tr>
                <td class="muted"><?= e((string) ($i + 1)) ?></td>
                <td><?= e((string) $role['name']) ?></td>
                <td><code style="color:var(--ra-muted);font-size:.85rem;"><?= e((string) $role['slug']) ?></code></td>
                <td class="muted small"><?= e((string) ($role['description'] ?? '—')) ?></td>
                <td>
                    <?php if (!empty($role['is_system'])): ?>
                        <span class="ra-badge">system</span>
                    <?php else: ?>
                        <span class="ra-badge ra-badge-success">custom</span>
                    <?php endif; ?>
                </td>
                <td>
                    <a href="/admin/roles-perm/roles/<?= (int) $role['id'] ?>/edit" class="ra-btn ra-btn-outline" style="padding:.25rem .5rem;font-size:.85rem;">Edit</a>
                    <?php if (empty($role['is_system'])): ?>
                        <form method="POST" action="/admin/roles-perm/roles/<?= (int) $role['id'] ?>" style="display:inline;" onsubmit="return confirm('Delete this role?');">
                            <?= csrf_field() ?>
                            <?= method_field('DELETE') ?>
                            <button type="submit" class="ra-btn ra-btn-outline" style="padding:.25rem .5rem;font-size:.85rem;color:var(--ra-error-fg);">Delete</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

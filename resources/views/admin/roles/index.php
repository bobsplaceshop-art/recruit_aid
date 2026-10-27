<div class="ra-page-band">
    <div class="ra-page-band-row">
        <div>
            <div class="ra-page-band-label">Administration · Roles &amp; Permissions</div>
            <h1 class="ra-page-band-title">Roles</h1>
        </div>
        <div class="ra-page-band-actions">
            <a href="/admin/roles-perm" class="ra-btn ra-btn-outline">
                <i class="bi bi-arrow-left"></i>
                <span>Back</span>
            </a>
        </div>
    </div>
</div>

<div class="ra-table-card">
    <div class="ra-table-toolbar">
        <div class="ra-table-toolbar-left">
            <span class="ra-table-toolbar-label">Show</span>
            <select class="ra-select" disabled>
                <option>50</option>
            </select>
            <span class="ra-table-toolbar-label">entries</span>
        </div>
        <div class="ra-table-toolbar-right">
            <input type="text" class="ra-input" placeholder="Search…" style="width:200px;" disabled>
        </div>
    </div>

    <table class="ra-table">
        <thead>
            <tr>
                <th style="width:60px;">#</th>
                <th>Role</th>
                <th>Slug</th>
                <th>Description</th>
                <th style="width:100px;">Type</th>
                <th style="width:120px;">Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($roles as $i => $role): ?>
            <tr>
                <td class="muted"><?= e((string) ($i + 1)) ?></td>
                <td><?= e($role['name']) ?></td>
                <td><code style="color:var(--ra-muted);font-size:.85rem;"><?= e($role['slug']) ?></code></td>
                <td class="muted small"><?= e($role['description'] ?? '—') ?></td>
                <td>
                    <?php if (!empty($role['is_system'])): ?>
                        <span class="ra-badge">system</span>
                    <?php else: ?>
                        <span class="ra-badge ra-badge-success">custom</span>
                    <?php endif; ?>
                </td>
                <td>
                    <button type="button" class="ra-row-actions" disabled>
                        Actions <i class="bi bi-chevron-down"></i>
                    </button>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

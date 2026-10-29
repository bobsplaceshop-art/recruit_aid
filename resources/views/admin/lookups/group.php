<div class="ra-page-band">
    <div class="ra-page-band-row">
        <div>
            <div class="ra-page-band-label">Administration · Lookups</div>
            <h1 class="ra-page-band-title"><?= e($groupLabel) ?></h1>
        </div>
        <div class="ra-page-band-actions">
            <a href="/admin/lookups/<?= e($group) ?>/create" class="ra-btn ra-btn-primary">
                <i class="bi bi-plus-lg"></i>
                <span>New Value</span>
            </a>
            <a href="/admin/lookups" class="ra-btn ra-btn-outline">
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
                <th style="width:60px;">Order</th>
                <th>Label</th>
                <th>Slug</th>
                <th>Description</th>
                <th style="width:100px;">Type</th>
                <th style="width:100px;">Active</th>
                <th style="width:140px;">Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($items)): ?>
            <tr><td colspan="7" class="text-center muted" style="padding:2.5rem;">No values yet.</td></tr>
        <?php else: ?>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td class="muted"><?= (int) $item['sort_order'] ?></td>
                    <td><strong><?= e($item['label']) ?></strong></td>
                    <td><code style="color:var(--ra-muted);"><?= e($item['slug']) ?></code></td>
                    <td class="muted small"><?= e($item['description'] ?? '—') ?></td>
                    <td>
                        <?php if ($item['is_system']): ?>
                            <span class="ra-badge">system</span>
                        <?php else: ?>
                            <span class="ra-badge ra-badge-success">custom</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($item['is_active']): ?>
                            <span class="ra-badge ra-badge-success">Active</span>
                        <?php else: ?>
                            <span class="ra-badge">Inactive</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="/admin/lookups/<?= e($group) ?>/<?= (int) $item['id'] ?>/edit" class="ra-btn ra-btn-outline" style="padding:.25rem .5rem;font-size:.85rem;">Edit</a>
                        <?php if (!$item['is_system']): ?>
                            <form method="POST" action="/admin/lookups/<?= e($group) ?>/<?= (int) $item['id'] ?>" style="display:inline;" onsubmit="return confirm('Delete this value?');">
                                <?= csrf_field() ?>
                                <?= method_field('DELETE') ?>
                                <button type="submit" class="ra-btn ra-btn-outline" style="padding:.25rem .5rem;font-size:.85rem;color:var(--ra-error-fg);">Delete</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

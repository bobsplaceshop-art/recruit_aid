<div class="ra-page-band">
    <div class="ra-page-band-row">
        <div>
            <div class="ra-page-band-label">Administration · Lookups · <?= e($groupLabel) ?></div>
            <h1 class="ra-page-band-title"><?= e($title) ?></h1>
        </div>
        <div class="ra-page-band-actions">
            <a href="/admin/lookups/<?= e($group) ?>" class="ra-btn ra-btn-outline">
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

<form method="POST" action="<?= $item && isset($item['id']) ? '/admin/lookups/' . e($group) . '/' . (int) $item['id'] : '/admin/lookups/' . e($group) ?>">
    <?= csrf_field() ?>
    <?php if ($item && isset($item['id'])): ?>
        <?= method_field('PUT') ?>
    <?php endif; ?>

    <div class="ra-card mb-3">
        <div class="row g-3">
            <?php if (!$item || !isset($item['id'])): ?>
                <div class="col-md-6">
                    <label class="ra-label" for="lk-slug">Slug <span style="color:var(--ra-error-fg)">*</span></label>
                    <input type="text" id="lk-slug" name="slug" class="ra-input" required
                           pattern="^[a-z0-9_]+$"
                           value="<?= e((string) ($item['slug'] ?? '')) ?>"
                           placeholder="e.g. permanent">
                    <div class="muted small mt-1">Lowercase letters, numbers, underscore. Cannot be changed later.</div>
                </div>
            <?php else: ?>
                <div class="col-md-6">
                    <label class="ra-label">Slug</label>
                    <div><code><?= e((string) $item['slug']) ?></code> <span class="muted small">(immutable)</span></div>
                </div>
            <?php endif; ?>
            <div class="col-md-6">
                <label class="ra-label" for="lk-label">Label <span style="color:var(--ra-error-fg)">*</span></label>
                <input type="text" id="lk-label" name="label" class="ra-input" required
                       value="<?= e((string) ($item['label'] ?? '')) ?>"
                       placeholder="e.g. Permanent">
            </div>
            <div class="col-md-6">
                <label class="ra-label" for="lk-sort">Sort Order</label>
                <input type="number" id="lk-sort" name="sort_order" class="ra-input"
                       value="<?= e((string) ($item['sort_order'] ?? 0)) ?>">
                <div class="muted small mt-1">Lower numbers appear first in dropdowns.</div>
            </div>
            <div class="col-md-6">
                <label class="ra-label" for="lk-active">Active</label>
                <label class="d-flex align-items-center gap-2" style="cursor:pointer;">
                    <input type="checkbox" id="lk-active" name="is_active" value="1" <?= (!isset($item['is_active']) || $item['is_active']) ? 'checked' : '' ?>>
                    <span>Show this value in dropdowns</span>
                </label>
            </div>
            <div class="col-12">
                <label class="ra-label" for="lk-desc">Description</label>
                <textarea id="lk-desc" name="description" class="ra-input" rows="2"
                          placeholder="Optional internal note"><?= e((string) ($item['description'] ?? '')) ?></textarea>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="ra-btn ra-btn-primary"><?= $item && isset($item['id']) ? 'Save Changes' : 'Create Value' ?></button>
        <a href="/admin/lookups/<?= e($group) ?>" class="ra-btn ra-btn-outline">Cancel</a>
    </div>
</form>

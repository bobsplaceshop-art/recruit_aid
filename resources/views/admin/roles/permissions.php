<div class="ra-page-band">
    <div class="ra-page-band-row">
        <div>
            <div class="ra-page-band-label">Administration · Roles &amp; Permissions</div>
            <h1 class="ra-page-band-title">Permissions</h1>
        </div>
        <div class="ra-page-band-actions">
            <a href="/admin/roles-perm" class="ra-btn ra-btn-outline">
                <i class="bi bi-arrow-left"></i>
                <span>Back</span>
            </a>
        </div>
    </div>
</div>

<div class="ra-alert ra-alert-warn">
    The permissions matrix is coming in the next step. It will show every role as a column and
    every permission as a row, with a toggle for each combination.
</div>

<div class="ra-table-card">
    <div class="ra-table-title">Current permissions</div>
    <?php foreach ($permissions as $module => $items): ?>
        <div style="padding:1rem 1.25rem;border-bottom:1px solid var(--ra-border-soft);">
            <div class="ra-section-label mb-2"><?= e($module) ?></div>
            <div class="d-flex flex-wrap gap-2">
                <?php foreach ($items as $perm): ?>
                    <span class="ra-badge"><?= e($perm['slug']) ?></span>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

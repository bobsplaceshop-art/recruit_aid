<div class="ra-page-band">
    <div class="ra-page-band-row">
        <div>
            <div class="ra-page-band-label">Administration · Lookups</div>
            <h1 class="ra-page-band-title">Lookup Values</h1>
            <p class="ra-page-sub">Configurable options used across the app. Customers can add, rename, or deactivate values without code changes.</p>
        </div>
    </div>
</div>

<div class="row g-3">
    <?php if (empty($groups)): ?>
        <div class="col-12">
            <div class="ra-alert ra-alert-info">No lookup groups exist yet.</div>
        </div>
    <?php else: ?>
        <?php foreach ($groups as $g): ?>
            <div class="col-md-6 col-lg-4">
                <a href="/admin/lookups/<?= e($g['group_slug']) ?>" class="ra-card text-decoration-none d-block h-100" style="color:inherit;">
                    <div style="font-size:2rem;color:var(--ra-primary);"><i class="bi bi-list-ul"></i></div>
                    <h3 class="h5 mt-2 mb-1"><?= e(ucwords(str_replace('_', ' ', $g['group_slug']))) ?></h3>
                    <p class="muted small mb-0"><?= (int) $g['item_count'] ?> value<?= $g['item_count'] === 1 ? '' : 's' ?></p>
                </a>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

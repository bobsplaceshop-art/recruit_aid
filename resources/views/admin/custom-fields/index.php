<div class="ra-page-band">
    <div class="ra-page-band-row">
        <div>
            <div class="ra-page-band-label">Administration</div>
            <h1 class="ra-page-band-title">Custom Fields</h1>
        </div>
        <div class="ra-page-band-actions">
            <a href="/admin" class="ra-btn ra-btn-outline">
                <i class="bi bi-arrow-left"></i>
                <span>Back</span>
            </a>
        </div>
    </div>
</div>

<p class="muted mb-4">
    Add your own fields to any entity. Fields you create here appear on the corresponding
    create, edit, and detail pages automatically.
</p>

<div class="ra-tiles">
    <?php foreach ($counts as $key => $info): ?>
        <a href="/admin/custom-fields/<?= e($key) ?>" class="ra-tile">
            <div class="ra-tile-icon ra-tile-icon--sky">
                <i class="bi bi-input-cursor-text"></i>
            </div>
            <div class="ra-tile-label">
                <?= e($info['label']) ?>
            </div>
            <div class="muted small">
                <?= e((string) $info['active']) ?> active
                <?php if ($info['total'] > $info['active']): ?>
                    · <?= e((string) ($info['total'] - $info['active'])) ?> archived
                <?php endif; ?>
            </div>
        </a>
    <?php endforeach; ?>
</div>

<div class="ra-card">
    <div class="ra-section-label mb-2">How it works</div>
    <p class="muted small mb-2">
        <strong>Pick a type, and the field validates itself.</strong> Choose "Date" and the
        system enforces a valid date. Choose "Number" and it rejects non-numeric input.
        Choose "Dropdown" and it only accepts the options you list.
    </p>
    <p class="muted small mb-0">
        Fields are <strong>archived</strong> if they hold data, not deleted. Archived
        fields stop appearing on forms but keep their historical values.
    </p>
</div>

<div class="ra-page-band">
    <div class="ra-page-band-row">
        <div>
            <div class="ra-page-band-label">Search</div>
            <h1 class="ra-page-band-title">
                <?= $term === '' ? 'Global Search' : 'Results for "' . e($term) . '"' ?>
            </h1>
        </div>
    </div>
</div>

<form method="GET" action="/search" class="ra-card mb-3">
    <div class="d-flex gap-2">
        <input type="text" name="q" class="ra-input" value="<?= e($term) ?>"
               placeholder="Search organisations, contacts, engagements, vacancies…"
               autofocus>
        <button type="submit" class="ra-btn ra-btn-primary">
            <i class="bi bi-search"></i>
            <span>Search</span>
        </button>
    </div>
</form>

<?php if ($term === ''): ?>
    <div class="ra-alert ra-alert-info">
        Enter a search term above. Searches across every entity you can view.
    </div>
<?php elseif ($total === 0): ?>
    <div class="ra-alert ra-alert-info">
        No results found for "<strong><?= e($term) ?></strong>".
    </div>
<?php else: ?>
    <p class="muted small mb-3"><?= (int) $total ?> result<?= $total === 1 ? '' : 's' ?> across <?= count($groups) ?> categor<?= count($groups) === 1 ? 'y' : 'ies' ?>.</p>

    <?php foreach ($groups as $group): ?>
        <div class="ra-card mb-3">
            <h2 class="h5 mb-3"><?= e($group['label']) ?> <span class="muted" style="font-weight:normal;font-size:.9rem;">(<?= count($group['items']) ?>)</span></h2>
            <div class="d-flex flex-column gap-2">
                <?php foreach ($group['items'] as $item): ?>
                    <a href="<?= e($item['url']) ?>" class="d-flex align-items-center justify-content-between text-decoration-none" style="color:inherit;padding:.5rem .75rem;border:1px solid var(--ra-border);border-radius:.5rem;">
                        <div style="min-width:0;">
                            <div style="font-weight:600;"><?= e($item['title']) ?></div>
                            <div class="muted small"><?= e($item['subtitle']) ?></div>
                        </div>
                        <div class="muted small text-nowrap ms-3"><?= e($item['meta']) ?></div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

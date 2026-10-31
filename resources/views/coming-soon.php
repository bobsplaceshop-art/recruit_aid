<div class="ra-page-band">
    <div class="ra-page-band-row">
        <div>
            <div class="ra-page-band-label">Coming Soon</div>
            <h1 class="ra-page-band-title"><?= e($featureLabel) ?></h1>
        </div>
        <div class="ra-page-band-actions">
            <a href="/recruit/dashboard" class="ra-btn ra-btn-outline">
                <i class="bi bi-house-door"></i>
                <span>Back to Dashboard</span>
            </a>
        </div>
    </div>
</div>

<div class="ra-card" style="max-width:720px;margin:2rem auto;text-align:center;padding:3rem 2rem;">
    <div style="font-size:4rem;color:var(--ra-primary);">
        <i class="bi bi-hourglass-split"></i>
    </div>
    <h2 class="h3 mt-3 mb-2"><?= e($featureLabel) ?> is on the way</h2>
    <p class="muted mb-4" style="max-width:520px;margin:0 auto;">
        <?= e($description) ?>
    </p>
    <div class="mt-4">
        <a href="/recruit/dashboard" class="ra-btn ra-btn-primary">
            <i class="bi bi-house-door"></i>
            <span>Back to Dashboard</span>
        </a>
    </div>
</div>

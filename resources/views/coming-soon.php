<?php
/** @var string $title */
/** @var string $description */
?>
<div class="ra-card" style="padding: 3rem; text-align: center; max-width: 640px; margin: 2rem auto;">
    <div style="font-size: 3.5rem; color: var(--ra-primary); margin-bottom: 1rem;">
        <i class="bi bi-tools"></i>
    </div>
    <h1 class="h3 mb-3" style="color: var(--ra-text-strong);"><?= e($title) ?></h1>
    <p class="muted mb-4" style="font-size: 1.05rem; line-height: 1.6;"><?= e($description) ?></p>
    <div class="d-flex gap-2 justify-content-center flex-wrap">
        <a href="/recruit/dashboard" class="ra-btn ra-btn-primary">
            <i class="bi bi-house-door"></i>
            <span>Back to dashboard</span>
        </a>
        <a href="javascript:history.back()" class="ra-btn ra-btn-outline">
            <i class="bi bi-arrow-left"></i>
            <span>Go back</span>
        </a>
    </div>
</div>

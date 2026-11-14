<?php /** @var \CorporateAid\Platform\Modules\Contracts\ModuleContract[] $modules */ ?>
<?php /** @var array<string, array<string, mixed>> $metrics */ ?>
<?php /** @var array<string, bool> $canAccess */ ?>
<?php /** @var array<string, bool> $enabled */ ?>
<div class="row g-4 mt-2">
    <div class="col-12 mb-3">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="h3" style="color:var(--ra-text-strong);">Welcome to CAS Suite</h1>
                <p class="muted">Select a module to begin working.</p>
            </div>
        </div>
    </div>

    <?php foreach ($modules as $key => $module): ?>
        <?php
            $isEnabled = $enabled[$key]   ?? false;
            $hasAccess = $canAccess[$key] ?? false;
            $tileState = !$isEnabled
                ? 'coming_soon'
                : ($hasAccess ? 'active' : 'no_access');

            $icon = match($key) {
                'recruit' => 'bi-people',
                'hr'      => 'bi-person-badge',
                default   => 'bi-box',
            };
            $moduleMetrics = $metrics[$key] ?? [];
        ?>
        <div class="col-md-6 col-lg-4">
            <?php if ($tileState === 'active'): ?>
                <a href="/<?= e($key) ?>/dashboard" class="ra-card d-block text-decoration-none" style="padding:2rem; transition:transform 0.2s; border:1px solid var(--ra-border);">
            <?php else: ?>
                <div class="ra-card d-block text-decoration-none" style="padding:2rem; border:1px solid var(--ra-border); opacity:0.65; cursor:default;">
            <?php endif; ?>

                <div class="d-flex align-items-center mb-3">
                    <div style="font-size:2rem; color:var(--ra-primary); margin-right:1rem;">
                        <i class="bi <?= e($icon) ?>"></i>
                    </div>
                    <div>
                        <h2 class="h4 mb-1" style="color:var(--ra-text-strong);"><?= e($module->getName()) ?></h2>
                        <span class="badge bg-secondary">v<?= e($module->getVersion()) ?></span>
                        <?php if ($tileState === 'coming_soon'): ?>
                            <span class="badge bg-warning text-dark">Coming soon</span>
                        <?php elseif ($tileState === 'no_access'): ?>
                            <span class="badge bg-danger">No access</span>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (!empty($moduleMetrics)): ?>
                    <div class="d-flex gap-4 mt-4 pt-3" style="border-top:1px solid var(--ra-border);">
                        <?php foreach ($moduleMetrics as $label => $value): ?>
                            <div>
                                <div class="h5 mb-0" style="color:var(--ra-text-strong);"><?= e((string) $value) ?></div>
                                <div class="muted" style="font-size:.8rem;"><?= e($label) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="muted mb-0 mt-3">
                        <?php if ($tileState === 'coming_soon'): ?>
                            This module is not yet enabled.
                        <?php elseif ($tileState === 'no_access'): ?>
                            You don't have access to this tenant's data. Ask a platform owner or tenant admin.
                        <?php else: ?>
                            Access the <?= e($module->getName()) ?> workspace.
                        <?php endif; ?>
                    </p>
                <?php endif; ?>

            <?php if ($tileState === 'active'): ?>
                </a>
            <?php else: ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>

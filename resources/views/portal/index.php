<?php /** @var \CorporateAid\Platform\Modules\Contracts\ModuleContract[] $modules */ ?>
<?php /** @var array<string, array<string, mixed>> $metrics */ ?>
<div class="row g-4 mt-2">
    <div class="col-12 mb-3">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="h3" style="color:var(--ra-text-strong);">Welcome to CAS Suite</h1>
                <p class="muted">Select a module to begin working.</p>
            </div>
            <a href="/users" class="ra-btn ra-btn-outline"><i class="bi bi-people"></i> Manage Users</a>
        </div>
    </div>

    <?php foreach ($modules as $key => $module): ?>
        <?php
            $url = match($key) {
                'recruit' => '/recruit/dashboard',
                'hr'      => '/hr/dashboard',
                default   => '/#' . $key,
            };
            $icon = match($key) {
                'recruit' => 'bi-people',
                'hr'      => 'bi-person-badge',
                default   => 'bi-box',
            };
            $moduleMetrics = $metrics[$key] ?? [];
        ?>
        <div class="col-md-6 col-lg-4">
            <a href="<?= e($url) ?>" class="ra-card d-block text-decoration-none" style="padding:2rem; transition:transform 0.2s; border:1px solid var(--ra-border);">
                <div class="d-flex align-items-center mb-3">
                    <div style="font-size:2rem; color:var(--ra-primary); margin-right:1rem;">
                        <i class="bi <?= e($icon) ?>"></i>
                    </div>
                    <div>
                        <h2 class="h4 mb-1" style="color:var(--ra-text-strong);"><?= e($module->getName()) ?></h2>
                        <span class="badge bg-secondary">v<?= e($module->getVersion()) ?></span>
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
                    <p class="muted mb-0 mt-3">Access the <?= e($module->getName()) ?> workspace.</p>
                <?php endif; ?>
            </a>
        </div>
    <?php endforeach; ?>
</div>

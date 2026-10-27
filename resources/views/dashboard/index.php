<div class="ra-page-band">
    <div class="ra-page-band-row">
        <div>
            <div class="ra-page-band-label">Dashboard</div>
            <h1 class="ra-page-band-title">Welcome, <?= e($user->firstName() ?: $user->name) ?></h1>
            <p class="ra-page-sub"><?= e($user->email) ?></p>
        </div>
        <div class="ra-page-band-actions">
            <span class="ra-badge ra-badge-success">Signed in</span>
        </div>
    </div>
</div>

<!-- Summary cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <a href="/organisations" class="ra-card d-block text-decoration-none">
            <div class="muted small mb-1">Organisations</div>
            <div style="font-size:1.75rem;font-weight:700;color:var(--ra-text-strong);">
                <?= number_format($summary['organisations']) ?>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <a href="/organisations" class="ra-card d-block text-decoration-none">
            <div class="muted small mb-1">Contacts</div>
            <div style="font-size:1.75rem;font-weight:700;color:var(--ra-text-strong);">
                <?= number_format($summary['contacts']) ?>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <a href="/engagements" class="ra-card d-block text-decoration-none">
            <div class="muted small mb-1">Engagements</div>
            <div style="font-size:1.75rem;font-weight:700;color:var(--ra-text-strong);">
                <?= number_format($summary['engagements']) ?>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <a href="/vacancies" class="ra-card d-block text-decoration-none">
            <div class="muted small mb-1">Vacancies</div>
            <div style="font-size:1.75rem;font-weight:700;color:var(--ra-text-strong);">
                <?= number_format($summary['vacancies']) ?>
            </div>
        </a>
    </div>
</div>

<div class="row g-3 mb-4">
    <!-- Needs your attention column -->
    <div class="col-lg-8">
        <h2 class="h5 mb-3">
            <i class="bi bi-exclamation-circle me-1" style="color:var(--ra-warn-fg);"></i>
            Needs your attention
        </h2>

        <!-- Capacity alerts -->
        <?php if (!empty($capacityAlerts)): ?>
        <div class="ra-card mb-3">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <div class="small fw-bold">Engagements near capacity</div>
                    <div class="muted" style="font-size:.78rem;">
                        Reaching the agreed active-role limit
                    </div>
                </div>
                <a href="/engagements" class="ra-row-actions">View all</a>
            </div>
            <?php foreach ($capacityAlerts as $e): ?>
                <?php
                    $pct = $e['agreed_active_role_capacity'] > 0
                        ? min(100, (int) round(($e['used_capacity'] / $e['agreed_active_role_capacity']) * 100))
                        : 0;
                    $atCapacity = $e['used_capacity'] >= $e['agreed_active_role_capacity'];
                ?>
                <div class="d-flex align-items-center gap-3 mb-2 pb-2" style="border-bottom:1px solid var(--ra-border-soft);">
                    <div style="flex:1;min-width:0;">
                        <a href="/engagements/<?= e($e['code']) ?>" class="text-decoration-none">
                            <strong><?= e($e['name']) ?></strong>
                        </a>
                        <div class="muted" style="font-size:.75rem;">
                            <?= e($e['org_name']) ?> · <?= (int) $e['used_capacity'] ?> / <?= (int) $e['agreed_active_role_capacity'] ?> slots
                        </div>
                    </div>
                    <div style="width:120px;">
                        <div style="background:var(--ra-surface-3);border-radius:999px;height:6px;overflow:hidden;">
                            <div style="height:100%;width:<?= $pct ?>%;background:<?= $atCapacity ? 'var(--ra-error-fg)' : 'var(--ra-warn-fg)' ?>;"></div>
                        </div>
                    </div>
                    <span class="<?= $atCapacity ? 'ra-badge ra-badge-danger' : 'ra-badge ra-badge-warn' ?>">
                        <?= $atCapacity ? 'Full' : $pct . '%' ?>
                    </span>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Approaching deadline -->
        <?php if (!empty($deadlines)): ?>
        <div class="ra-card mb-3">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <div class="small fw-bold">Vacancies closing soon</div>
                    <div class="muted" style="font-size:.78rem;">
                        Target hire date in the next 30 days
                    </div>
                </div>
                <a href="/vacancies" class="ra-row-actions">View all</a>
            </div>
            <?php foreach ($deadlines as $v): ?>
                <div class="d-flex justify-content-between align-items-center mb-2 pb-2" style="border-bottom:1px solid var(--ra-border-soft);">
                    <div style="flex:1;min-width:0;">
                        <a href="/vacancies/<?= e($v['code']) ?>" class="text-decoration-none">
                            <strong><?= e($v['title']) ?></strong>
                        </a>
                        <div class="muted" style="font-size:.75rem;">
                            <?= e($v['org_name']) ?> · target <?= e($v['target_hire_date']) ?>
                        </div>
                    </div>
                    <span class="ra-badge <?= ((int) $v['days_until']) <= 7 ? 'ra-badge-danger' : 'ra-badge-warn' ?>">
                        <?= (int) $v['days_until'] ?> day<?= (int) $v['days_until'] === 1 ? '' : 's' ?>
                    </span>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Aging vacancies -->
        <?php if (!empty($aging)): ?>
        <div class="ra-card mb-3">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <div class="small fw-bold">Open longer than 30 days</div>
                    <div class="muted" style="font-size:.78rem;">
                        Worth reviewing — do these roles need re-scoping?
                    </div>
                </div>
                <a href="/vacancies" class="ra-row-actions">View all</a>
            </div>
            <?php foreach ($aging as $v): ?>
                <div class="d-flex justify-content-between align-items-center mb-2 pb-2" style="border-bottom:1px solid var(--ra-border-soft);">
                    <div style="flex:1;min-width:0;">
                        <a href="/vacancies/<?= e($v['code']) ?>" class="text-decoration-none">
                            <strong><?= e($v['title']) ?></strong>
                        </a>
                        <div class="muted" style="font-size:.75rem;">
                            <?= e($v['org_name']) ?> · opened <?= e($v['opening_date']) ?>
                        </div>
                    </div>
                    <span class="ra-badge"><?= (int) $v['days_open'] ?> days open</span>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (empty($capacityAlerts) && empty($deadlines) && empty($aging)): ?>
            <div class="ra-card text-center muted" style="padding:3rem 1.5rem;">
                <div style="font-size:2.5rem;opacity:.3;">
                    <i class="bi bi-check-circle"></i>
                </div>
                <div class="mt-2">Nothing needs your attention right now.</div>
                <div class="small mt-1">We'll show deadlines, aging vacancies, and capacity warnings here.</div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Side column: recent activity -->
    <div class="col-lg-4">
        <h2 class="h5 mb-3">
            <i class="bi bi-activity me-1" style="color:var(--ra-primary);"></i>
            Recent activity
        </h2>
        <div class="ra-card">
            <?php if (empty($recentActivity)): ?>
                <div class="muted small text-center py-3">No activity yet.</div>
            <?php else: ?>
                <?php foreach ($recentActivity as $a): ?>
                    <div class="mb-3 pb-2" style="border-bottom:1px solid var(--ra-border-soft);">
                        <div class="small"><?= e($a['description']) ?></div>
                        <div class="muted" style="font-size:.72rem;">
                            <?= e($a['module']) ?> · <?= e($a['created_at']) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

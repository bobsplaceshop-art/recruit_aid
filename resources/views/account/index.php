<div class="ra-page-header">
    <div>
        <div class="ra-breadcrumbs">
            <a href="/recruit/dashboard">Home</a>
            <span>/</span>
            <span>Account</span>
        </div>
        <h1 class="ra-page-title">Your account</h1>
        <p class="ra-page-sub">Your profile details and security settings.</p>
    </div>
</div>

<div class="ra-card">
    <div class="row g-4">
        <div class="col-md-6">
            <div class="muted small">Name</div>
            <div class="mb-3"><?= e($user->name) ?></div>

            <div class="muted small">Email</div>
            <div class="mb-3"><?= e($user->email) ?></div>

            <div class="muted small">Phone</div>
            <div class="mb-3"><?= e($user->phone ?? '—') ?></div>

            <div class="muted small">Timezone</div>
            <div class="mb-0"><?= e($user->timezone) ?></div>
        </div>
        <div class="col-md-6">
            <div class="muted small">Status</div>
            <div class="mb-3">
                <span class="ra-badge ra-badge-success"><?= e(ucfirst($user->status)) ?></span>
            </div>

            <div class="muted small">Roles</div>
            <div class="mb-3">
                <?php foreach ($user->roleSlugs as $slug): ?>
                    <span class="ra-badge me-1"><?= e($slug) ?></span>
                <?php endforeach; ?>
            </div>

            <div class="muted small">Last login</div>
            <div class="mb-0"><?= e($user->lastLoginAt ?? 'First login') ?></div>
        </div>
    </div>

    <hr style="border-color:var(--ra-border); margin:1.75rem 0;">

    <a href="/account/password" class="ra-btn ra-btn-primary">
        <i class="bi bi-shield-lock"></i>
        <span>Change password</span>
    </a>
</div>

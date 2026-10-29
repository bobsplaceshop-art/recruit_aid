<div class="ra-page-band">
    <div class="ra-page-band-row">
        <div>
            <div class="ra-page-band-label">Administration · Settings</div>
            <h1 class="ra-page-band-title">Settings</h1>
            <p class="ra-page-sub">Configure how <?= e(app_setting('branding.app_name', 'Recruit Aid')) ?> behaves for your organisation.</p>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-md-6 col-lg-4">
        <a href="/admin/settings/branding" class="ra-card text-decoration-none d-block h-100" style="color:inherit;">
            <div style="font-size:2rem;color:var(--ra-accent);"><i class="bi bi-palette"></i></div>
            <h3 class="h5 mt-2 mb-1">Branding</h3>
            <p class="muted small mb-0">App name, logo, favicon, primary color, and your organisation details.</p>
        </a>
    </div>
    <div class="col-md-6 col-lg-4">
        <a href="/admin/roles-perm" class="ra-card text-decoration-none d-block h-100" style="color:inherit;">
            <div style="font-size:2rem;color:var(--ra-accent);"><i class="bi bi-shield-lock"></i></div>
            <h3 class="h5 mt-2 mb-1">Roles &amp; Permissions</h3>
            <p class="muted small mb-0">Manage roles, assign permissions, and see who has what.</p>
        </a>
    </div>
    <div class="col-md-6 col-lg-4">
        <a href="/admin/custom-fields" class="ra-card text-decoration-none d-block h-100" style="color:inherit;">
            <div style="font-size:2rem;color:var(--ra-accent);"><i class="bi bi-input-cursor-text"></i></div>
            <h3 class="h5 mt-2 mb-1">Custom Fields</h3>
            <p class="muted small mb-0">Add fields to organisations, contacts, engagements, and vacancies.</p>
        </a>
    </div>
</div>

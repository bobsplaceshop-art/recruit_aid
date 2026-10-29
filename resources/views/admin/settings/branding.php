<div class="ra-page-band">
    <div class="ra-page-band-row">
        <div>
            <div class="ra-page-band-label">Administration · Settings · Branding</div>
            <h1 class="ra-page-band-title">Branding &amp; Identity</h1>
        </div>
        <div class="ra-page-band-actions">
            <a href="/admin/settings" class="ra-btn ra-btn-outline">
                <i class="bi bi-arrow-left"></i>
                <span>Back</span>
            </a>
        </div>
    </div>
</div>

<form method="POST" action="/admin/settings/branding" enctype="multipart/form-data">
    <?= csrf_field() ?>

    <div class="ra-card mb-3">
        <h2 class="h5 mb-3">Application Identity</h2>
        <p class="muted small mb-3">How the platform identifies itself to your team. Change these to white-label the app for your agency.</p>

        <div class="row g-3">
            <div class="col-md-6">
                <label class="ra-label" for="app-name">App Name</label>
                <input type="text" id="app-name" name="app_name" class="ra-input" required
                       value="<?= e($branding['app_name']) ?>"
                       placeholder="e.g. Acme Recruiting">
                <div class="muted small mt-1">Shown in the sidebar, login screen, and browser title.</div>
            </div>
            <div class="col-md-6">
                <label class="ra-label" for="tagline">Tagline</label>
                <input type="text" id="tagline" name="tagline" class="ra-input"
                       value="<?= e($branding['tagline']) ?>"
                       placeholder="e.g. Talent Operations Platform">
            </div>
            <div class="col-md-6">
                <label class="ra-label" for="primary-color">Primary Color</label>
                <div class="d-flex gap-2 align-items-center">
                    <input type="color" id="primary-color" name="primary_color" class="ra-input form-control-color" style="width:60px;height:44px;"
                           value="<?= e($branding['primary_color']) ?>"
                           onchange="document.getElementById('primary-color-hex').value = this.value">
                    <input type="text" id="primary-color-hex" name="primary_color_hex" class="ra-input"
                           style="max-width:120px;font-family:monospace;"
                           pattern="^#[0-9a-fA-F]{6}$"
                           value="<?= e($branding['primary_color']) ?>"
                           placeholder="#0d6efd"
                           oninput="var v = this.value; if (/^#[0-9a-fA-F]{6}$/.test(v)) { document.getElementById('primary-color').value = v; }">
                </div>
                <div class="muted small mt-1">Used for buttons and accents. Click the swatch or paste a hex code.</div>
            </div>
        </div>
    </div>

    <div class="ra-card mb-3">
        <h2 class="h5 mb-3">Logo &amp; Favicon</h2>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="ra-label" for="logo">Logo</label>
                <input type="file" id="logo" name="logo" class="ra-input" accept="image/png,image/jpeg,image/svg+xml,image/webp">
                <div class="muted small mt-1">PNG, JPEG, SVG, or WebP. Max 2 MB. Recommended 200×60px (horizontal).</div>
                <?php if ($branding['logo_path'] !== ''): ?>
                    <div class="mt-2">
                        <div class="muted small mb-1">Current logo:</div>
                        <img src="/files/<?= e($branding['logo_path']) ?>" alt="Current logo" style="max-height:60px;border:1px solid var(--ra-border);border-radius:.25rem;padding:.25rem;background:var(--ra-bg-elevated);">
                    </div>
                <?php endif; ?>
            </div>
            <div class="col-md-6">
                <label class="ra-label" for="favicon">Favicon</label>
                <input type="file" id="favicon" name="favicon" class="ra-input" accept="image/png,image/x-icon,image/svg+xml,image/webp">
                <div class="muted small mt-1">PNG, ICO, SVG, or WebP. Max 1 MB. Recommended 32×32 or 64×64px.</div>
                <?php if ($branding['favicon_path'] !== ''): ?>
                    <div class="mt-2">
                        <div class="muted small mb-1">Current favicon:</div>
                        <img src="/files/<?= e($branding['favicon_path']) ?>" alt="Current favicon" style="max-height:32px;">
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="ra-card mb-3">
        <h2 class="h5 mb-3">Your Organisation Details</h2>
        <p class="muted small mb-3">The details of your agency. Shown on invoices, email signatures, and generated reports (when those features ship).</p>

        <div class="row g-3">
            <div class="col-md-6">
                <label class="ra-label" for="org-name">Organisation Name</label>
                <input type="text" id="org-name" name="client_org_name" class="ra-input"
                       value="<?= e($branding['client_org_name']) ?>"
                       placeholder="e.g. Acme Recruiting Ltd">
            </div>
            <div class="col-md-6">
                <label class="ra-label" for="org-email">Contact Email</label>
                <input type="email" id="org-email" name="client_org_email" class="ra-input"
                       value="<?= e($branding['client_org_email']) ?>"
                       placeholder="e.g. hello@acme.co.ke">
            </div>
            <div class="col-md-6">
                <label class="ra-label" for="org-phone">Phone</label>
                <input type="text" id="org-phone" name="client_org_phone" class="ra-input"
                       value="<?= e($branding['client_org_phone']) ?>"
                       placeholder="e.g. +254 700 000 000">
            </div>
            <div class="col-12">
                <label class="ra-label" for="org-address">Address</label>
                <textarea id="org-address" name="client_org_address" class="ra-input" rows="3"
                          placeholder="e.g. Grace Business Center, Nairobi, Kenya"><?= e($branding['client_org_address']) ?></textarea>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="ra-btn ra-btn-primary">Save Branding</button>
        <a href="/admin/settings" class="ra-btn ra-btn-outline">Cancel</a>
    </div>
</form>

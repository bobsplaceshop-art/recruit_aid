<?php
/**
 * Auth layout — used by login, forgot-password, reset-password.
 *
 * ------------------------------------------------------------------
 * Bug fixed 2026-09-24:
 * ------------------------------------------------------------------
 * The prior version read only `flash('success')` and never rendered
 * errors. AuthController::showLogin() correctly read the `error`
 * flash and passed it as `$error` to the view, but the layout
 * ignored that variable — so a failed login looked like a silent
 * page refresh.
 *
 * This version reads BOTH `$error` and `$success` from view data
 * (with fallback to the session flash for controllers that don't
 * pass them explicitly). Using `??` short-circuits, so the flash
 * is only consumed from the session when it wasn't already pulled
 * by the controller.
 * ------------------------------------------------------------------
 */
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? app_setting('branding.app_name', 'Recruit Aid')) ?> — <?= e(app_setting('branding.app_name', 'Recruit Aid')) ?></title>
    <?php
        $fav = app_setting('branding.favicon_path', '');
        $primaryColor = app_setting('branding.primary_color', '#0d6efd');
    ?>
    <?php if ($fav !== ''): ?>
        <link rel="icon" href="/files/<?= e($fav) ?>">
    <?php endif; ?>
    <style>
        /* Override theme defaults — higher specificity than :root or [data-theme] */
        html[data-theme], html {
            --ra-primary: <?= e($primaryColor) ?> !important;
        }
    </style>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body { background:#0b1220; color:#e2e8f0; font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif; min-height:100vh; display:flex; align-items:center; justify-content:center; }
        .auth-card { width:100%; max-width:420px; background:#111c33; border:1px solid #1f2a44; border-radius:16px; padding:2.25rem; box-shadow:0 20px 60px rgba(0,0,0,0.4); }
        .brand { display:flex; align-items:center; gap:.6rem; font-weight:700; font-size:1.25rem; }
        .brand-mark { width:32px; height:32px; background:linear-gradient(135deg,#0f4c81,#f5a623); border-radius:9px; }
        .muted { color:#94a3b8; }
        .form-control, .form-control:focus { background:#0b1424; border-color:#1f2a44; color:#e2e8f0; }
        .form-control:focus { box-shadow:0 0 0 .2rem rgba(15,76,129,.35); border-color:#0f4c81; }
        .btn-primary { background:#0f4c81; border-color:#0f4c81; }
        .btn-primary:hover { background:#0d416e; border-color:#0d416e; }
        .alert-error { background:#3b1414; color:#fecaca; border:1px solid #7f1d1d; }
        .alert-success { background:#0f2c1c; color:#bbf7d0; border:1px solid #166534; }
    </style>
</head>
<body>
    <div class="auth-card">
        <div class="brand mb-1">
            <span class="brand-mark"></span>
            <?php
                $brandLogo = app_setting('branding.logo_path', '');
                $brandName = app_setting('branding.app_name', 'Recruit Aid');
            ?>
            <?php if ($brandLogo !== ''): ?>
                <img src="/files/<?= e($brandLogo) ?>" alt="<?= e($brandName) ?>" style="max-height:48px;max-width:200px;width:auto;object-fit:contain;">
            <?php else: ?>
                <span><?= e($brandName) ?></span>
            <?php endif; ?>
        </div>
        <p class="muted mb-4"><?= e(app_setting('branding.tagline', 'Recruitment Operations Platform')) ?></p>

        <?php
            // Prefer view data (set by controller). Fall back to flash.
            // `??` short-circuits, so we do not touch the session when
            // the controller already supplied the value.
            $__error   = $error   ?? flash('error');
            $__success = $success ?? flash('success');
        ?>

        <?php if (!empty($__error)): ?>
            <div class="alert alert-error" role="alert">
                <i class="bi bi-exclamation-triangle me-1"></i>
                <?= e((string) $__error) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($__success)): ?>
            <div class="alert alert-success" role="alert">
                <i class="bi bi-check-circle me-1"></i>
                <?= e((string) $__success) ?>
            </div>
        <?php endif; ?>

        <?= $content ?? '' ?>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

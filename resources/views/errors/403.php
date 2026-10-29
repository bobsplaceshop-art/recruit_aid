<?php
/**
 * 403 Forbidden view.
 * $errorMessage — custom message from the middleware (optional)
 * $requiredPermission — the permission the user lacks (optional, for context)
 */
$displayMessage = isset($errorMessage) && $errorMessage !== ''
    ? $errorMessage
    : "You don't have permission to view this page.";
$permissionText = isset($requiredPermission) && $requiredPermission !== ''
    ? $requiredPermission
    : '';
?>
<!doctype html>
<html lang="en" data-theme="system">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Forbidden — Recruit Aid</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="/assets/css/app.css" rel="stylesheet">
    <script>
    (function () {
        try {
            var stored = localStorage.getItem('ra-theme');
            if (stored && ['dark', 'light', 'system'].indexOf(stored) !== -1) {
                document.documentElement.setAttribute('data-theme', stored);
            }
        } catch (e) {}
    })();
    </script>
</head>
<body style="min-height:100vh;display:flex;align-items:center;justify-content:center;padding:2rem;">
    <div class="ra-card" style="max-width:560px;width:100%;text-align:center;padding:2.5rem;">
        <div style="font-size:3.5rem;color:var(--ra-error-fg);">
            <i class="bi bi-shield-lock"></i>
        </div>
        <h1 class="h3 mt-3 mb-2" style="color:var(--ra-text-strong);">
            Access denied
        </h1>
        <p class="muted mb-3"><?= htmlspecialchars($displayMessage, ENT_QUOTES, 'UTF-8') ?></p>

        <?php if ($permissionText !== ''): ?>
            <p class="muted small mb-4">
                Required permission:
                <code style="background:var(--ra-bg-elevated);padding:.15rem .4rem;border-radius:.25rem;font-size:.85em;"><?= htmlspecialchars($permissionText, ENT_QUOTES, 'UTF-8') ?></code>
                <br>
                <span style="font-size:.85em;">Contact your administrator if you believe you should have access.</span>
            </p>
        <?php endif; ?>

        <div class="d-flex gap-2 justify-content-center flex-wrap">
            <a href="/dashboard" class="ra-btn ra-btn-primary">
                <i class="bi bi-house-door"></i>
                <span>Back to dashboard</span>
            </a>
            <a href="javascript:history.back()" class="ra-btn ra-btn-outline">
                <i class="bi bi-arrow-left"></i>
                <span>Go back</span>
            </a>
        </div>
    </div>
</body>
</html>

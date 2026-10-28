<?php
$displayMessage = isset($errorMessage) && $errorMessage !== ''
    ? $errorMessage
    : "You don't have permission to view this page. If you believe you should have access, contact your administrator.";

$showLoginLink = isset($loginLink) && $loginLink === true;
?>
<!doctype html>
<html lang="en" data-theme="system">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Access denied — Recruit Aid</title>
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
            <?= $showLoginLink ? 'Session expired' : 'Access denied' ?>
        </h1>
        <p class="muted mb-4"><?= htmlspecialchars($displayMessage, ENT_QUOTES, 'UTF-8') ?></p>

        <div class="d-flex gap-2 justify-content-center flex-wrap">
            <?php if ($showLoginLink): ?>
                <a href="/login" class="ra-btn ra-btn-primary">
                    <i class="bi bi-box-arrow-in-right"></i>
                    <span>Sign in again</span>
                </a>
                <a href="javascript:history.back()" class="ra-btn ra-btn-outline">
                    <i class="bi bi-arrow-left"></i>
                    <span>Go back</span>
                </a>
            <?php else: ?>
                <a href="/dashboard" class="ra-btn ra-btn-primary">
                    <i class="bi bi-house-door"></i>
                    <span>Back to dashboard</span>
                </a>
                <a href="javascript:history.back()" class="ra-btn ra-btn-outline">
                    <i class="bi bi-arrow-left"></i>
                    <span>Go back</span>
                </a>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>

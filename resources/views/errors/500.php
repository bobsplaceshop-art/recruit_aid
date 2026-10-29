<?php
/**
 * 500 error page.
 *
 * Available variables (set by App::renderException):
 *   $errorMessage — human message (may be generic)
 *   $errorRef     — short reference ID like "ERR-4a2f9c", or null
 *
 * The reference is displayed discreetly so users can quote it to
 * support. Grepping the log for it returns the full stack trace.
 */
$displayMessage = isset($errorMessage) && $errorMessage !== ''
    ? $errorMessage
    : "We hit an unexpected problem processing your request. The issue has been recorded and our team will look into it.";

$displayRef = isset($errorRef) && is_string($errorRef) ? $errorRef : null;
?>
<!doctype html>
<html lang="en" data-theme="system">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Something went wrong — <?= e(app_setting('branding.app_name', 'Recruit Aid')) ?></title>
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
    <div class="ra-card" style="max-width:600px;width:100%;padding:2.5rem;">
        <div class="text-center">
            <div style="font-size:3.5rem;color:var(--ra-warn-fg);">
                <i class="bi bi-exclamation-triangle"></i>
            </div>
            <h1 class="h3 mt-3 mb-2" style="color:var(--ra-text-strong);">
                Something went wrong
            </h1>
            <p class="muted mb-3"><?= htmlspecialchars($displayMessage, ENT_QUOTES, 'UTF-8') ?></p>

            <?php if ($displayRef !== null): ?>
                <p class="mb-4" style="font-size:.85rem;">
                    <span class="muted">Reference:</span>
                    <code style="background:var(--ra-surface-3);padding:.15rem .4rem;border-radius:.25rem;color:var(--ra-text-strong);font-size:.85em;letter-spacing:.02em;">
                        <?= htmlspecialchars($displayRef, ENT_QUOTES, 'UTF-8') ?>
                    </code>
                </p>
                <p class="muted mb-4" style="font-size:.78rem;">
                    Quote this reference if you contact support.
                </p>
            <?php endif; ?>
        </div>

        <div class="ra-alert ra-alert-warn" style="font-size:.85rem;">
            <strong>What you can try:</strong>
            <ul class="mb-0 mt-1" style="padding-left:1.2rem;">
                <li>Reload the page</li>
                <li>Go back and try again</li>
                <li>If this keeps happening, contact your administrator</li>
            </ul>
        </div>

        <div class="d-flex gap-2 justify-content-center flex-wrap mt-3">
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

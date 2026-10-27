<?php
/**
 * Application layout — sidebar shell.
 * Includes a pre-paint theme script to prevent flash-of-wrong-theme.
 *
 * Views may define $pageScripts (array of asset paths) to load extra JS.
 */
$initialTheme = 'system';
if (isset($user) && $user && !empty($user->themePreference)) {
    $initialTheme = $user->themePreference;
}
$pageScripts = $pageScripts ?? [];

// Determine if there are any flash messages to render.
$hasFlash = false;
$flashSuccess = null;
$flashError = null;
if (function_exists('flash')) {
    $flashSuccess = flash('success');
    $flashError = flash('error');
    if ($flashSuccess !== null || $flashError !== null) {
        $hasFlash = true;
    }
}
?>
<!doctype html>
<html lang="en" data-theme="<?= e($initialTheme) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?= e($title ?? 'Recruit Aid') ?> — Recruit Aid</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= asset('css/app.css') ?>" rel="stylesheet">

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
<body>
    <div class="ra-shell">
        <?= view_partial('partials/sidebar', ['user' => $user ?? null]) ?>
        <?= view_partial('partials/topbar',  ['user' => $user ?? null, 'title' => $title ?? null]) ?>

        <main class="ra-content<?= $hasFlash ? ' has-flash' : '' ?>">
            <?php if ($flashSuccess !== null): ?>
                <div class="ra-alert ra-alert-success"><?= e((string) $flashSuccess) ?></div>
            <?php endif; ?>
            <?php if ($flashError !== null): ?>
                <div class="ra-alert ra-alert-error"><?= e((string) $flashError) ?></div>
            <?php endif; ?>

            <?= $content ?? '' ?>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= asset('js/app.js') ?>"></script>
    <script src="<?= asset('js/notifications.js') ?>"></script>
    <?php foreach ($pageScripts as $script): ?>
        <script src="<?= asset($script) ?>"></script>
    <?php endforeach; ?>
</body>
</html>

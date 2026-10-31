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
<link rel="stylesheet" href="/assets/vendor/air-datepicker/air-datepicker.css">
    <script>
        // Force reload if the page is loaded from the back-forward cache
        window.addEventListener('pageshow', function(event) {
            if (event.persisted || (typeof window.performance != 'undefined' && window.performance.navigation.type === 2)) {
                window.location.reload();
            }
        });
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
    <script src="<?= asset('js/search.js') ?>?v=1"></script>
    <script src="<?= asset('js/live-search.js') ?>?v=1"></script>
<script src="/assets/vendor/air-datepicker/air-datepicker.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Target both data-datepicker and text inputs that were originally dates
    document.querySelectorAll('[data-datepicker], input[type="date"]').forEach(function(el) {
        // Force type to text so the browser doesn't show the native picker
        if (el.type === 'date') {
            el.type = 'text';
            el.setAttribute('placeholder', 'yyyy-mm-dd');
        }

        new AirDatepicker(el, {
            dateFormat: 'yyyy-MM-dd',
            autoClose: true,
            minDate: new Date(),
            locale: {
                days: ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
                daysShort: ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'],
                daysMin: ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'],
                months: ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'],
                monthsShort: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                today: 'Today',
                clear: 'Clear',
                dateFormat: 'yyyy-MM-dd',
                firstDay: 0
            }
        });
    });
});
</script>
</body>
</html>

<!doctype html>
<html lang="en" data-theme="system">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'CAS Suite') ?> — CAS Suite</title>
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
    <nav class="ra-navbar" style="border-bottom: 1px solid var(--ra-border); padding: 1rem 2rem; display: flex; justify-content: space-between; align-items: center;">
        <div class="d-flex align-items-center gap-3">
            <i class="bi bi-grid-1x2-fill" style="font-size: 1.5rem; color: var(--ra-primary);"></i>
            <h1 class="h4 mb-0" style="color: var(--ra-text-strong);">CAS Suite</h1>
        </div>
        <div class="d-flex align-items-center gap-3">
            <span class="muted"><?= e($user->name ?? 'Admin') ?></span>
            <a href="/logout" class="ra-btn ra-btn-outline" style="padding: 0.25rem 0.75rem; font-size: 0.85rem;">Log out</a>
        </div>
    </nav>

    <main style="padding: 2rem; max-width: 1200px; margin: 0 auto;">
        <?= $content ?? '' ?>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

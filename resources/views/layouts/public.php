<?php /** @var string $title */ ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'Open Roles') ?> — <?= e(app_setting('branding.app_name', 'Recruit Aid')) ?></title>
    <?php $primaryColor = app_setting('branding.primary_color', '#0f4c81'); ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root { --brand: <?= e($primaryColor) ?>; }
        body { background: #f8f9fa; }
        .brand-header { background: var(--brand); color: white; padding: 1rem 0; }
        .brand-header a { color: white; text-decoration: none; }
        .brand-header a:hover { color: rgba(255,255,255,0.85); }
        .job-card { border: 1px solid #dee2e6; background: white; border-radius: .5rem; transition: border-color 0.15s; }
        .job-card:hover { border-color: var(--brand); }
        .btn-brand { background: var(--brand); color: white; border: none; }
        .btn-brand:hover { background: var(--brand); opacity: 0.9; color: white; }
    </style>
</head>
<body>
    <header class="brand-header">
        <div class="container">
            <a href="/jobs"><h1 class="h4 mb-0"><?= e(app_setting('branding.app_name', 'Open Roles')) ?></h1></a>
        </div>
    </header>

    <main class="container py-4">
        <?= $content ?? '' ?>
    </main>

    <footer class="text-center text-muted py-4 small">
        Powered by <?= e(app_setting('branding.app_name', 'Recruit Aid')) ?>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

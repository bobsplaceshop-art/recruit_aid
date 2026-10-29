<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background:#0f172a; color:#e2e8f0; font-family:system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
        .wrap { max-width:720px; margin:6rem auto; padding:2rem 1.5rem; background:#111c33; border-radius:16px; border:1px solid #1f2a44; }
        h1 { font-weight:700; letter-spacing:-0.02em; }
        .badge-soft { background:#152748; color:#93c5fd; padding:.35rem .75rem; border-radius:999px; font-size:.85rem; }
        .row-item { display:flex; justify-content:space-between; padding:.6rem 0; border-bottom:1px dashed #1f2a44; }
        .row-item:last-child { border-bottom:0; }
        .muted { color:#94a3b8; font-size:.9rem; }
    </style>
</head>
<body>
    <div class="wrap">
        <span class="badge-soft">Foundation online</span>
        <h1 class="mt-3"><?= e(app_setting('branding.app_name', 'Recruit Aid')) ?></h1>
        <p class="muted mb-4">Recruitment Operations Platform — the application skeleton is working.</p>

        <div class="row-item"><span>App version</span><strong><?= htmlspecialchars($appVersion, ENT_QUOTES, 'UTF-8') ?></strong></div>
        <div class="row-item"><span>PHP version</span><strong><?= htmlspecialchars($phpVersion, ENT_QUOTES, 'UTF-8') ?></strong></div>
        <div class="row-item"><span>Timezone</span><strong><?= htmlspecialchars($timezone, ENT_QUOTES, 'UTF-8') ?></strong></div>
        <div class="row-item"><span>Server time</span><strong><?= htmlspecialchars($serverTime, ENT_QUOTES, 'UTF-8') ?></strong></div>

        <hr style="border-color:#1f2a44; margin:2rem 0;">
        <p class="muted mb-0">Next: database migration, authentication, and the first admin user.</p>
    </div>
</body>
</html>

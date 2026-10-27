<div class="ra-page-band">
    <div class="ra-page-band-row">
        <div>
            <div class="ra-page-band-label">Administration</div>
            <h1 class="ra-page-band-title">System settings</h1>
        </div>
    </div>
</div>

<p class="muted mb-4">Manage users, roles, and system-wide configuration.</p>

<div class="ra-tiles">
    <?php if ($user->can('users.view')): ?>
    <a href="/admin/users" class="ra-tile">
        <div class="ra-tile-icon ra-tile-icon--sky">
            <i class="bi bi-people-fill"></i>
        </div>
        <div class="ra-tile-label">User Accounts</div>
    </a>
    <?php endif; ?>

    <?php if ($user->can('settings.manage')): ?>
    <a href="/admin/roles-perm" class="ra-tile">
        <div class="ra-tile-icon ra-tile-icon--clay">
            <i class="bi bi-diagram-3-fill"></i>
        </div>
        <div class="ra-tile-label">Roles &amp; Permissions</div>
    </a>
    <?php endif; ?>

    <?php if ($user->can('audit.view')): ?>
    <a href="/admin/audit" class="ra-tile">
        <div class="ra-tile-icon ra-tile-icon--sage">
            <i class="bi bi-journal-text"></i>
        </div>
        <div class="ra-tile-label">Audit Logs</div>
    </a>
    <?php endif; ?>

    <?php if ($user->can('settings.manage')): ?>
    <a href="/admin/branding" class="ra-tile">
        <div class="ra-tile-icon ra-tile-icon--plum">
            <i class="bi bi-palette-fill"></i>
        </div>
        <div class="ra-tile-label">Branding</div>
    </a>

    <a href="/admin/email-templates" class="ra-tile">
        <div class="ra-tile-icon ra-tile-icon--mint">
            <i class="bi bi-envelope-fill"></i>
        </div>
        <div class="ra-tile-label">Email Templates</div>
    </a>

    <a href="/admin/custom-fields" class="ra-tile">
        <div class="ra-tile-icon ra-tile-icon--rose">
            <i class="bi bi-input-cursor-text"></i>
        </div>
        <div class="ra-tile-label">Custom Fields</div>
    </a>

    <a href="/admin/settings" class="ra-tile">
        <div class="ra-tile-icon ra-tile-icon--amber">
            <i class="bi bi-gear-fill"></i>
        </div>
        <div class="ra-tile-label">System Settings</div>
    </a>
    <?php endif; ?>
</div>

<div class="ra-table-card">
    <div class="ra-table-title">System overview</div>
    <table class="ra-table">
        <tbody>
            <tr>
                <td style="width:220px;"><span class="ra-section-label">Total users</span></td>
                <td><strong><?= e((string) $userCount) ?></strong></td>
            </tr>
            <tr>
                <td><span class="ra-section-label">Your role</span></td>
                <td>
                    <?php foreach ($user->roleSlugs as $slug): ?>
                        <span class="ra-badge me-1"><?= e($slug) ?></span>
                    <?php endforeach; ?>
                </td>
            </tr>
            <tr>
                <td><span class="ra-section-label">App version</span></td>
                <td><?= e(\CorporateAid\Core\App::VERSION) ?></td>
            </tr>
            <tr>
                <td><span class="ra-section-label">Environment</span></td>
                <td><?= e((string) config('app.env', 'production')) ?></td>
            </tr>
            <tr>
                <td><span class="ra-section-label">Timezone</span></td>
                <td><?= e(date_default_timezone_get()) ?></td>
            </tr>
        </tbody>
    </table>
</div>

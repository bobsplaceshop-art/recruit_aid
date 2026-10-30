<?php
    // Helper: render a small "soon" badge next to a nav item
    $soonBadge = function (): string {
        return ' <span style="font-size:.6rem;padding:.1rem .35rem;background:var(--ra-bg-elevated);color:var(--ra-muted);border-radius:.25rem;letter-spacing:.5px;">SOON</span>';
    };
?>
<?php
/**
 * Left sidebar navigation.
 *
 * Expects:
 *   $user  — the current \CorporateAid\Platform\Models\User (may be null for guests)
 *
 * Active state: matches the current URI against each item's href.
 * Visibility: each item is wrapped in $user->can($permission) so users
 * only see what their role grants them.
 */

$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

/**
 * Return 'is-active' if $href matches the current path.
 */
$isActive = function (string $href) use ($currentPath): string {
    $href = rtrim($href, '/');
    $cur = rtrim($currentPath, '/');
    if ($href === '' || $href === '/') {
        return $cur === '' ? 'is-active' : '';
    }
    return ($cur === $href || str_starts_with($cur, $href . '/')) ? 'is-active' : '';
};

$isLoggedIn = isset($user) && $user;
?>
<aside class="ra-sidebar" id="ra-sidebar">
    <a href="/dashboard" class="ra-brand">
        <?php
            $brandLogo = app_setting('branding.logo_path', '');
            $brandName = app_setting('branding.app_name', 'Recruit Aid');
        ?>
        <?php if ($brandLogo !== ''): ?>
            <img src="/files/<?= e($brandLogo) ?>" alt="<?= e($brandName) ?>" style="max-height:36px;max-width:180px;width:auto;object-fit:contain;">
        <?php else: ?>
            <span class="ra-brand-mark"></span>
            <span><?= e($brandName) ?></span>
        <?php endif; ?>
    </a>

    <nav class="ra-nav">
        <?php if ($isLoggedIn): ?>
        <div class="ra-nav-group">
            <a href="/dashboard" class="ra-nav-item <?= $isActive('/dashboard') ?>">
                <i class="bi bi-speedometer2"></i>
                <span>Dashboard</span>
            </a>
        </div>
        <?php endif; ?>

        <?php if ($isLoggedIn && ($user->can('recruit.organisations.view') || $user->can('recruit.contacts.view') || $user->can('recruit.engagements.view'))): ?>
        <div class="ra-nav-group">
            <div class="ra-nav-group-label">Clients</div>
            <?php if ($user->can('recruit.organisations.view')): ?>
                <a href="/organisations" class="ra-nav-item <?= $isActive('/organisations') ?>">
                    <i class="bi bi-buildings"></i>
                    <span>Organisations</span>
                </a>
            <?php endif; ?>
            <?php if ($user->can('recruit.contacts.view')): ?>
                <a href="/contacts" class="ra-nav-item <?= $isActive('/contacts') ?>">
                    <i class="bi bi-person-lines-fill"></i>
                    <span>Contacts</span>
                </a>
            <?php endif; ?>
            <?php if ($user->can('recruit.engagements.view')): ?>
                <a href="/engagements" class="ra-nav-item <?= $isActive('/engagements') ?>">
                    <i class="bi bi-file-earmark-text"></i>
                    <span>Engagements</span>
                </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($isLoggedIn && ($user->can('recruit.vacancies.view') || $user->can('recruit.candidates.view') || $user->can('recruit.interviews.view'))): ?>
        <div class="ra-nav-group">
            <div class="ra-nav-group-label">Recruitment</div>
            <?php if ($user->can('recruit.vacancies.view')): ?>
                <a href="/vacancies" class="ra-nav-item <?= $isActive('/vacancies') ?>">
                    <i class="bi bi-briefcase"></i>
                    <span>Vacancies</span>
                </a>
            <?php endif; ?>
            <?php if ($user->can('recruit.candidates.view')): ?>
                <a href="/candidates" class="ra-nav-item <?= $isActive('/candidates') ?>">
                    <i class="bi bi-people"></i>
                    <span>Candidates</span>
                </a>
                <a href="/applications" class="ra-nav-item <?= $isActive('/applications') ?>">
                    <i class="bi bi-diagram-3"></i>
                    <span>Applications</span>
                </a>
            <?php endif; ?>
            <?php if ($user->can('recruit.interviews.view')): ?>
                <a href="/interviews" class="ra-nav-item <?= $isActive('/interviews') ?>">
                    <i class="bi bi-calendar-event"></i>
                    <span>Interviews</span>
                </a>
            <?php endif; ?>
            <?php if ($user->can('recruit.candidates.view')): ?>
                <a href="/offers" class="ra-nav-item <?= $isActive('/offers') ?>">
                    <i class="bi bi-file-earmark-check"></i>
                    <span>Offers</span>
                </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($isLoggedIn): ?>
        <div class="ra-nav-group">
            <div class="ra-nav-group-label">Work</div>
            <a href="/tasks" class="ra-nav-item <?= $isActive('/tasks') ?>">
                <i class="bi bi-check2-square"></i>
                <span>Tasks</span><?= $soonBadge() ?>
            </a>
            <a href="/calendar" class="ra-nav-item <?= $isActive('/calendar') ?>">
                <i class="bi bi-calendar3"></i>
                <span>Calendar</span><?= $soonBadge() ?>
            </a>
            <a href="/activity" class="ra-nav-item <?= $isActive('/activity') ?>">
                <i class="bi bi-activity"></i>
                <span>Activity</span>
            </a>
        </div>
        <?php endif; ?>

        <?php if ($isLoggedIn && ($user->can('recruit.reports.view') || $user->can('core.documents.view'))): ?>
        <div class="ra-nav-group">
            <div class="ra-nav-group-label">Insights</div>
            <?php if ($user->can('recruit.reports.view')): ?>
                <a href="/reports" class="ra-nav-item <?= $isActive('/reports') ?>">
                    <i class="bi bi-graph-up"></i>
                    <span>Reports</span>
                </a>
            <?php endif; ?>
            <?php if ($user->can('core.documents.view')): ?>
                <a href="/documents" class="ra-nav-item <?= $isActive('/documents') ?>">
                    <i class="bi bi-folder2-open"></i>
                    <span>Documents</span><?= $soonBadge() ?>
                </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($isLoggedIn && ($user->can('core.users.view') || $user->can('core.settings.manage') || $user->can('core.audit.view') || $user->can('core.custom_fields.manage'))): ?>
        <div class="ra-nav-group">
            <div class="ra-nav-group-label">Administration</div>
            <a href="/admin" class="ra-nav-item <?= $isActive('/admin') ?>">
                <i class="bi bi-sliders"></i>
                <span>Overview</span>
            </a>
            <?php if ($user->can('core.users.view')): ?>
                <a href="/admin/users" class="ra-nav-item <?= $isActive('/admin/users') ?>">
                    <i class="bi bi-person-badge"></i>
                    <span>Users</span>
                </a>
            <?php endif; ?>
            <?php if ($user->can('core.settings.manage')): ?>
                <a href="/admin/roles-perm" class="ra-nav-item <?= $isActive('/admin/roles-perm') ?>">
                    <i class="bi bi-shield-lock"></i>
                    <span>Roles &amp; Permissions</span>
                </a>
            <?php endif; ?>
            <?php if ($user->can('core.custom_fields.manage')): ?>
                <a href="/admin/custom-fields" class="ra-nav-item <?= $isActive('/admin/custom-fields') ?>">
                    <i class="bi bi-input-cursor-text"></i>
                    <span>Custom Fields</span>
                </a>
                <a href="/admin/lookups" class="ra-nav-item <?= $isActive('/admin/lookups') ?>">
                    <i class="bi bi-list-ul"></i>
                    <span>Lookups</span>
                </a>
            <?php endif; ?>
            <?php if ($user->can('core.audit.view')): ?>
                <a href="/admin/audit" class="ra-nav-item <?= $isActive('/admin/audit') ?>">
                    <i class="bi bi-journal-text"></i>
                    <span>Audit Log</span><?= $soonBadge() ?>
                </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </nav>

    <?php if ($isLoggedIn): ?>
    <div class="ra-nav-footer">
        <div class="d-flex align-items-center gap-2 px-2 py-1">
            <div class="ra-avatar"><?= e(strtoupper(substr($user->name, 0, 1))) ?></div>
            <div class="flex-grow-1" style="min-width:0;">
                <div class="small text-truncate"><?= e($user->name) ?></div>
                <div class="muted" style="font-size:.72rem;"><?= e($user->roleSlugs[0] ?? '') ?></div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</aside>

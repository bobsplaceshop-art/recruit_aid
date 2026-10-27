<?php
/**
 * Top bar. Hamburger (mobile), breadcrumbs, search, theme toggle,
 * notifications placeholder, user menu.
 */
?>
<header class="ra-topbar">
    <div class="d-flex align-items-center gap-3" style="min-width:0;">
        <button type="button" class="ra-icon-btn ra-hamburger" data-ra-sidebar-toggle aria-label="Toggle menu">
            <i class="bi bi-list" style="font-size:1.15rem;"></i>
        </button>

        <div class="ra-breadcrumbs d-none d-md-flex">
            <a href="/dashboard">Home</a>
            <?php if (!empty($title) && $title !== 'Dashboard'): ?>
                <span>/</span>
                <span><?= e($title) ?></span>
            <?php endif; ?>
        </div>
    </div>

    <div class="d-flex align-items-center gap-2">
        <input type="text" class="ra-search" placeholder="Search (coming soon)" disabled>

        <button type="button" class="ra-icon-btn" data-ra-theme-toggle title="Theme">
            <i class="bi bi-sun-fill" style="display:none;" data-ra-icon-light></i>
            <i class="bi bi-moon-stars-fill" data-ra-icon-dark></i>
            <i class="bi bi-display" style="display:none;" data-ra-icon-system></i>
        </button>

        <?php if (isset($user) && $user && $user->can('settings.manage')): ?>
            <a href="/admin/settings" class="ra-icon-btn" title="System Settings" id="settings-gear">
                <i class="bi bi-gear"></i>
            </a>
        <?php endif; ?>

        <?php
            // Load the notification count and recent items for initial render.
            // The JS then polls for updates.
            $notifCount = 0;
            try {
                /** @var \CorporateAid\Platform\Services\NotificationService $notifSvc */
                $notifSvc = \CorporateAid\Core\App::instance()->container()->get(\CorporateAid\Platform\Services\NotificationService::class);
                $notifCount = $notifSvc->unreadCountForCurrentUser();
            } catch (\Throwable $e) { /* fail silent */ }

            echo view_partial('partials/notification-bell', ['unreadCount' => $notifCount]);
        ?>

        <?php if (isset($user) && $user): ?>
            <div class="ra-user-menu" data-ra-user-menu>
                <div class="ra-avatar"><?= e(strtoupper(substr($user->name, 0, 1))) ?></div>
                <div class="d-none d-md-block" style="line-height:1.2;">
                    <div class="small"><?= e($user->name) ?></div>
                    <div class="muted" style="font-size:.72rem;"><?= e($user->email) ?></div>
                </div>
                <i class="bi bi-chevron-down muted small"></i>
            </div>

            <?= view_partial('partials/user-dropdown', ['user' => $user]) ?>
        <?php endif; ?>
    </div>

    <?= view_partial('partials/theme-dropdown') ?>
</header>

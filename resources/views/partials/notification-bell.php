<?php
/**
 * Notification bell button + dropdown.
 * Renders server-side with the unread count; JS enhances it with polling.
 *
 * Expects $unreadCount (int) and $notifications (array) — optional.
 */
$unreadCount = $unreadCount ?? 0;
$badgeStyle = $unreadCount > 0
    ? 'position:absolute;top:-4px;right:-4px;background:#dc2626;color:#fff;border-radius:999px;font-size:.65rem;font-weight:700;padding:1px 5px;min-width:16px;text-align:center;line-height:1.1;'
    : 'display:none;';
?>
<div style="position:relative;" id="ra-notif-wrap">
    <button type="button" class="ra-icon-btn" id="ra-notif-toggle" title="Notifications" aria-haspopup="true" aria-expanded="false">
        <i class="bi bi-bell"></i>
        <span id="ra-notif-badge" style="<?= $badgeStyle ?>"><?= (int) $unreadCount ?></span>
    </button>

    <div id="ra-notif-dropdown" class="ra-dropdown" style="min-width:360px;max-width:440px;max-height:480px;overflow-y:auto;">
        <div class="ra-dropdown-header d-flex justify-content-between align-items-center">
            <div>
                <div class="small fw-bold">Notifications</div>
                <div class="muted" style="font-size:.72rem;" id="ra-notif-summary">—</div>
            </div>
            <button type="button" id="ra-notif-mark-all" class="ra-row-actions" title="Mark all as read">
                <i class="bi bi-check2-all"></i>
            </button>
        </div>

        <div id="ra-notif-list">
            <div class="text-center muted small p-4">
                <i class="bi bi-hourglass-split"></i> Loading…
            </div>
        </div>

        <div class="ra-dropdown-divider"></div>
        <a href="/notifications" class="ra-dropdown-item" style="justify-content:center;">
            <i class="bi bi-arrow-right"></i>
            <span>View all notifications</span>
        </a>
    </div>
</div>

<?php
/**
 * User dropdown menu. Positioned by JS relative to the trigger.
 */
?>
<div id="ra-user-dropdown" class="ra-dropdown" style="min-width:230px;">
    <?php if (isset($user) && $user): ?>
        <div class="ra-dropdown-header">
            <div class="small"><?= e($user->name) ?></div>
            <div class="muted" style="font-size:.78rem;"><?= e($user->email) ?></div>
        </div>
    <?php endif; ?>

    <a href="/account" class="ra-dropdown-item">
        <i class="bi bi-person-circle"></i>
        <span>Your account</span>
    </a>

    <a href="/account/password" class="ra-dropdown-item">
        <i class="bi bi-shield-lock"></i>
        <span>Change password</span>
    </a>

    <div class="ra-dropdown-divider"></div>

    <form method="POST" action="/logout" style="margin:0;">
        <?= csrf_field() ?>
        <button type="submit" class="ra-dropdown-item" style="color:#fecaca;">
            <i class="bi bi-box-arrow-right"></i>
            <span>Sign out</span>
        </button>
    </form>
</div>

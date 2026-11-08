<?php /** @var int $tenant_id */ ?>
<div class="ra-page-header">
    <div>
        <div class="ra-breadcrumbs">
            <a href="/cas/dashboard">Dashboard</a>
            <span>/</span>
            <span>Confirm password</span>
        </div>
        <h1 class="ra-page-title">Confirm your password</h1>
        <p class="ra-page-sub">
            Switching to tenant #<?= (int) $tenant_id ?> is a privileged action.
            Please re-enter your password to continue.
        </p>
    </div>
</div>

<div class="ra-card" style="max-width:520px;">
    <?php if (!empty($error)): ?>
        <div class="ra-alert ra-alert-error"><?= e((string) $error) ?></div>
    <?php endif; ?>

    <form method="POST" action="/cas/context/confirm" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="tenant_id" value="<?= (int) $tenant_id ?>">

        <div class="mb-3">
            <label class="ra-label" for="cc-password">Your password</label>
            <input type="password" id="cc-password" name="password" class="ra-input"
                   required autofocus autocomplete="current-password">
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="ra-btn ra-btn-primary">
                <i class="bi bi-shield-lock"></i>
                <span>Confirm and switch</span>
            </button>
            <a href="/cas/dashboard" class="ra-btn ra-btn-outline">Cancel</a>
        </div>
    </form>
</div>

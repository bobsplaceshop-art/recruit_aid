<div class="ra-page-header">
    <div>
        <div class="ra-breadcrumbs">
            <a href="/dashboard">Home</a>
            <span>/</span>
            <a href="/account">Account</a>
            <span>/</span>
            <span>Change password</span>
        </div>
        <h1 class="ra-page-title">Change password</h1>
        <p class="ra-page-sub">Choose something strong and unique.</p>
    </div>
</div>

<div class="ra-card" style="max-width:560px;">
    <?php if (!empty($error)): ?>
        <div class="ra-alert ra-alert-error"><?= e((string) $error) ?></div>
    <?php endif; ?>
    <?php if (!empty($success)): ?>
        <div class="ra-alert ra-alert-success"><?= e((string) $success) ?></div>
    <?php endif; ?>

    <form method="POST" action="/account/password" novalidate>
        <?= csrf_field() ?>

        <div class="mb-3">
            <label class="ra-label" for="current_password">Current password</label>
            <div style="position:relative;">
                <input type="password" id="current_password" name="current_password"
                       class="ra-input" required autocomplete="current-password" style="padding-right:2.5rem;">
                <button type="button" class="pw-toggle" data-target="current_password"
                        style="position:absolute;top:50%;right:.6rem;transform:translateY(-50%);background:transparent;border:0;color:#94a3b8;cursor:pointer;padding:.25rem;">
                    <i class="bi bi-eye"></i>
                </button>
            </div>
        </div>

        <div class="mb-3">
            <label class="ra-label" for="new_password">New password</label>
            <div style="position:relative;">
                <input type="password" id="new_password" name="new_password"
                       class="ra-input" required autocomplete="new-password" style="padding-right:2.5rem;">
                <button type="button" class="pw-toggle" data-target="new_password"
                        style="position:absolute;top:50%;right:.6rem;transform:translateY(-50%);background:transparent;border:0;color:#94a3b8;cursor:pointer;padding:.25rem;">
                    <i class="bi bi-eye"></i>
                </button>
            </div>
            <div class="muted small mt-1">Minimum 12 characters, or 10 with 3 of 4 character types.</div>
        </div>

        <div class="mb-3">
            <label class="ra-label" for="confirm_password">Confirm new password</label>
            <div style="position:relative;">
                <input type="password" id="confirm_password" name="confirm_password"
                       class="ra-input" required autocomplete="new-password" style="padding-right:2.5rem;">
                <button type="button" class="pw-toggle" data-target="confirm_password"
                        style="position:absolute;top:50%;right:.6rem;transform:translateY(-50%);background:transparent;border:0;color:#94a3b8;cursor:pointer;padding:.25rem;">
                    <i class="bi bi-eye"></i>
                </button>
            </div>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="ra-btn ra-btn-primary">
                <i class="bi bi-check2"></i>
                <span>Update password</span>
            </button>
            <a href="/account" class="ra-btn ra-btn-outline">Cancel</a>
        </div>
    </form>
</div>

<script>
(function(){
    document.querySelectorAll('.pw-toggle').forEach(function(btn){
        btn.addEventListener('click', function(){
            var target = document.getElementById(btn.getAttribute('data-target'));
            if (!target) return;
            var isPassword = target.type === 'password';
            target.type = isPassword ? 'text' : 'password';
            var icon = btn.querySelector('i');
            if (icon) {
                icon.className = isPassword ? 'bi bi-eye-slash' : 'bi bi-eye';
            }
        });
    });
})();
</script>

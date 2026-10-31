<div class="ra-page-header">
    <div>
        <div class="ra-breadcrumbs">
            <a href="/recruit/dashboard">Home</a>
            <span>/</span>
            <span>Confirm password</span>
        </div>
        <h1 class="ra-page-title">Confirm your password</h1>
        <p class="ra-page-sub">
            For your security, please re-enter your password before continuing.
        </p>
    </div>
</div>

<div class="ra-card" style="max-width:520px;">
    <?php if (!empty($error)): ?>
        <div class="ra-alert ra-alert-error"><?= e((string) $error) ?></div>
    <?php endif; ?>

    <form method="POST" action="/account/confirm-password" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="next" value="<?= e($next ?? '/dashboard') ?>">

        <div class="mb-3">
            <label class="ra-label" for="cp-password">Your password</label>
            <div style="position:relative;">
                <input type="password" id="cp-password" name="password" class="ra-input"
                       required autofocus autocomplete="current-password" style="padding-right:2.5rem;">
                <button type="button" class="pw-toggle" data-target="cp-password"
                        aria-label="Show password"
                        style="position:absolute;top:50%;right:.6rem;transform:translateY(-50%);background:transparent;border:0;color:#94a3b8;cursor:pointer;padding:.25rem;">
                    <i class="bi bi-eye"></i>
                </button>
            </div>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="ra-btn ra-btn-primary">
                <i class="bi bi-shield-lock"></i>
                <span>Confirm</span>
            </button>
            <a href="/recruit/dashboard" class="ra-btn ra-btn-outline">Cancel</a>
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
            if (icon) icon.className = isPassword ? 'bi bi-eye-slash' : 'bi bi-eye';
        });
    });
})();
</script>

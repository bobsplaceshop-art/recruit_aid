<?php $invalid = !empty($invalidToken); ?>

<?php if ($invalid): ?>
    <h1 class="h4 mb-1">Link expired</h1>
    <p class="muted small mb-4">
        That reset link is no longer valid. Reset links expire after 1 hour for security.
    </p>

    <?php if (!empty($error)): ?>
        <div class="alert alert-error mb-3"><?= e((string) $error) ?></div>
    <?php endif; ?>

    <a href="/password/forgot" class="btn btn-primary w-100 py-2">
        <i class="bi bi-arrow-repeat me-1"></i>
        Request a new link
    </a>
<?php else: ?>
    <h1 class="h4 mb-1">Choose a new password</h1>
    <p class="muted small mb-4">
        For <strong><?= e($email ?? '') ?></strong>
    </p>

    <?php if (!empty($error)): ?>
        <div class="alert alert-error mb-3"><?= e((string) $error) ?></div>
    <?php endif; ?>

    <form method="POST" action="/password/reset" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= e($token ?? '') ?>">

        <div class="mb-3">
            <label for="password" class="form-label">New password</label>
            <div style="position:relative;">
                <input type="password" class="form-control" id="password" name="password"
                       required autofocus autocomplete="new-password" style="padding-right:2.5rem;">
                <button type="button" class="pw-toggle" data-target="password"
                        style="position:absolute;top:50%;right:.6rem;transform:translateY(-50%);background:transparent;border:0;color:#94a3b8;cursor:pointer;padding:.25rem;">
                    <i class="bi bi-eye"></i>
                </button>
            </div>
            <div class="mt-2" id="pw-meter-wrap" style="display:none;">
                <div style="display:flex;height:6px;border-radius:4px;overflow:hidden;background:#e2e8f0;">
                    <div id="pw-meter-bar" style="width:0%;height:100%;background:#dc2626;transition:width .2s,background .2s;"></div>
                </div>
                <div class="muted small mt-1">
                    Strength: <span id="pw-meter-label">—</span>
                    <span id="pw-meter-hint" class="ms-2"></span>
                </div>
            </div>
        </div>

        <div class="mb-3">
            <label for="password_confirm" class="form-label">Confirm new password</label>
            <div style="position:relative;">
                <input type="password" class="form-control" id="password_confirm"
                       name="password_confirm" required autocomplete="new-password" style="padding-right:2.5rem;">
                <button type="button" class="pw-toggle" data-target="password_confirm"
                        style="position:absolute;top:50%;right:.6rem;transform:translateY(-50%);background:transparent;border:0;color:#94a3b8;cursor:pointer;padding:.25rem;">
                    <i class="bi bi-eye"></i>
                </button>
            </div>
            <div class="muted small mt-1" id="pw-match-hint"></div>
        </div>

        <div class="muted small mb-3" style="font-size:.82rem;">
            Password must be at least 12 characters, or at least 10 characters with
            3 of 4 character types (uppercase, lowercase, digit, symbol).
        </div>

        <button type="submit" class="btn btn-primary w-100 py-2">
            <i class="bi bi-check2 me-1"></i>
            Set new password
        </button>
    </form>

    <script>
    (function(){
        var pw = document.getElementById('password');
        var confirm = document.getElementById('password_confirm');
        var wrap = document.getElementById('pw-meter-wrap');
        var bar = document.getElementById('pw-meter-bar');
        var label = document.getElementById('pw-meter-label');
        var hint = document.getElementById('pw-meter-hint');
        var matchHint = document.getElementById('pw-match-hint');

        function score(p) {
            var s = 0;
            var len = p.length;
            s += Math.min(50, len * 3);
            var classes = 0;
            if (/[a-z]/.test(p)) classes++;
            if (/[A-Z]/.test(p)) classes++;
            if (/[0-9]/.test(p)) classes++;
            if (/[^A-Za-z0-9]/.test(p)) classes++;
            s += classes * 8;
            if (len > 12) s += Math.min(20, (len - 12) * 2);
            if (/(.)\1{2,}/.test(p)) s -= 10;
            if (/(012|123|234|345|456|567|678|789|890)/.test(p)) s -= 10;
            return Math.max(0, Math.min(100, s));
        }

        function color(s) {
            if (s < 30) return '#dc2626';
            if (s < 60) return '#f59e0b';
            if (s < 80) return '#10b981';
            return '#059669';
        }

        function labelText(s) {
            if (s < 30) return 'Weak';
            if (s < 60) return 'Fair';
            if (s < 80) return 'Good';
            return 'Strong';
        }

        pw.addEventListener('input', function(){
            var p = pw.value;
            if (!p) { wrap.style.display = 'none'; return; }
            wrap.style.display = '';
            var sc = score(p);
            bar.style.width = sc + '%';
            bar.style.background = color(sc);
            label.textContent = labelText(sc);

            var hints = [];
            if (p.length < 10) hints.push('≥10 characters');
            if (!/[A-Z]/.test(p)) hints.push('uppercase letter');
            if (!/[0-9]/.test(p)) hints.push('number');
            if (!/[^A-Za-z0-9]/.test(p)) hints.push('symbol');
            hint.textContent = hints.length ? 'Add: ' + hints.join(', ') : '';
        });

        confirm.addEventListener('input', function(){
            if (!confirm.value) { matchHint.textContent = ''; return; }
            if (confirm.value === pw.value) {
                matchHint.style.color = '#10b981';
                matchHint.textContent = '✓ Passwords match';
            } else {
                matchHint.style.color = '#dc2626';
                matchHint.textContent = '✗ Passwords do not match';
            }
        });

        // Eye toggle for all password fields
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
<?php endif; ?>

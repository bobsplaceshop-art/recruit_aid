<?php
/**
 * Security settings form.
 * $settings — flat array of current values (from SystemSettingService::all('security.'))
 * $isSuper  — whether the current user can save
 */
$v = fn (string $key, int $default) => (int) ($settings[$key] ?? $default);
?>
<div class="ra-page-band">
    <div class="ra-page-band-row">
        <div>
            <div class="ra-page-band-label">
                Administration · Settings · Security
            </div>
            <h1 class="ra-page-band-title">Security</h1>
            <p class="ra-page-sub">Rate limits, password policy, and session settings.</p>
        </div>
        <div class="ra-page-band-actions">
            <a href="/admin/settings" class="ra-btn ra-btn-outline">
                <i class="bi bi-arrow-left"></i><span>Back</span>
            </a>
        </div>
    </div>
</div>

<?php if (!$isSuper): ?>
    <div class="ra-alert ra-alert-warn">
        <i class="bi bi-lock me-1"></i>
        You can view these settings, but only super administrators can change them.
    </div>
<?php endif; ?>

<?php if (!empty($success)): ?>
    <div class="ra-alert ra-alert-success"><?= e((string) $success) ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="ra-alert ra-alert-error"><?= e((string) $error) ?></div>
<?php endif; ?>

<form method="POST" action="/admin/settings/security" novalidate>
    <?= csrf_field() ?>

    <div class="ra-card">
        <h2 class="h5 mb-1">Login lockout</h2>
        <p class="muted small mb-3">
            Protects against brute-force attacks. If a user fails this many logins,
            they're locked out for the specified number of minutes.
        </p>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="ra-label" for="login_max_attempts">Max login attempts</label>
                <input type="number" id="login_max_attempts" name="login_max_attempts"
                       class="ra-input" <?= $isSuper ? '' : 'disabled' ?>
                       value="<?= $v('security.login.max_attempts', 5) ?>">
                <div class="muted small mt-1">Current: <?= $v('security.login.max_attempts', 5) ?> attempts</div>
            </div>
            <div class="col-md-6">
                <label class="ra-label" for="login_lockout_minutes">Lockout duration (minutes)</label>
                <input type="number" id="login_lockout_minutes" name="login_lockout_minutes"
                       class="ra-input" <?= $isSuper ? '' : 'disabled' ?>
                       value="<?= $v('security.login.lockout_minutes', 15) ?>">
                <div class="muted small mt-1">Current: <?= $v('security.login.lockout_minutes', 15) ?> minutes</div>
            </div>
        </div>
    </div>

    <div class="ra-card mt-3">
        <h2 class="h5 mb-1">Password reset limits</h2>
        <p class="muted small mb-3">
            Prevents password reset email spam and enumeration.
        </p>
        <div class="row g-3">
            <div class="col-md-3">
                <label class="ra-label">Request max</label>
                <input type="number" name="reset_max_attempts" class="ra-input" <?= $isSuper ? '' : 'disabled' ?>
                       value="<?= $v('security.password_reset.max_attempts', 3) ?>">
            </div>
            <div class="col-md-3">
                <label class="ra-label">Request window (min)</label>
                <input type="number" name="reset_window_minutes" class="ra-input" <?= $isSuper ? '' : 'disabled' ?>
                       value="<?= $v('security.password_reset.window_minutes', 60) ?>">
            </div>
            <div class="col-md-3">
                <label class="ra-label">Submit max</label>
                <input type="number" name="reset_submit_max_attempts" class="ra-input" <?= $isSuper ? '' : 'disabled' ?>
                       value="<?= $v('security.password_reset.submit_max_attempts', 5) ?>">
            </div>
            <div class="col-md-3">
                <label class="ra-label">Submit window (min)</label>
                <input type="number" name="reset_submit_window_minutes" class="ra-input" <?= $isSuper ? '' : 'disabled' ?>
                       value="<?= $v('security.password_reset.submit_window_minutes', 60) ?>">
            </div>
        </div>
    </div>

    <div class="ra-card mt-3">
        <h2 class="h5 mb-1">Password policy</h2>
        <p class="muted small mb-3">
            Minimum strength requirements for all user passwords.
        </p>
        <div class="row g-3">
            <div class="col-md-4">
                <label class="ra-label">Full length</label>
                <input type="number" name="password_min_length" class="ra-input" <?= $isSuper ? '' : 'disabled' ?>
                       value="<?= $v('security.password.min_length', 12) ?>">
                <div class="muted small mt-1">Must be at least this long, period.</div>
            </div>
            <div class="col-md-4">
                <label class="ra-label">Relaxed length</label>
                <input type="number" name="password_min_length_relaxed" class="ra-input" <?= $isSuper ? '' : 'disabled' ?>
                       value="<?= $v('security.password.min_length_relaxed', 10) ?>">
                <div class="muted small mt-1">Minimum when class diversity is met.</div>
            </div>
            <div class="col-md-4">
                <label class="ra-label">Required character classes</label>
                <input type="number" name="password_require_classes" class="ra-input" <?= $isSuper ? '' : 'disabled' ?>
                       value="<?= $v('security.password.require_classes', 3) ?>">
                <div class="muted small mt-1">Out of: uppercase, lowercase, digit, symbol.</div>
            </div>
        </div>
    </div>

    <div class="ra-card mt-3">
        <h2 class="h5 mb-1">Sessions &amp; re-authentication</h2>
        <p class="muted small mb-3">
            Session idle timeout and password re-confirmation window for sensitive actions.
        </p>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="ra-label">Session idle timeout (seconds)</label>
                <input type="number" name="session_idle_timeout" class="ra-input" <?= $isSuper ? '' : 'disabled' ?>
                       value="<?= $v('security.session.idle_timeout', 7200) ?>">
                <div class="muted small mt-1">Currently <?= number_format($v('security.session.idle_timeout', 7200) / 60) ?> minutes.</div>
            </div>
            <div class="col-md-6">
                <label class="ra-label">Re-auth window (minutes)</label>
                <input type="number" name="session_reauth_window" class="ra-input" <?= $isSuper ? '' : 'disabled' ?>
                       value="<?= $v('security.session.reauth_window_minutes', 15) ?>">
                <div class="muted small mt-1">How recently a super admin must confirm their password before sensitive saves.</div>
            </div>
        </div>
    </div>

    <?php if ($isSuper): ?>
        <div class="d-flex gap-2 mt-3">
            <button type="submit" class="ra-btn ra-btn-primary">
                <i class="bi bi-check2"></i>
                <span>Save security settings</span>
            </button>
            <a href="/admin/settings" class="ra-btn ra-btn-outline">Cancel</a>
        </div>
    <?php else: ?>
        <div class="muted small mt-3">
            <i class="bi bi-info-circle me-1"></i>
            Contact a super administrator to change these settings.
        </div>
    <?php endif; ?>
</form>

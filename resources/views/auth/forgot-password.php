<?php
use CorporateAid\Core\App;
$session = App::instance()->container()->get(\CorporateAid\Core\Session::class);
$sentTo = $session->getFlash('reset_sent_to');
?>

<?php if ($sentTo): ?>
    <!-- Success state: hide the form -->
    <div class="text-center">
        <div style="font-size:3rem;color:#10b981;margin-bottom:1rem;">
            <i class="bi bi-envelope-check"></i>
        </div>
        <h1 class="h4 mb-2">Check your inbox</h1>
        <p class="muted small mb-4">
            If an account exists for <strong><?= e($sentTo) ?></strong>,
            we've sent a password reset link. The link expires in 1 hour.
        </p>

        <div class="alert alert-warning text-start mb-3" style="font-size:.85rem;">
            <strong>Didn't get the email?</strong>
            <ul class="mb-0 mt-1" style="padding-left:1.2rem;">
                <li>Check your spam or junk folder</li>
                <li>Make sure you entered the right address</li>
                <li>Wait a minute and try again</li>
            </ul>
        </div>

        <a href="/password/forgot" class="btn btn-outline-light w-100 py-2 mb-2">
            <i class="bi bi-arrow-repeat me-1"></i>
            Try a different email
        </a>
        <a href="/login" class="muted small text-decoration-none d-block mt-2">
            <i class="bi bi-arrow-left me-1"></i>
            Back to sign in
        </a>
    </div>
<?php else: ?>
    <!-- Initial state: show the form -->
    <h1 class="h4 mb-1">Reset your password</h1>
    <p class="muted small mb-4">
        Enter the email address on your account. We'll send you a link to choose a new password.
    </p>

    <?php if (!empty($error)): ?>
        <div class="alert alert-error mb-3"><?= e((string) $error) ?></div>
    <?php endif; ?>

    <form method="POST" action="/password/forgot" novalidate>
        <?= csrf_field() ?>

        <div class="mb-3">
            <label for="email" class="form-label">Email address</label>
            <input type="email" class="form-control" id="email" name="email"
                   value="<?= e($email ?? '') ?>" required autofocus autocomplete="username">
        </div>

        <button type="submit" class="btn btn-primary w-100 py-2">
            <i class="bi bi-envelope-arrow-up me-1"></i>
            Send reset link
        </button>

        <div class="text-center mt-3">
            <a href="/login" class="muted small text-decoration-none">
                <i class="bi bi-arrow-left me-1"></i>
                Back to sign in
            </a>
        </div>
    </form>
<?php endif; ?>

<?php
/**
 * Password reset email.
 *
 * Expects:
 *   $user_name  (string)
 *   $reset_url  (string)
 *   $app_name   (string, optional)
 *   $expires_in (string, optional)  e.g. '1 hour'
 */
$appName = $app_name ?? 'Recruit Aid';
$expires = $expires_in ?? '1 hour';
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Reset your password</title>
</head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:Arial,Helvetica,sans-serif;color:#0f172a;">
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background:#f1f5f9;">
  <tr>
    <td align="center" style="padding:40px 20px;">
      <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="max-width:560px;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 2px 8px rgba(15,23,42,0.08);">
        <tr>
          <td style="background:linear-gradient(135deg,#0f4c81,#f5a623);height:6px;"></td>
        </tr>
        <tr>
          <td style="padding:32px 32px 8px;">
            <div style="font-size:20px;font-weight:700;color:#0f172a;margin:0 0 4px;">
              <?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?>
            </div>
            <div style="font-size:12px;color:#64748b;">
              Recruitment Operations Platform
            </div>
          </td>
        </tr>
        <tr>
          <td style="padding:8px 32px 24px;font-size:15px;line-height:1.55;color:#1e293b;">
            <p style="margin:0 0 12px;">Hi <?= htmlspecialchars($user_name ?? 'there', ENT_QUOTES, 'UTF-8') ?>,</p>
            <p style="margin:0 0 16px;">
              We received a request to reset your password. Click the button below to
              choose a new one. This link expires in <?= htmlspecialchars($expires, ENT_QUOTES, 'UTF-8') ?>.
            </p>
            <p style="margin:24px 0 8px;">
              <a href="<?= htmlspecialchars($reset_url, ENT_QUOTES, 'UTF-8') ?>"
                 style="display:inline-block;background:#0f4c81;color:#ffffff;text-decoration:none;padding:12px 24px;border-radius:8px;font-weight:600;">
                Reset password
              </a>
            </p>
            <p style="margin:0 0 16px;font-size:13px;color:#64748b;">
              If the button doesn't work, copy and paste this link into your browser:
              <br>
              <span style="word-break:break-all;"><?= htmlspecialchars($reset_url, ENT_QUOTES, 'UTF-8') ?></span>
            </p>
            <p style="margin:24px 0 0;font-size:13px;color:#64748b;">
              If you didn't request a password reset, you can safely ignore this email —
              your password won't change.
            </p>
          </td>
        </tr>
        <tr>
          <td style="padding:16px 32px 24px;font-size:12px;color:#94a3b8;border-top:1px solid #e2e8f0;">
            Sent by <?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?>.
          </td>
        </tr>
      </table>
    </td>
  </tr>
</table>
</body>
</html>

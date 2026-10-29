-- =====================================================================
-- 0034_seed_email_templates.sql
-- Seeds DB-backed email templates. Body content uses the branded
-- EmailLayout shell — the layout is applied at render time, so the
-- HTML here is only the inner body.
-- =====================================================================

INSERT INTO `core_email_templates` (`template_key`, `locale`, `subject`, `body_html`, `body_text`, `variables`) VALUES

('offer.sent', 'en',
 'Your offer from {{product_name}}',
 '<p style="font-size:16px;line-height:1.6;margin:0 0 16px;">Hi {{candidate_first_name}},</p>
<p style="font-size:16px;line-height:1.6;margin:0 0 16px;">We are delighted to extend a formal offer for the position of <strong>{{vacancy_title}}</strong> at <strong>{{client_name}}</strong>.</p>
<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="width:100%;background:#f9fafb;border-radius:8px;margin:0 0 24px;">
<tr><td style="padding:20px;">
<div style="font-size:12px;color:#6b7280;text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px;">Salary</div>
<div style="font-size:24px;font-weight:700;color:#111827;margin-bottom:16px;">{{salary}}</div>
<div style="font-size:12px;color:#6b7280;text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px;">Start date</div>
<div style="font-size:16px;color:#111827;margin-bottom:16px;">{{start_date}}</div>
<div style="font-size:12px;color:#6b7280;text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px;">Offer expires</div>
<div style="font-size:16px;color:#111827;">{{expires_at}}</div>
</td></tr>
</table>
<p style="font-size:16px;line-height:1.6;margin:0 0 24px;">To accept this offer, please reply to this email or contact us before the expiry date.</p>
<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 8px;"><tr><td style="background:#0d6efd;border-radius:8px;"><a href="{{offer_url}}" style="display:inline-block;padding:12px 24px;color:#ffffff;text-decoration:none;font-weight:600;font-size:14px;">View offer</a></td></tr></table>
<p style="font-size:16px;line-height:1.6;margin:24px 0 0;">We look forward to welcoming you to the team.</p>
<p style="font-size:16px;line-height:1.6;margin:16px 0 0;">Warm regards,<br>{{sender_name}}</p>',
 'Hi {{candidate_first_name}},

We are delighted to extend a formal offer for the position of {{vacancy_title}} at {{client_name}}.

Salary: {{salary}}
Start date: {{start_date}}
Offer expires: {{expires_at}}

To accept this offer, please reply to this email or contact us before the expiry date.

View offer: {{offer_url}}

Warm regards,
{{sender_name}}',
 '["candidate_first_name","vacancy_title","client_name","salary","start_date","expires_at","offer_url","sender_name","product_name"]'),

('interview.scheduled', 'en',
 'Interview scheduled — {{vacancy_title}}',
 '<p style="font-size:16px;line-height:1.6;margin:0 0 16px;">Hi {{candidate_first_name}},</p>
<p style="font-size:16px;line-height:1.6;margin:0 0 24px;">We have scheduled an interview for the <strong>{{vacancy_title}}</strong> role at <strong>{{client_name}}</strong>.</p>
<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="width:100%;background:#f9fafb;border-radius:8px;margin:0 0 24px;">
<tr><td style="padding:20px;">
<div style="font-size:12px;color:#6b7280;text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px;">Date &amp; time</div>
<div style="font-size:18px;font-weight:600;color:#111827;margin-bottom:16px;">{{scheduled_at}}</div>
<div style="font-size:12px;color:#6b7280;text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px;">Duration</div>
<div style="font-size:16px;color:#111827;margin-bottom:16px;">{{duration}} minutes</div>
<div style="font-size:12px;color:#6b7280;text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px;">Type</div>
<div style="font-size:16px;color:#111827;margin-bottom:16px;">{{interview_type}}</div>
<div style="font-size:12px;color:#6b7280;text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px;">Location</div>
<div style="font-size:16px;color:#111827;">{{location}}</div>
</td></tr>
</table>
{{notes_block}}
<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 8px;"><tr><td style="background:#0d6efd;border-radius:8px;"><a href="{{interview_url}}" style="display:inline-block;padding:12px 24px;color:#ffffff;text-decoration:none;font-weight:600;font-size:14px;">View details</a></td></tr></table>
<p style="font-size:16px;line-height:1.6;margin:24px 0 0;">Best regards,<br>{{sender_name}}</p>',
 'Hi {{candidate_first_name}},

Your interview for the {{vacancy_title}} role at {{client_name}} has been scheduled.

Date & time: {{scheduled_at}}
Duration: {{duration}} minutes
Type: {{interview_type}}
Location: {{location}}

View details: {{interview_url}}

Best regards,
{{sender_name}}',
 '["candidate_first_name","vacancy_title","client_name","scheduled_at","duration","interview_type","location","notes_block","interview_url","sender_name","product_name"]'),

('password.reset', 'en',
 'Reset your {{product_name}} password',
 '<p style="font-size:16px;line-height:1.6;margin:0 0 16px;">Hi {{user_name}},</p>
<p style="font-size:16px;line-height:1.6;margin:0 0 24px;">We received a request to reset your password. If you did not make this request, you can safely ignore this email.</p>
<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 24px;"><tr><td style="background:#0d6efd;border-radius:8px;"><a href="{{reset_url}}" style="display:inline-block;padding:12px 24px;color:#ffffff;text-decoration:none;font-weight:600;font-size:14px;">Reset password</a></td></tr></table>
<p style="font-size:14px;line-height:1.6;color:#6b7280;margin:0 0 8px;">Or copy this link into your browser:</p>
<p style="font-size:14px;line-height:1.6;word-break:break-all;color:#111827;margin:0 0 24px;">{{reset_url}}</p>
<p style="font-size:14px;line-height:1.6;color:#6b7280;margin:0;">This link expires in {{expires_in}}.</p>',
 'Hi {{user_name}},

We received a request to reset your password. If you did not make this request, you can safely ignore this email.

Reset your password: {{reset_url}}

This link expires in {{expires_in}}.',
 '["user_name","reset_url","expires_in","product_name"]'),

('welcome', 'en',
 'Welcome to {{product_name}}',
 '<p style="font-size:16px;line-height:1.6;margin:0 0 16px;">Hi {{user_name}},</p>
<p style="font-size:16px;line-height:1.6;margin:0 0 16px;">Welcome to {{product_name}}. Your account is ready.</p>
<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 24px;"><tr><td style="background:#0d6efd;border-radius:8px;"><a href="{{login_url}}" style="display:inline-block;padding:12px 24px;color:#ffffff;text-decoration:none;font-weight:600;font-size:14px;">Sign in</a></td></tr></table>
<p style="font-size:16px;line-height:1.6;margin:0;">Best regards,<br>The {{product_name}} team</p>',
 'Hi {{user_name}},

Welcome to {{product_name}}. Your account is ready.

Sign in: {{login_url}}

Best regards,
The {{product_name}} team',
 '["user_name","login_url","product_name"]')

ON DUPLICATE KEY UPDATE
    `subject` = VALUES(`subject`),
    `body_html` = VALUES(`body_html`),
    `body_text` = VALUES(`body_text`),
    `variables` = VALUES(`variables`);

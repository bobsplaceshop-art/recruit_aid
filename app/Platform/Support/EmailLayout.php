<?php
declare(strict_types=1);

namespace CorporateAid\Platform\Support;

/**
 * Premium HTML email shell. Table-based for maximum client
 * compatibility (Outlook, Gmail, Apple Mail). Inline styles only.
 * Brand color pulled from app_setting('branding.primary_color').
 */
final class EmailLayout
{
    /**
     * Wrap inner content in the branded layout.
     *
     * @param array{app_name?:string, product_name?:string, logo_url?:string, primary_color?:string, tagline?:string, footer_text?:string}|null $brand
     */
    public function wrap(string $innerHtml, ?array $brand = null): string
    {
        $brand = $brand ?? [];

        $primary     = $brand['primary_color'] ?? $this->safeStr(app_setting('branding.primary_color', '#0d6efd'), '#0d6efd');
        $productName = $brand['product_name']  ?? $this->safeStr(app_setting('branding.app_name', 'Recruit Aid'), 'Recruit Aid');
        $tagline     = $brand['tagline']       ?? $this->safeStr(app_setting('branding.tagline', 'Recruitment Operations Platform'), 'Recruitment Operations Platform');
        $logoUrl     = $brand['logo_url'] ?? '';
        $footerText  = $brand['footer_text'] ?? 'This email was sent by ' . $productName . '. If you did not expect this, please ignore it.';

        if ($logoUrl === '') {
            $logoPath = $this->safeStr(app_setting('branding.logo_path', ''), '');
            if ($logoPath !== '') {
                $appUrl  = rtrim($this->safeStr(app_setting('app.url', 'http://localhost:8080'), 'http://localhost:8080'), '/');
                $logoUrl = $appUrl . '/files/' . $logoPath;
            }
        }

        $year = date('Y');
        $logoBlock = $logoUrl !== ''
            ? '<img src="' . htmlspecialchars($logoUrl, ENT_QUOTES) . '" alt="' . htmlspecialchars($productName, ENT_QUOTES) . '" style="max-height:40px;display:block;margin:0 auto 12px;">'
            : '<div style="width:48px;height:48px;background:' . htmlspecialchars($primary, ENT_QUOTES) . ';border-radius:12px;margin:0 auto 12px;"></div>';

        return <<<HTML
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>{$productName}</title>
</head>
<body style="margin:0;padding:0;background:#f4f6f9;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#1f2937;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f4f6f9;padding:32px 16px;">
<tr><td align="center">
<table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.06);">
<tr><td style="background:{$primary};padding:32px 32px 24px;text-align:center;">
{$logoBlock}
<div style="color:#ffffff;font-size:20px;font-weight:700;letter-spacing:-.01em;">{$productName}</div>
<div style="color:rgba(255,255,255,.85);font-size:13px;margin-top:4px;">{$tagline}</div>
</td></tr>
<tr><td style="padding:32px;">
{$innerHtml}
</td></tr>
<tr><td style="padding:20px 32px;background:#f9fafb;border-top:1px solid #e5e7eb;text-align:center;">
<div style="font-size:12px;color:#6b7280;line-height:1.6;">{$footerText}</div>
<div style="font-size:11px;color:#9ca3af;margin-top:8px;">&copy; {$year} {$productName}. All rights reserved.</div>
</td></tr>
</table>
</td></tr>
</table>
</body>
</html>
HTML;
    }

    /**
     * Guarded cast for values that come from app_setting() (mixed).
     */
    private function safeStr(mixed $value, string $default = ''): string
    {
        return is_scalar($value) ? (string) $value : $default;
    }
}

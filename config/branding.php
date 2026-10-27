<?php
declare(strict_types=1);

/**
 * Recruit Aid - branding defaults.
 * The installer and admin UI overwrite these (via system_settings in the
 * database), so a buyer can rebrand without touching this file.
 */

return [
    'product_name'     => 'Recruit Aid',
    'tagline'          => 'Recruitment Operations Platform',
    'company_name'     => 'Recruit Aid',
    'company_email'    => '',
    'support_email'    => '',
    'primary_color'    => '#0f4c81',
    'accent_color'     => '#f5a623',
    'logo_path'        => null,
    'favicon_path'     => null,
    'legal_entity'     => '',
    'privacy_url'      => '',
    'terms_url'        => '',
    'email_from_name'  => 'Recruit Aid',
    'email_from_email' => 'no-reply@recruitaid.test',
];

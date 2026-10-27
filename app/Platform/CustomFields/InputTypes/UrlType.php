<?php
declare(strict_types=1);

namespace CorporateAid\Platform\CustomFields\InputTypes;

use CorporateAid\Platform\CustomFields\InputType;

final class UrlType implements InputType
{
    public function key(): string { return 'url'; }
    public function label(): string { return 'Website URL'; }
    public function supportsOptions(): bool { return false; }
    public function configFields(): array { return []; }

    public function validate(mixed $value, array $constraints, bool $isRequired): array
    {
        $v = is_string($value) ? trim($value) : '';
        $errors = [];
        if ($isRequired && $v === '') { $errors[] = 'This field is required.'; return $errors; }
        if ($v === '') return $errors;

        $parts = parse_url($v);
        if ($parts === false || empty($parts['host']) || empty($parts['scheme'])
            || !in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            $errors[] = 'Must be a valid URL starting with http:// or https://.';
        }
        return $errors;
    }

    public function cast(mixed $value): ?string
    {
        $v = is_string($value) ? trim($value) : '';
        return $v === '' ? null : $v;
    }

    public function humanize(?string $value): string { return $value ?? ''; }

    public function render(string $fieldKey, string $fieldLabel, ?string $currentValue, array $constraints, array $options, ?string $helpText, bool $isRequired, array $errors = []): string
    {
        $html  = '<div class="ra-field">';
        $html .= '<label class="ra-label" for="cf_' . e($fieldKey) . '">' . e($fieldLabel);
        if ($isRequired) $html .= ' <span style="color:var(--ra-error-fg)">*</span>';
        $html .= '</label>';
        $html .= '<input type="url" id="cf_' . e($fieldKey) . '" name="cf[' . e($fieldKey) . ']" value="' . e((string) $currentValue) . '" placeholder="https://" class="ra-input"' . ($isRequired ? ' required' : '') . '>';
        if ($helpText) $html .= '<div class="muted small mt-1">' . e($helpText) . '</div>';
        foreach ($errors as $err) $html .= '<div class="small" style="color:var(--ra-error-fg);margin-top:.25rem;">' . e($err) . '</div>';
        $html .= '</div>';
        return $html;
    }
}

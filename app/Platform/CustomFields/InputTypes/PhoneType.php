<?php
declare(strict_types=1);

namespace CorporateAid\Platform\CustomFields\InputTypes;

use CorporateAid\Platform\CustomFields\InputType;

final class PhoneType implements InputType
{
    public function key(): string { return 'phone'; }
    public function label(): string { return 'Phone number'; }
    public function supportsOptions(): bool { return false; }
    public function configFields(): array { return []; }

    public function validate(mixed $value, array $constraints, bool $isRequired): array
    {
        $v = is_string($value) ? trim($value) : '';
        $errors = [];
        if ($isRequired && $v === '') { $errors[] = 'This field is required.'; return $errors; }
        if ($v === '') return $errors;
        if (!preg_match('/^[0-9 +().\-]{5,30}$/', $v)) {
            $errors[] = 'Must be a valid phone number (5-30 chars, digits, spaces, +, -, (), .).';
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
        $html .= '<input type="tel" id="cf_' . e($fieldKey) . '" name="cf[' . e($fieldKey) . ']" value="' . e((string) $currentValue) . '" class="ra-input"' . ($isRequired ? ' required' : '') . '>';
        if ($helpText) $html .= '<div class="muted small mt-1">' . e($helpText) . '</div>';
        foreach ($errors as $err) $html .= '<div class="small" style="color:var(--ra-error-fg);margin-top:.25rem;">' . e($err) . '</div>';
        $html .= '</div>';
        return $html;
    }
}

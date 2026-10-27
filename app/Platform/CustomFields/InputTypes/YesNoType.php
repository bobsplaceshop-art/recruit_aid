<?php
declare(strict_types=1);

namespace CorporateAid\Platform\CustomFields\InputTypes;

use CorporateAid\Platform\CustomFields\InputType;

final class YesNoType implements InputType
{
    public function key(): string { return 'yes_no'; }
    public function label(): string { return 'Yes / No (radio)'; }
    public function supportsOptions(): bool { return false; }
    public function configFields(): array { return []; }

    public function validate(mixed $value, array $constraints, bool $isRequired): array
    {
        $v = is_string($value) ? trim($value) : '';
        $errors = [];
        if ($isRequired && $v === '') { $errors[] = 'This field is required.'; return $errors; }
        if ($v === '') return $errors;
        if (!in_array($v, ['yes', 'no'], true)) $errors[] = 'Must be Yes or No.';
        return $errors;
    }

    public function cast(mixed $value): ?string
    {
        $v = is_string($value) ? strtolower(trim($value)) : '';
        return in_array($v, ['yes', 'no'], true) ? $v : null;
    }

    public function humanize(?string $value): string
    {
        return match ($value) {
            'yes' => 'Yes',
            'no'  => 'No',
            default => '',
        };
    }

    public function render(string $fieldKey, string $fieldLabel, ?string $currentValue, array $constraints, array $options, ?string $helpText, bool $isRequired, array $errors = []): string
    {
        $html  = '<div class="ra-field">';
        $html .= '<div class="ra-label">' . e($fieldLabel);
        if ($isRequired) $html .= ' <span style="color:var(--ra-error-fg)">*</span>';
        $html .= '</div>';
        $html .= '<div style="display:flex;gap:1.25rem;padding:.25rem 0;">';
        $html .= '<label style="display:flex;align-items:center;gap:.4rem;font-size:.9rem;"><input type="radio" name="cf[' . e($fieldKey) . ']" value="yes"' . ($currentValue === 'yes' ? ' checked' : '') . '> Yes</label>';
        $html .= '<label style="display:flex;align-items:center;gap:.4rem;font-size:.9rem;"><input type="radio" name="cf[' . e($fieldKey) . ']" value="no"'  . ($currentValue === 'no'  ? ' checked' : '') . '> No</label>';
        $html .= '</div>';
        if ($helpText) $html .= '<div class="muted small mt-1">' . e($helpText) . '</div>';
        foreach ($errors as $err) $html .= '<div class="small" style="color:var(--ra-error-fg);margin-top:.25rem;">' . e($err) . '</div>';
        $html .= '</div>';
        return $html;
    }
}

<?php
declare(strict_types=1);

namespace CorporateAid\Platform\CustomFields\InputTypes;

use CorporateAid\Platform\CustomFields\InputType;

final class CheckboxType implements InputType
{
    public function key(): string { return 'checkbox'; }
    public function label(): string { return 'Checkbox (yes/no)'; }
    public function supportsOptions(): bool { return false; }
    public function configFields(): array { return []; }

    public function validate(mixed $value, array $constraints, bool $isRequired): array
    {
        $checked = ($value === '1' || $value === 1 || $value === true || $value === 'on');
        if ($isRequired && !$checked) return ['You must tick this box.'];
        return [];
    }

    public function cast(mixed $value): ?string
    {
        $checked = ($value === '1' || $value === 1 || $value === true || $value === 'on');
        return $checked ? '1' : '0';
    }

    public function humanize(?string $value): string
    {
        return ($value === '1') ? 'Yes' : 'No';
    }

    public function render(string $fieldKey, string $fieldLabel, ?string $currentValue, array $constraints, array $options, ?string $helpText, bool $isRequired, array $errors = []): string
    {
        $checked = ($currentValue === '1') ? ' checked' : '';
        $html  = '<div class="ra-field">';
        $html .= '<label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;">'
               . '<input type="checkbox" id="cf_' . e($fieldKey) . '" name="cf[' . e($fieldKey) . ']" value="1"' . $checked . '>'
               . '<span class="ra-label" style="margin:0;">' . e($fieldLabel) . ($isRequired ? ' <span style="color:var(--ra-error-fg)">*</span>' : '') . '</span>'
               . '</label>';
        if ($helpText) $html .= '<div class="muted small mt-1">' . e($helpText) . '</div>';
        foreach ($errors as $err) $html .= '<div class="small" style="color:var(--ra-error-fg);margin-top:.25rem;">' . e($err) . '</div>';
        $html .= '</div>';
        return $html;
    }
}

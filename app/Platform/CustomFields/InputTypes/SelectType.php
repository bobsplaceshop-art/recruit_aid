<?php
declare(strict_types=1);

namespace CorporateAid\Platform\CustomFields\InputTypes;

use CorporateAid\Platform\CustomFields\InputType;

final class SelectType implements InputType
{
    public function key(): string { return 'select'; }
    public function label(): string { return 'Dropdown (single choice)'; }
    public function supportsOptions(): bool { return true; }
    public function configFields(): array { return []; }

    public function validate(mixed $value, array $constraints, bool $isRequired): array
    {
        $v = is_string($value) ? trim($value) : '';
        $errors = [];
        if ($isRequired && $v === '') { $errors[] = 'This field is required.'; return $errors; }
        if ($v === '') return $errors;

        // Options come from $constraints['options'] when validating — the validator
        // passes the field's options via constraints so we don't need a 4th parameter.
        $options = $constraints['_options'] ?? [];
        if (!is_array($options) || !in_array($v, array_map('strval', $options), true)) {
            $errors[] = 'Must be one of the allowed options.';
        }
        return $errors;
    }

    public function cast(mixed $value): ?string
    {
        $v = is_string($value) ? trim($value) : '';
        return $v === '' ? null : $v;
    }

    public function humanize(?string $value): string { return $value ?? ''; }

    public function render(string $fieldKey, string $fieldLabel, ?string $currentValue, array $constraints, array $options, ?string $helpText
, bool $isRequired, array $errors = []): string
    {
        $exts = $constraints['extensions'] ?? 'pdf,doc,docx,png,jpg';
        $maxMb = (int) ($constraints['max_size_mb'] ?? 8);
        $html  = '<div class="ra-field">';
        $html .= '<label class="ra-label" for="cf_' . e($fieldKey) . '">' . e($fieldLabel);
        if ($isRequired) $html .= ' <span style="color:var(--ra-error-fg)">*</span>';
        $html .= '</label>';
        $html .= '<input type="file" id="cf_' . e($fieldKey) . '" name="cf_' . e($fieldKey) . '_file" class="ra-input"' . ($isRequired ? ' required' : '') . '>';
        $html .= '<div class="muted small mt-1">Allowed: ' . e($exts) . ' · Max: ' . $maxMb . ' MB';
        if ($helpText) $html .= ' · ' . e($helpText);
        $html .= '</div>';
        if ($currentValue) $html .= '<div class="muted small mt-1">Currently attached: #' . e($currentValue) . '</div>';
        foreach ($errors as $err) $html .= '<div class="small" style="color:var(--ra-error-fg);margin-top:.25rem;">' . e($err) . '</div>';
        $html .= '</div>';
        return $html;
    }
}

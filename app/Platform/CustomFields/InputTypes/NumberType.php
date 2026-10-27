<?php
declare(strict_types=1);

namespace CorporateAid\Platform\CustomFields\InputTypes;

use CorporateAid\Platform\CustomFields\InputType;

final class NumberType implements InputType
{
    public function key(): string { return 'number'; }
    public function label(): string { return 'Number'; }
    public function supportsOptions(): bool { return false; }

    public function configFields(): array
    {
        return [
            ['key' => 'min',      'label' => 'Minimum value', 'type' => 'number'],
            ['key' => 'max',      'label' => 'Maximum value', 'type' => 'number'],
            ['key' => 'decimals', 'label' => 'Decimal places allowed (0-10)', 'type' => 'number'],
        ];
    }

    public function validate(mixed $value, array $constraints, bool $isRequired): array
    {
        $v = is_string($value) ? trim($value) : (is_numeric($value) ? (string) $value : '');
        $errors = [];
        if ($isRequired && $v === '') {
            $errors[] = 'This field is required.';
            return $errors;
        }
        if ($v === '') return $errors;

        if (!is_numeric($v)) {
            $errors[] = 'Must be a number.';
            return $errors;
        }

        $num = (float) $v;
        if (isset($constraints['min']) && $num < (float) $constraints['min']) {
            $errors[] = 'Must be at least ' . $constraints['min'] . '.';
        }
        if (isset($constraints['max']) && $num > (float) $constraints['max']) {
            $errors[] = 'Must be at most ' . $constraints['max'] . '.';
        }
        if (isset($constraints['decimals'])) {
            $dec = max(0, min(10, (int) $constraints['decimals']));
            $parts = explode('.', $v);
            if (isset($parts[1]) && strlen($parts[1]) > $dec) {
                $errors[] = "Must have at most {$dec} decimal place(s).";
            }
        }
        return $errors;
    }

    public function cast(mixed $value): ?string
    {
        $v = is_string($value) ? trim($value) : (is_numeric($value) ? (string) $value : '');
        return $v === '' ? null : $v;
    }

    public function humanize(?string $value): string
    {
        return $value ?? '';
    }

    public function render(string $fieldKey, string $fieldLabel, ?string $currentValue, array $constraints, array $options, ?string $helpText, bool $isRequired, array $errors = []): string
    {
        $step = '1';
        if (isset($constraints['decimals']) && (int) $constraints['decimals'] > 0) {
            $step = '0.' . str_repeat('0', (int) $constraints['decimals'] - 1) . '1';
        }
        $html  = '<div class="ra-field">';
        $html .= '<label class="ra-label" for="cf_' . e($fieldKey) . '">' . e($fieldLabel);
        if ($isRequired) $html .= ' <span style="color:var(--ra-error-fg)">*</span>';
        $html .= '</label>';
        $html .= '<input type="number" id="cf_' . e($fieldKey) . '" name="cf[' . e($fieldKey) . ']"'
               . ' value="' . e((string) $currentValue) . '"'
               . ' step="' . $step . '"'
               . (isset($constraints['min']) ? ' min="' . (float) $constraints['min'] . '"' : '')
               . (isset($constraints['max']) ? ' max="' . (float) $constraints['max'] . '"' : '')
               . ' class="ra-input"' . ($isRequired ? ' required' : '') . '>';
        if ($helpText) $html .= '<div class="muted small mt-1">' . e($helpText) . '</div>';
        foreach ($errors as $err) $html .= '<div class="small" style="color:var(--ra-error-fg);margin-top:.25rem;">' . e($err) . '</div>';
        $html .= '</div>';
        return $html;
    }
}

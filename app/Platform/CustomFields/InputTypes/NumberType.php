<?php
declare(strict_types=1);

namespace CorporateAid\Platform\CustomFields\InputTypes;

use CorporateAid\Core\Row;
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

    /** @param array<string, mixed> $constraints
     *  @return string[] */
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
        $min = Row::nullableFloat($constraints, 'min');
        if ($min !== null && $num < $min) {
            $errors[] = 'Must be at least ' . $min . '.';
        }
        $max = Row::nullableFloat($constraints, 'max');
        if ($max !== null && $num > $max) {
            $errors[] = 'Must be at most ' . $max . '.';
        }
        if (array_key_exists('decimals', $constraints) && $constraints['decimals'] !== null && $constraints['decimals'] !== '') {
            $dec = max(0, min(10, Row::int($constraints, 'decimals', 0)));
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

    /** @param array<string, mixed> $constraints
     *  @param string[] $options
     *  @param string[] $errors */
    public function render(string $fieldKey, string $fieldLabel, ?string $currentValue, array $constraints, array $options, ?string $helpText, bool $isRequired, array $errors = []): string
    {
        $step = '1';
        $decimals = Row::int($constraints, 'decimals', 0);
        if ($decimals > 0) {
            $step = '0.' . str_repeat('0', $decimals - 1) . '1';
        }
        $minAttr = '';
        $maxAttr = '';
        $min = Row::nullableFloat($constraints, 'min');
        if ($min !== null) $minAttr = ' min="' . $min . '"';
        $max = Row::nullableFloat($constraints, 'max');
        if ($max !== null) $maxAttr = ' max="' . $max . '"';

        $html  = '<div class="ra-field">';
        $html .= '<label class="ra-label" for="cf_' . e($fieldKey) . '">' . e($fieldLabel);
        if ($isRequired) $html .= ' <span style="color:var(--ra-error-fg)">*</span>';
        $html .= '</label>';
        $html .= '<input type="number" id="cf_' . e($fieldKey) . '" name="cf[' . e($fieldKey) . ']"'
               . ' value="' . e((string) $currentValue) . '"'
               . ' step="' . $step . '"'
               . $minAttr
               . $maxAttr
               . ' class="ra-input"' . ($isRequired ? ' required' : '') . '>';
        if ($helpText) $html .= '<div class="muted small mt-1">' . e($helpText) . '</div>';
        foreach ($errors as $err) $html .= '<div class="small" style="color:var(--ra-error-fg);margin-top:.25rem;">' . e($err) . '</div>';
        $html .= '</div>';
        return $html;
    }
}

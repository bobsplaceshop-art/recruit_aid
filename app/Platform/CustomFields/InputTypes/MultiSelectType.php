<?php
declare(strict_types=1);

namespace CorporateAid\Platform\CustomFields\InputTypes;

use CorporateAid\Platform\CustomFields\InputType;

final class MultiSelectType implements InputType
{
    public function key(): string { return 'multi_select'; }
    public function label(): string { return 'Multi-select (checkboxes)'; }
    public function supportsOptions(): bool { return true; }
    public function configFields(): array { return []; }

    /** @param array<string, mixed> $constraints
     *  @return string[] */
    public function validate(mixed $value, array $constraints, bool $isRequired): array
    {
        $values = $this->normalizeValues($value);
        $errors = [];
        if ($isRequired && empty($values)) { $errors[] = 'This field is required.'; return $errors; }
        if (empty($values)) return $errors;

        $rawOptions = $constraints['_options'] ?? [];
        $optionsStr = [];
        if (is_array($rawOptions)) {
            foreach ($rawOptions as $o) {
                if (is_scalar($o)) $optionsStr[] = (string) $o;
            }
        }
        foreach ($values as $v) {
            if (!in_array($v, $optionsStr, true)) {
                $errors[] = 'Contains an invalid option.';
                break;
            }
        }
        return $errors;
    }

    public function cast(mixed $value): ?string
    {
        $values = $this->normalizeValues($value);
        if (empty($values)) return null;
        $encoded = json_encode(array_values($values));
        return $encoded !== false ? $encoded : null;
    }

    public function humanize(?string $value): string
    {
        if ($value === null || $value === '') return '';
        $decoded = json_decode($value, true);
        if (!is_array($decoded)) return $value;
        $parts = [];
        foreach ($decoded as $v) {
            if (is_scalar($v)) $parts[] = (string) $v;
        }
        return implode(', ', $parts);
    }

    /** @return string[] */
    private function normalizeValues(mixed $value): array
    {
        if (is_array($value)) {
            $out = [];
            foreach ($value as $v) {
                if (is_scalar($v)) $out[] = (string) $v;
            }
            return $out;
        }
        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                $out = [];
                foreach ($decoded as $v) {
                    if (is_scalar($v)) $out[] = (string) $v;
                }
                return $out;
            }
            return [$value];
        }
        return [];
    }

    /** @param array<string, mixed> $constraints
     *  @param string[] $options
     *  @param string[] $errors */
    public function render(string $fieldKey, string $fieldLabel, ?string $currentValue, array $constraints, array $options, ?string $helpText, bool $isRequired, array $errors = []): string
    {
        $current = $this->normalizeValues($currentValue);
        $html  = '<div class="ra-field">';
        $html .= '<div class="ra-label">' . e($fieldLabel);
        if ($isRequired) $html .= ' <span style="color:var(--ra-error-fg)">*</span>';
        $html .= '</div>';
        $html .= '<div style="display:flex;flex-wrap:wrap;gap:.75rem;padding:.5rem 0;">';
        foreach ($options as $opt) {
            $optStr = (string) $opt;
            $checked = in_array($optStr, $current, true) ? ' checked' : '';
            $html .= '<label style="display:flex;align-items:center;gap:.4rem;font-size:.9rem;">'
                   . '<input type="checkbox" name="cf[' . e($fieldKey) . '][]" value="' . e($optStr) . '"' . $checked . '>'
                   . '<span>' . e($optStr) . '</span></label>';
        }
        $html .= '</div>';
        if ($helpText) $html .= '<div class="muted small mt-1">' . e($helpText) . '</div>';
        foreach ($errors as $err) $html .= '<div class="small" style="color:var(--ra-error-fg);margin-top:.25rem;">' . e($err) . '</div>';
        $html .= '</div>';
        return $html;
    }
}

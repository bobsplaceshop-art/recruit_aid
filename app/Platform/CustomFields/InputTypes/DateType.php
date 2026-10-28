<?php
declare(strict_types=1);

namespace CorporateAid\Platform\CustomFields\InputTypes;

use CorporateAid\Core\Row;
use CorporateAid\Platform\CustomFields\InputType;

final class DateType implements InputType
{
    public function key(): string { return 'date'; }
    public function label(): string { return 'Date'; }
    public function supportsOptions(): bool { return false; }

    public function configFields(): array
    {
        return [
            ['key' => 'earliest', 'label' => 'Earliest allowed (YYYY-MM-DD)', 'type' => 'date'],
            ['key' => 'latest',   'label' => 'Latest allowed (YYYY-MM-DD)',   'type' => 'date'],
            ['key' => 'min_age',  'label' => 'Minimum age (years)',           'type' => 'number', 'placeholder' => 'e.g. 18'],
            ['key' => 'max_age',  'label' => 'Maximum age (years)',           'type' => 'number', 'placeholder' => 'e.g. 65'],
        ];
    }

    /** @param array<string, mixed> $constraints
     *  @return string[] */
    public function validate(mixed $value, array $constraints, bool $isRequired): array
    {
        $v = is_string($value) ? trim($value) : '';
        $errors = [];
        if ($isRequired && $v === '') {
            $errors[] = 'This field is required.';
            return $errors;
        }
        if ($v === '') return $errors;

        $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $v);
        if (!$dt || $dt->format('Y-m-d') !== $v) {
            $errors[] = 'Must be a valid date (YYYY-MM-DD).';
            return $errors;
        }

        $earliest = Row::str($constraints, 'earliest');
        if ($earliest !== '' && $v < $earliest) {
            $errors[] = 'Must be on or after ' . $earliest . '.';
        }
        $latest = Row::str($constraints, 'latest');
        if ($latest !== '' && $v > $latest) {
            $errors[] = 'Must be on or before ' . $latest . '.';
        }

        $today = new \DateTimeImmutable('today');
        $age = $today->diff($dt)->y;

        $minAge = Row::nullableInt($constraints, 'min_age');
        if ($minAge !== null && $age < $minAge) {
            $errors[] = "Must be at least {$minAge} years old (currently {$age}).";
        }
        $maxAge = Row::nullableInt($constraints, 'max_age');
        if ($maxAge !== null && $age > $maxAge) {
            $errors[] = "Must be at most {$maxAge} years old (currently {$age}).";
        }

        return $errors;
    }

    public function cast(mixed $value): ?string
    {
        $v = is_string($value) ? trim($value) : '';
        return $v === '' ? null : $v;
    }

    public function humanize(?string $value): string
    {
        if ($value === null || $value === '') return '';
        $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $value);
        return $dt ? $dt->format('d M Y') : $value;
    }

    /** @param array<string, mixed> $constraints
     *  @param string[] $options
     *  @param string[] $errors */
    public function render(string $fieldKey, string $fieldLabel, ?string $currentValue, array $constraints, array $options, ?string $helpText, bool $isRequired, array $errors = []): string
    {
        $earliest = Row::str($constraints, 'earliest');
        $latest = Row::str($constraints, 'latest');
        $html  = '<div class="ra-field">';
        $html .= '<label class="ra-label" for="cf_' . e($fieldKey) . '">' . e($fieldLabel);
        if ($isRequired) $html .= ' <span style="color:var(--ra-error-fg)">*</span>';
        $html .= '</label>';
        $html .= '<input type="date" id="cf_' . e($fieldKey) . '" name="cf[' . e($fieldKey) . ']"'
               . ' value="' . e((string) $currentValue) . '"'
               . ($earliest !== '' ? ' min="' . e($earliest) . '"' : '')
               . ($latest !== '' ? ' max="' . e($latest) . '"' : '')
               . ' class="ra-input"' . ($isRequired ? ' required' : '') . '>';
        if ($helpText) $html .= '<div class="muted small mt-1">' . e($helpText) . '</div>';
        foreach ($errors as $err) $html .= '<div class="small" style="color:var(--ra-error-fg);margin-top:.25rem;">' . e($err) . '</div>';
        $html .= '</div>';
        return $html;
    }
}

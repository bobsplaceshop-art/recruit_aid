<?php
declare(strict_types=1);

namespace CorporateAid\Platform\CustomFields\InputTypes;

use CorporateAid\Core\Row;
use CorporateAid\Platform\CustomFields\InputType;

final class TextType implements InputType
{
    public function key(): string { return 'text'; }
    public function label(): string { return 'Text (single line)'; }
    public function supportsOptions(): bool { return false; }

    public function configFields(): array
    {
        return [
            ['key' => 'max_length', 'label' => 'Maximum length', 'type' => 'number', 'help' => 'Default 255'],
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

        $max = Row::int($constraints, 'max_length', 255);
        if (mb_strlen($v) > $max) {
            $errors[] = "Must be {$max} characters or fewer.";
        }
        if (preg_match('/<[^>]*>/', $v)) {
            $errors[] = 'HTML tags are not allowed.';
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
        return $value ?? '';
    }

    /** @param array<string, mixed> $constraints
     *  @param string[] $options
     *  @param string[] $errors */
    public function render(
        string $fieldKey,
        string $fieldLabel,
        ?string $currentValue,
        array $constraints,
        array $options,
        ?string $helpText,
        bool $isRequired,
        array $errors = []
    ): string {
        $max = Row::int($constraints, 'max_length', 255);
        $required = $isRequired ? ' required' : '';
        $errorClass = $errors ? ' is-invalid' : '';
        $html  = '<div class="ra-field' . $errorClass . '">';
        $html .= '<label class="ra-label" for="cf_' . e($fieldKey) . '">' . e($fieldLabel);
        if ($isRequired) $html .= ' <span style="color:var(--ra-error-fg)">*</span>';
        $html .= '</label>';
        $html .= '<input type="text" id="cf_' . e($fieldKey) . '" name="cf[' . e($fieldKey) . ']"'
               . ' maxlength="' . $max . '"'
               . ' value="' . e((string) $currentValue) . '"'
               . ' class="ra-input"' . $required . '>';
        if ($helpText) $html .= '<div class="muted small mt-1">' . e($helpText) . '</div>';
        foreach ($errors as $err) $html .= '<div class="small" style="color:var(--ra-error-fg);margin-top:.25rem;">' . e($err) . '</div>';
        $html .= '</div>';
        return $html;
    }
}

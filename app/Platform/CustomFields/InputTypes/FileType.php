<?php
declare(strict_types=1);

namespace CorporateAid\Platform\CustomFields\InputTypes;

use CorporateAid\Core\Row;
use CorporateAid\Platform\CustomFields\InputType;

final class FileType implements InputType
{
    public function key(): string { return 'file'; }
    public function label(): string { return 'File upload'; }
    public function supportsOptions(): bool { return false; }

    public function configFields(): array
    {
        return [
            ['key' => 'extensions',  'label' => 'Allowed extensions (comma separated)', 'type' => 'text',   'help' => 'Default: pdf,doc,docx,png,jpg'],
            ['key' => 'max_size_mb', 'label' => 'Maximum size (MB)',                     'type' => 'number', 'help' => 'Default 5. Recommended: 5. Avoid values above 10 for resumes.'],
        ];
    }

    /** @param array<string, mixed> $constraints
     *  @return string[] */
    public function validate(mixed $value, array $constraints, bool $isRequired): array
    {
        return [];
    }

    public function cast(mixed $value): ?string
    {
        $v = is_string($value) ? trim($value) : '';
        return $v === '' ? null : $v;
    }

    public function humanize(?string $value): string
    {
        return $value ? 'Attached document' : '';
    }

    /** @param array<string, mixed> $constraints
     *  @param string[] $options
     *  @param string[] $errors */
    public function render(string $fieldKey, string $fieldLabel, ?string $currentValue, array $constraints, array $options, ?string $helpText, bool $isRequired, array $errors = []): string
    {
        $exts  = Row::str($constraints, 'extensions', 'pdf,doc,docx,png,jpg');
        $maxMb = Row::int($constraints, 'max_size_mb', 5);

        $html  = '<div class="ra-field">';
        $html .= '<label class="ra-label" for="cf_' . e($fieldKey) . '">' . e($fieldLabel);
        if ($isRequired) $html .= ' <span style="color:var(--ra-error-fg)">*</span>';
        $html .= '</label>';
        $html .= '<input type="file" id="cf_' . e($fieldKey) . '" name="cf_' . e($fieldKey) . '_file" class="ra-input"';
        if ($isRequired && $currentValue === null) $html .= ' required';
        $html .= '>';
        $html .= '<div class="muted small mt-1">Allowed: ' . e($exts) . ' &middot; Max: ' . $maxMb . ' MB';
        if ($helpText) $html .= ' &middot; ' . e($helpText);
        $html .= '</div>';

        if ($currentValue) {
            $html .= '<div class="muted small mt-1">';
            $html .= 'Currently attached: <a href="/files/' . e($currentValue) . '" target="_blank" rel="noopener">download</a>';
            $html .= '</div>';
        }

        foreach ($errors as $err) {
            $html .= '<div class="small" style="color:var(--ra-error-fg);margin-top:.25rem;">' . e($err) . '</div>';
        }

        $html .= '</div>';
        return $html;
    }
}

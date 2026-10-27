<?php

declare(strict_types=1);

namespace CorporateAid\Platform\CustomFields\InputTypes;

use CorporateAid\Platform\CustomFields\InputType;

/**
 * File upload field.
 *
 * The actual upload is handled by CustomFieldValueSaver, which calls
 * FileService->store() when it sees a file-type field with a matching
 * $_FILES entry. This input type is responsible for:
 *   - Rendering the file input element
 *   - Displaying the "currently attached" state
 *   - Humanizing a stored value (a UUID) for the detail view
 *
 * Validation of file content, size, MIME, and extension is handled by
 * FileValidator (see app/Platform/Validation/FileValidator.php).
 */
final class FileType implements InputType
{
    public function key(): string
    {
        return 'file';
    }
    public function label(): string
    {
        return 'File upload';
    }
    public function supportsOptions(): bool
    {
        return false;
    }

    public function configFields(): array
    {
        return [
            [
                'key'   => 'extensions',
                'label' => 'Allowed extensions (comma separated)',
                'type'  => 'text',
                'help'  => 'Default: pdf,doc,docx,png,jpg',
            ],
            [
                'key'   => 'max_size_mb',
                'label' => 'Maximum size (MB)',
                'type'  => 'number',
                'help'  => 'Default 5. Recommended: 5. Avoid values above 10 for resumes.',
            ],
        ];
    }

    /**
     * File fields skip string-based validation. The upload pipeline
     * (FileValidator) handles content and size validation. If the field
     * is required and no file is uploaded, that's caught by the
     * CustomFieldValueSaver before the file is stored.
     */
    public function validate(mixed $value, array $constraints, bool $isRequired): array
    {
        return [];
    }

    /**
     * Cast a stored value. File fields store a UUID string.
     */
    public function cast(mixed $value): ?string
    {
        $v = is_string($value) ? trim($value) : '';
        return $v === '' ? null : $v;
    }

    /**
     * Human-readable display for detail views.
     * Returns the label only; the renderer wraps it in a download link.
     */
    public function humanize(?string $value): string
    {
        return $value ? 'Attached document' : '';
    }

    public function render(string $fieldKey, string $fieldLabel, ?string $currentValue, array $constraints, array $options, ?string $helpText, bool $isRequired, array $errors = []): string
    {
        $exts  = $constraints['extensions'] ?? 'pdf,doc,docx,png,jpg';
        $maxMb = (int) ($constraints['max_size_mb'] ?? 5);

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

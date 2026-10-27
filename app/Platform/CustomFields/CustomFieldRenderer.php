<?php

declare(strict_types=1);

namespace CorporateAid\Platform\CustomFields;

use CorporateAid\Platform\Repositories\CustomFieldRepository;
use CorporateAid\Platform\Repositories\CustomFieldValueRepository;

/**
 * Renders custom fields on forms and on detail views.
 *
 * Form rendering (renderForm):
 *   Delegates to the input type's render() method. Input types own
 *   their own HTML. No escaping here — the input types handle it.
 *
 * Detail rendering (renderDetail):
 *   Delegates to the input type's humanize() method for the display
 *   text. Special case: file fields produce a download link that
 *   points to /files/{uuid}. All other values are HTML-escaped before
 *   display. The file link case is explicitly marked so the renderer
 *   knows not to escape the (already-safe) anchor tag.
 */
final class CustomFieldRenderer
{
    public function __construct(
        private CustomFieldRepository $fields,
        private CustomFieldValueRepository $values
    ) {}

    public function renderForm(
        string $entityType,
        ?int $entityId = null,
        array $submitted = [],
        array $errors = []
    ): string {
        $fields = $this->fields->activeFor($entityType);
        if (empty($fields)) {
            return '';
        }

        $existingValues = [];
        if ($entityId !== null) {
            $existingValues = $this->values->forEntity($entityType, $entityId);
        }

        $html = '<div class="ra-card mt-3" id="custom-fields-section">';
        $html .= '<h2 class="h5 mb-3">Additional fields</h2>';
        $html .= '<div class="row g-3">';

        foreach ($fields as $field) {
            $currentValue = null;

            if (array_key_exists($field->fieldKey, $submitted)) {
                $currentValue = $submitted[$field->fieldKey];
                if (is_array($currentValue)) {
                    $currentValue = json_encode($currentValue);
                } else {
                    $currentValue = (string) $currentValue;
                }
            } elseif (isset($existingValues[$field->id])) {
                $currentValue = $existingValues[$field->id];
            } elseif ($field->defaultValue !== null) {
                $currentValue = $field->defaultValue;
            }

            $fieldErrors = $errors[$field->fieldKey] ?? [];
            $type = InputTypeRegistry::get($field->inputType);

            $constraints = $field->constraints;
            if (!empty($field->options)) {
                $constraints['_options'] = $field->options;
            }

            $inputHtml = $type->render(
                $field->fieldKey,
                $field->label,
                $currentValue,
                $constraints,
                $field->options,
                $field->helpText,
                $field->isRequired,
                $fieldErrors
            );

            $html .= '<div class="col-md-6 col-lg-4">' . $inputHtml . '</div>';
        }

        $html .= '</div></div>';
        return $html;
    }

    /**
     * Render a detail view (read-only) of an entity's custom field values.
     *
     * File fields produce a download link. All other values are escaped.
     */
    public function renderDetail(string $entityType, int $entityId): string
    {
        $fields = $this->fields->activeFor($entityType);
        if (empty($fields)) {
            return '';
        }

        $values = $this->values->forEntity($entityType, $entityId);

        $rows = [];
        foreach ($fields as $field) {
            $rawValue = $values[$field->id] ?? null;
            if ($rawValue === null || $rawValue === '') {
                continue;
            }

            $type = InputTypeRegistry::get($field->inputType);

            // File fields: render as a download link
            if ($field->inputType === 'file') {
                $uuid = trim((string) $rawValue);
                if ($uuid === '') {
                    continue;
                }
                // The UUID is a validated string from our own DB.
                // e() escapes it for attribute safety; the whole anchor
                // tag is treated as safe HTML by the render loop.
                $rows[] = [
                    'label'  => $field->label,
                    'value'  => '<a href="/files/' . e($uuid) . '" target="_blank" rel="noopener">Download</a>',
                    'isHtml' => true,
                ];
                continue;
            }

            // All other types: use humanize() and escape.
            $humanized = $type->humanize((string) $rawValue);
            if ($humanized === '') {
                continue;
            }

            $rows[] = [
                'label'  => $field->label,
                'value'  => $humanized,
                'isHtml' => false,
            ];
        }

        if (empty($rows)) {
            return '';
        }

        $html = '<div class="ra-card mt-3">';
        $html .= '<h2 class="h5 mb-3">Additional fields</h2>';
        $html .= '<div class="row g-4">';

        foreach ($rows as $row) {
            $html .= '<div class="col-md-6">';
            $html .= '<div class="muted small">' . e($row['label']) . '</div>';
            if (!empty($row['isHtml'])) {
                // Already safe: the value is our own anchor tag with an
                // escaped UUID in the href.
                $html .= '<div>' . $row['value'] . '</div>';
            } else {
                $html .= '<div>' . e($row['value']) . '</div>';
            }
            $html .= '</div>';
        }

        $html .= '</div></div>';
        return $html;
    }
}

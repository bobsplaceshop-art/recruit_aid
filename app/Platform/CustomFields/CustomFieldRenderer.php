<?php

declare(strict_types=1);

namespace CorporateAid\Platform\CustomFields;

use CorporateAid\Platform\Repositories\CustomFieldRepository;
use CorporateAid\Platform\Repositories\CustomFieldValueRepository;

final class CustomFieldRenderer
{
    public function __construct(
        private CustomFieldRepository $fields,
        private CustomFieldValueRepository $values
    ) {}

    /**
     * @param array<string, mixed> $submitted
     * @param array<string, string[]> $errors
     */
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
                    $encoded = json_encode($currentValue);
                    $currentValue = $encoded !== false ? $encoded : null;
                } else {
                    $currentValue = is_scalar($currentValue) ? (string) $currentValue : null;
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

            if ($field->inputType === 'file') {
                $uuid = trim((string) $rawValue);
                if ($uuid === '') {
                    continue;
                }
                $rows[] = [
                    'label'  => $field->label,
                    'value'  => '<a href="/files/' . e($uuid) . '" target="_blank" rel="noopener">Download</a>',
                    'isHtml' => true,
                ];
                continue;
            }

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

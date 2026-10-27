<?php
declare(strict_types=1);

namespace CorporateAid\Platform\CustomFields;

use CorporateAid\Platform\Repositories\CustomFieldRepository;

final class CustomFieldValueValidator
{
    public function __construct(private CustomFieldRepository $fields) {}

    /**
     * @return array<string, string[]> errors keyed by field_key
     */
    public function validate(string $entityType, array $submitted): array
    {
        $definitions = $this->fields->activeFor($entityType);
        $errors = [];

        foreach ($definitions as $field) {
            $value = $submitted[$field->fieldKey] ?? null;

            $constraints = $field->constraints;
            if (!empty($field->options)) {
                $constraints['_options'] = $field->options;
            }

            $type = InputTypeRegistry::get($field->inputType);
            $rawErrors = $type->validate($value, $constraints, $field->isRequired);

            if (!empty($rawErrors)) {
                $errors[$field->fieldKey] = $this->localizeErrors($rawErrors, $field->label);
            }
        }

        return $errors;
    }

    /**
     * Prefix generic error messages with the field's label.
     * Handles the common patterns produced by the InputType classes:
     *   - "This field is required."  → "{label} is required."
     *   - "Must be a number."        → "{label} must be a number."
     *   - "A file is required."      → "{label} is required."
     *
     * @param string[] $errors
     * @return string[]
     */
    private function localizeErrors(array $errors, string $label): array
    {
        $out = [];
        foreach ($errors as $error) {
            $error = trim($error);
            if ($error === '') continue;

            // Pattern 1: "This field <rest>"
            if (str_starts_with($error, 'This field')) {
                $rest = trim(substr($error, 10)); // after "This field"
                $out[] = $label . ' ' . $rest;
                continue;
            }

            // Pattern 2: "A file is required."
            if (str_starts_with($error, 'A file is required')) {
                $out[] = $label . ' is required.';
                continue;
            }

            // Pattern 3: starts with "Must" — e.g. "Must be a number."
            if (str_starts_with($error, 'Must ')) {
                $out[] = $label . ' ' . lcfirst($error);
                continue;
            }

            // Pattern 4: starts with "You must" — e.g. "You must tick this box."
            if (str_starts_with($error, 'You must')) {
                $rest = substr($error, 8);
                $out[] = $label . ': you must' . $rest;
                continue;
            }

            // Pattern 5: default — prefix with label and colon
            $out[] = $label . ': ' . lcfirst($error);
        }
        return $out;
    }
}

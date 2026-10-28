<?php
declare(strict_types=1);

namespace CorporateAid\Platform\CustomFields;

use CorporateAid\Platform\Repositories\CustomFieldRepository;

final class CustomFieldValueValidator
{
    public function __construct(private CustomFieldRepository $fields) {}

    /**
     * @param array<string, mixed> $submitted
     * @return array<string, string[]>
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
     * @param string[] $errors
     * @return string[]
     */
    private function localizeErrors(array $errors, string $label): array
    {
        $out = [];
        foreach ($errors as $error) {
            $error = trim($error);
            if ($error === '') continue;

            if (str_starts_with($error, 'This field')) {
                $rest = trim(substr($error, 10));
                $out[] = $label . ' ' . $rest;
                continue;
            }

            if (str_starts_with($error, 'A file is required')) {
                $out[] = $label . ' is required.';
                continue;
            }

            if (str_starts_with($error, 'Must ')) {
                $out[] = $label . ' ' . lcfirst($error);
                continue;
            }

            if (str_starts_with($error, 'You must')) {
                $rest = substr($error, 8);
                $out[] = $label . ': you must' . $rest;
                continue;
            }

            $out[] = $label . ': ' . lcfirst($error);
        }
        return $out;
    }
}

<?php
declare(strict_types=1);

namespace CorporateAid\Platform\Validation;

use CorporateAid\Core\Row;
use CorporateAid\Platform\CustomFields\InputTypeRegistry;

/**
 * Validates the admin's submitted field definition when creating
 * or editing a custom field. This is NOT the same as validating a
 * candidate's value against the field — this validates the field
 * itself (label present, input type valid, options provided when required, etc).
 */
final class CustomFieldValidator
{
    /** @return string[] list of error messages; empty means valid */
    public function validate(array $input, ?int $editingId = null): array
    {
        $errors = [];

        // ---- label ----
        $label = trim(Row::str($input, 'label'));
        if ($label === '') {
            $errors[] = 'Label is required.';
        } elseif (mb_strlen($label) > 190) {
            $errors[] = 'Label must be 190 characters or fewer.';
        }

        // ---- input type ----
        $inputType = Row::str($input, 'input_type');
        if (!InputTypeRegistry::has($inputType)) {
            $errors[] = 'Please choose a valid input type.';
        }

        // ---- options (required when input type supports options) ----
        if (InputTypeRegistry::has($inputType)) {
            $type = InputTypeRegistry::get($inputType);
            if ($type->supportsOptions()) {
                $options = $input['options'] ?? [];
                if (!is_array($options)) $options = [];
                $clean = array_values(array_filter(array_map('trim', $options), fn($o) => $o !== ''));
                if (count($clean) < 1) {
                    $errors[] = 'Please provide at least one option.';
                } elseif (count($clean) !== count(array_unique($clean))) {
                    $errors[] = 'Options must be unique.';
                } elseif (count($clean) > 100) {
                    $errors[] = 'Maximum 100 options allowed.';
                }
            }
        }

        // ---- constraints (type-specific) ----
        $constraints = $input['constraints'] ?? [];
        if (!is_array($constraints)) $constraints = [];

        if ($inputType === 'text' || $inputType === 'long_text') {
            if (isset($constraints['max_length']) && $constraints['max_length'] !== '') {
                $ml = Row::int($constraints, 'max_length');
                if ($ml < 1 || $ml > 10000) $errors[] = 'Maximum length must be between 1 and 10000.';
            }
        }

        if ($inputType === 'number') {
            if (isset($constraints['min']) && $constraints['min'] !== '' && !is_numeric($constraints['min'])) {
                $errors[] = 'Minimum value must be a number.';
            }
            if (isset($constraints['max']) && $constraints['max'] !== '' && !is_numeric($constraints['max'])) {
                $errors[] = 'Maximum value must be a number.';
            }
            if (isset($constraints['decimals']) && $constraints['decimals'] !== '') {
                $d = Row::int($constraints, 'decimals');
                if ($d < 0 || $d > 10) $errors[] = 'Decimal places must be between 0 and 10.';
            }
        }

        if ($inputType === 'date') {
            foreach (['earliest', 'latest'] as $k) {
                if (!empty($constraints[$k])) {
                    $dt = \DateTimeImmutable::createFromFormat('Y-m-d', Row::str($constraints, $k));
                    if (!$dt || $dt->format('Y-m-d') !== Row::str($constraints, $k)) {
                        $errors[] = ucfirst($k) . ' must be a valid date (YYYY-MM-DD).';
                    }
                }
            }
        }

        // ---- help text ----
        $help = trim(Row::str($input, 'help_text'));
        if (mb_strlen($help) > 500) {
            $errors[] = 'Help text must be 500 characters or fewer.';
        }

        // ---- sort order ----
        if (isset($input['sort_order']) && $input['sort_order'] !== '' && !is_numeric($input['sort_order'])) {
            $errors[] = 'Sort order must be a number.';
        }

        return $errors;
    }

    /**
     * Build a stable, unique field_key from the label.
     * If $existingKeys contains it, appends _2, _3, etc.
     *
     * @param string[] $existingKeys
     */
    public function generateFieldKey(string $label, array $existingKeys = []): string
    {
        $slug = strtolower(trim($label));
        $slug = preg_replace('/[^a-z0-9]+/', '_', $slug) ?? '';
        $slug = trim($slug, '_');

        if ($slug === '') $slug = 'field';

        // Trim to 60 chars max (leave room for suffix)
        if (strlen($slug) > 60) $slug = substr($slug, 0, 60);

        if (!in_array($slug, $existingKeys, true)) return $slug;

        $i = 2;
        while (in_array($slug . '_' . $i, $existingKeys, true)) {
            $i++;
        }
        return $slug . '_' . $i;
    }
}

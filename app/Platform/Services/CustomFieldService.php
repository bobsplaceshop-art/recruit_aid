<?php
declare(strict_types=1);

namespace CorporateAid\Platform\Services;

use CorporateAid\Core\Auth;
use CorporateAid\Platform\Models\CustomField;
use CorporateAid\Platform\Repositories\CustomFieldRepository;
use CorporateAid\Platform\Repositories\CustomFieldValueRepository;
use CorporateAid\Platform\Support\ActivityLogger;
use CorporateAid\Platform\CustomFields\InputTypeRegistry;
use CorporateAid\Platform\Validation\CustomFieldValidator;

/**
 * Business logic for custom fields.
 * Controllers call this. Repositories handle SQL. Validator handles input.
 */
final class CustomFieldService
{
    public function __construct(
        private CustomFieldRepository $fields,
        private CustomFieldValueRepository $values,
        private CustomFieldValidator $validator,
        private ActivityLogger $activity,
        private ?Auth $auth = null
    ) {
    }

    /**
     * Create a new custom field for an entity.
     * Returns the new field's ID, or throws with validation errors.
     *
     * @throws \RuntimeException with errors attached
     */
    public function create(string $entityType, array $input): int
    {
        $errors = $this->validator->validate($input);
        if ($errors) {
            throw new \RuntimeException(implode(' ', $errors));
        }

        $existingKeys = array_map(
            fn (CustomField $f) => $f->fieldKey,
            $this->fields->allFor($entityType)
        );
        $fieldKey = $this->validator->generateFieldKey((string) $input['label'], $existingKeys);

        $userId = $this->auth?->user()?->id;

        $fieldId = $this->fields->create([
            'entity_type'   => $entityType,
            'field_key'     => $fieldKey,
            'label'         => trim((string) $input['label']),
            'input_type'    => (string) $input['input_type'],
            'is_required'   => !empty($input['is_required']),
            'options'       => $this->extractOptions($input),
            'constraints'   => $this->extractConstraints($input),
            'default_value' => trim((string) ($input['default_value'] ?? '')) ?: null,
            'help_text'     => trim((string) ($input['help_text'] ?? '')) ?: null,
            'sort_order'    => is_numeric($input['sort_order'] ?? null)
                                 ? (int) $input['sort_order']
                                 : $this->fields->nextSortOrder($entityType),
            'is_active'     => true,
            'created_by'    => $userId,
        ]);

        $this->activity->log(
            'custom_field.created',
            'settings',
            "Custom field created: {$input['label']} on {$entityType} (key: {$fieldKey})",
            'custom_field',
            $fieldId
        );

        return $fieldId;
    }

    public function update(int $fieldId, array $input): void
    {
        $field = $this->fields->findById($fieldId);
        if (!$field) {
            throw new \RuntimeException('Custom field not found.');
        }

        $errors = $this->validator->validate($input, $fieldId);
        if ($errors) {
            throw new \RuntimeException(implode(' ', $errors));
        }

        // The input type and field key cannot be changed after creation.
        // Changing input type could invalidate existing stored values.
        // Changing field key would break references in exports/reports.

        $this->fields->update($fieldId, [
            'label'         => trim((string) $input['label']),
            'is_required'   => !empty($input['is_required']),
            'options'       => $this->extractOptions($input),
            'constraints'   => $this->extractConstraints($input),
            'default_value' => trim((string) ($input['default_value'] ?? '')) ?: null,
            'help_text'     => trim((string) ($input['help_text'] ?? '')) ?: null,
            'sort_order'    => is_numeric($input['sort_order'] ?? null) ? (int) $input['sort_order'] : $field->sortOrder,
        ]);

        $this->activity->log(
            'custom_field.updated',
            'settings',
            "Custom field updated: {$input['label']} (key: {$field->fieldKey})",
            'custom_field',
            $fieldId
        );
    }

    public function archive(int $fieldId): void
    {
        $field = $this->fields->findById($fieldId);
        if (!$field) throw new \RuntimeException('Custom field not found.');

        $this->fields->setActive($fieldId, false);

        $this->activity->log(
            'custom_field.archived',
            'settings',
            "Custom field archived: {$field->label} (key: {$field->fieldKey})",
            'custom_field',
            $fieldId
        );
    }

    public function restore(int $fieldId): void
    {
        $field = $this->fields->findById($fieldId);
        if (!$field) throw new \RuntimeException('Custom field not found.');

        $this->fields->setActive($fieldId, true);

        $this->activity->log(
            'custom_field.restored',
            'settings',
            "Custom field restored: {$field->label} (key: {$field->fieldKey})",
            'custom_field',
            $fieldId
        );
    }

    /**
     * Delete a field. Only allowed if no values are stored against it.
     * Otherwise the caller should archive instead.
     *
     * @throws \RuntimeException if the field has stored values
     */
    public function delete(int $fieldId, string $confirmationPhrase): void
    {
        $field = $this->fields->findById($fieldId);
        if (!$field) throw new \RuntimeException('Custom field not found.');

        if (strtoupper(trim($confirmationPhrase)) !== 'DELETE') {
            throw new \RuntimeException('Please type DELETE to confirm.');
        }

        $valueCount = $this->fields->valueCount($fieldId);
        if ($valueCount > 0) {
            throw new \RuntimeException(
                "This field has {$valueCount} stored value(s) and cannot be deleted. Archive it instead."
            );
        }

        // Safe to hard delete — no data depends on it
        $this->fields->hardDelete($fieldId);

        $this->activity->log(
            'custom_field.deleted',
            'settings',
            "Custom field permanently deleted: {$field->label} (key: {$field->fieldKey})",
            'custom_field',
            $fieldId
        );
    }

    /**
     * Toggle the required flag on a field.
     */
    public function toggleRequired(int $fieldId, bool $required): void
    {
        $field = $this->fields->findById($fieldId);
        if (!$field) throw new \RuntimeException('Custom field not found.');

        $this->fields->setRequired($fieldId, $required);

        $this->activity->log(
            'custom_field.required_changed',
            'settings',
            "Custom field " . ($required ? 'marked required' : 'marked optional') . ": {$field->label}",
            'custom_field',
            $fieldId
        );
    }

    /**
     * Move a field one position up (lower sort_order).
     * Returns true on success, false if already at the top.
     */
    public function moveUp(int $fieldId): bool
    {
        return $this->move($fieldId, -1);
    }

    /**
     * Move a field one position down (higher sort_order).
     */
    public function moveDown(int $fieldId): bool
    {
        return $this->move($fieldId, 1);
    }

    /**
     * Internal: swap sort_order with the adjacent field.
     */
    private function move(int $fieldId, int $direction): bool
    {
        $field = $this->fields->findById($fieldId);
        if (!$field) throw new \RuntimeException('Custom field not found.');

        $all = $this->fields->orderedFor($field->entityType);

        $index = null;
        foreach ($all as $i => $f) {
            if ($f->id === $fieldId) { $index = $i; break; }
        }
        if ($index === null) return false;

        $swapIndex = (int) $index + $direction;
        if ($swapIndex < 0 || $swapIndex >= count($all)) {
            return false; // already at the boundary
        }

        $other = $all[$swapIndex];

        // Swap their sort_order values. If they're equal (both 0), reassign sequentially.
        $a = $field->sortOrder;
        $b = $other->sortOrder;

        if ($a === $b) {
            // Renumber the whole list with gaps of 10, preserving order
            foreach ($all as $i => $f) {
                $this->fields->setSortOrder($f->id, ($i + 1) * 10);
            }
            // Re-fetch the two fields after renumbering
            $all = $this->fields->orderedFor($field->entityType);
            $a = $all[$index]->sortOrder;
            $b = $all[$swapIndex]->sortOrder;
        }

        $this->fields->setSortOrder($field->id, $b);
        $this->fields->setSortOrder($other->id, $a);

        return true;
    }

    // ---------- helpers ----------

    private function extractOptions(array $input): array
    {
        $type = (string) ($input['input_type'] ?? '');
        if (!InputTypeRegistry::has($type)) return [];
        if (!InputTypeRegistry::get($type)->supportsOptions()) return [];

        $raw = $input['options'] ?? [];
        if (!is_array($raw)) return [];

        $clean = [];
        foreach ($raw as $o) {
            $str = is_scalar($o) ? trim((string) $o) : '';
            if ($str !== '') $clean[] = $str;
        }
        return array_values(array_unique($clean));
    }

    private function extractConstraints(array $input): array
    {
        $constraints = $input['constraints'] ?? [];
        if (!is_array($constraints)) return [];

        // Strip empty values so we only persist meaningful constraints
        $out = [];
        foreach ($constraints as $key => $val) {
            if ($val === '' || $val === null) continue;
            $out[(string) $key] = $val;
        }
        return $out;
    }
}

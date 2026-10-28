<?php

declare(strict_types=1);

namespace CorporateAid\Platform\CustomFields;

use CorporateAid\Core\Auth;
use CorporateAid\Platform\Repositories\CustomFieldRepository;
use CorporateAid\Platform\Repositories\CustomFieldValueRepository;
use CorporateAid\Core\Row;
use CorporateAid\Platform\Services\FileService;
use CorporateAid\Platform\Support\Logger;

/**
 * Persists custom field values for an entity.
 *
 * Two save paths:
 *   1. Scalar fields (text, number, date, select, etc.)
 *      → Cast the submitted value, save it.
 *
 *   2. File fields
 *      → Call FileService->store() with the uploaded file. On success,
 *        save the file's UUID as the value and link the file to the
 *        entity. On failure, log a warning and skip (the entity stays
 *        valid; only the file attachment is lost).
 *
 * The $files parameter is optional. Callers that don't submit files
 * (or entities without file custom fields) can omit it — behaviour is
 * unchanged for scalar fields.
 *
 * The $files array must be keyed by field key (e.g. $files['contract'])
 * and each value must be a $_FILES-shaped array:
 *   ['name' => ..., 'type' => ..., 'tmp_name' => ..., 'error' => ..., 'size' => ...]
 *
 * File upload security:
 *   - FileValidator enforces MIME sniffing, extension allowlist,
 *     null byte rejection, double-extension rejection, content scan.
 *   - FileService enforces is_uploaded_file() before moving the file.
 *   - Uploaded filenames are regenerated as UUIDs; original names are
 *     stored in the DB only.
 *   - Storage is outside the web root; files stream through PHP.
 */
final class CustomFieldValueSaver
{
    public function __construct(
        private CustomFieldRepository $fields,
        private CustomFieldValueRepository $values,
        private FileService $files,
        private ?Auth $auth = null
    ) {}

    /**
     * Save custom field values for the given entity.
     *
     * @param string $entityType  e.g. 'organisation'
     * @param int    $entityId    The entity's ID (already persisted)
     * @param array  $submitted   Custom field values from the form (cf[x] inputs)
     * @param array  $files       Uploaded files, keyed by field key (from $_FILES)
     */
    public function save(string $entityType, int $entityId, array $submitted, array $files = []): void
    {
        $definitions = $this->fields->activeFor($entityType);

        foreach ($definitions as $field) {
            $type = InputTypeRegistry::get($field->inputType);
            $isFileField = $field->inputType === 'file';
            $hasFile = $isFileField && isset($files[$field->fieldKey]);
            $hasValue = array_key_exists($field->fieldKey, $submitted);

            // --- File field with a new upload ---
            if ($hasFile) {
                $this->saveFileValue($field, $entityType, $entityId, $files[$field->fieldKey]);
                continue;
            }

            // --- File field with no new upload ---
            // Leave the existing value untouched. The user may be editing
            // other fields on the same form.
            if ($isFileField) {
                continue;
            }

            // --- Scalar field ---
            if (!$hasValue) {
                continue;
            }

            $rawValue = $submitted[$field->fieldKey];
            $casted = $type->cast($rawValue);

            $this->values->setValue(
                $field->id,
                $entityType,
                $entityId,
                $casted
            );
        }
    }

    /**
     * Store a file, save its UUID as the field value, and link it to the entity.
     *
     * Failures are logged but do not block the form submission. The
     * entity stays valid; only the file attachment is lost.
     */
    private function saveFileValue(
        object $field,
        string $entityType,
        int $entityId,
        array $fileData
    ): void {
        $userId = $this->auth?->user()?->id;

        // Read per-field constraints. Fall back to FileService defaults.
        $constraints = is_array($field->constraints ?? null) ? $field->constraints : [];
        $maxMb = Row::int($constraints, 'max_size_mb', FileService::DEFAULT_MAX_MB);
        if ($maxMb < 1) {
            $maxMb = FileService::DEFAULT_MAX_MB;
        }

        // Category is the field key. Falls into storage/uploads/candidate_docs/
        // for unknown keys — see FileService::directoryFor().
        $category = (string) $field->fieldKey;

        $result = $this->files->store($fileData, $userId, $category, $maxMb);

        // FileService::store returns File on success, string[] errors on failure.
        if (is_array($result)) {
            Logger::warning('Custom field file upload failed', [
                'entity_type' => $entityType,
                'entity_id'   => $entityId,
                'field_key'   => $field->fieldKey,
                'errors'      => $result,
            ]);
            return;
        }

        // Save the UUID as the field's value
        $this->values->setValue(
            $field->id,
            $entityType,
            $entityId,
            $result->uuid
        );

        // Link the file to the entity (idempotent via ON DUPLICATE KEY UPDATE)
        $this->files->link(
            $result->id,
            $entityType,
            $entityId,
            $category,
            $userId
        );
    }
}

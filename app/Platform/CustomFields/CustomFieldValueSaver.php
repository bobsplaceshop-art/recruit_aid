<?php
declare(strict_types=1);

namespace CorporateAid\Platform\CustomFields;

use CorporateAid\Core\Auth;
use CorporateAid\Core\Row;
use CorporateAid\Platform\Models\CustomField;
use CorporateAid\Platform\Repositories\CustomFieldRepository;
use CorporateAid\Platform\Repositories\CustomFieldValueRepository;
use CorporateAid\Platform\Services\FileService;
use CorporateAid\Platform\Support\Logger;

final class CustomFieldValueSaver
{
    public function __construct(
        private CustomFieldRepository $fields,
        private CustomFieldValueRepository $values,
        private FileService $files,
        private ?Auth $auth = null
    ) {}

    /**
     * @param array<string, mixed> $submitted The cf array (not wrapped).
     * @param array<string, array{name:string,type:string,tmp_name:string,error:int,size:int}> $files Legacy raw uploads.
     */
    public function save(string $entityType, int $entityId, array $submitted, array $files = []): void
    {
        $definitions = $this->fields->activeFor($entityType);

        foreach ($definitions as $field) {
            $type = InputTypeRegistry::get($field->inputType);
            $isFileField = $field->inputType === 'file';

            if ($isFileField) {
                // The value should be a UUID string (injected by middleware)
                if (isset($submitted[$field->fieldKey]) && is_string($submitted[$field->fieldKey])) {
                    $uuid = $submitted[$field->fieldKey];
                    if ($uuid !== '') {
                        $this->linkExistingFileByUuid($field, $entityType, $entityId, $uuid);
                    }
                    continue;
                }
                // Legacy: raw fileData passed via $files argument
                if (isset($files[$field->fieldKey])) {
                    $this->saveFileValue($field, $entityType, $entityId, $files[$field->fieldKey]);
                }
                continue;
            }

            // Non-file fields
            if (!array_key_exists($field->fieldKey, $submitted)) {
                continue;
            }

            $rawValue = $submitted[$field->fieldKey];
            $casted = $type->cast($rawValue);

            $this->values->setValue($field->id, $entityType, $entityId, $casted);
        }
    }

    private function linkExistingFileByUuid(
        CustomField $field,
        string $entityType,
        int $entityId,
        string $uuid
    ): void {
        // Look up the file ID from the UUID
        $file = $this->files->findByUuid($uuid);
        if ($file === null) {
            Logger::warning('SAVER-FAIL: UUID not found in core_files', ['uuid' => $uuid]);
            return;
        }

        $userId = $this->auth?->user()?->id;
        $this->files->link($file->id, $entityType, $entityId, $field->fieldKey, $userId);
        $this->values->setValue($field->id, $entityType, $entityId, $uuid);
    }

    /** @param array{name:string,type:string,tmp_name:string,error:int,size:int} $fileData */
    private function saveFileValue(
        CustomField $field,
        string $entityType,
        int $entityId,
        array $fileData
    ): void {
        $userId = $this->auth?->user()?->id;

        $constraints = $field->constraints;
        $maxMb = Row::int($constraints, 'max_size_mb', FileService::DEFAULT_MAX_MB);
        if ($maxMb < 1) {
            $maxMb = FileService::DEFAULT_MAX_MB;
        }

        $result = $this->files->store($fileData, $userId, $field->fieldKey, $maxMb);

        if (is_array($result)) {
            Logger::warning('Custom field file upload failed', [
                'entity_type' => $entityType,
                'entity_id'   => $entityId,
                'field_key'   => $field->fieldKey,
                'errors'      => $result,
            ]);
            return;
        }

        $this->values->setValue($field->id, $entityType, $entityId, $result->uuid);
        $this->files->link($result->id, $entityType, $entityId, $field->fieldKey, $userId);
    }
}

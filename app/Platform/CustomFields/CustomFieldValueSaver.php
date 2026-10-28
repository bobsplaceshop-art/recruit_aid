<?php

declare(strict_types=1);

namespace CorporateAid\Platform\CustomFields;

use CorporateAid\Core\Auth;
use CorporateAid\Platform\Models\CustomField;
use CorporateAid\Platform\Repositories\CustomFieldRepository;
use CorporateAid\Platform\Repositories\CustomFieldValueRepository;
use CorporateAid\Core\Row;
use CorporateAid\Platform\Services\FileService;
use CorporateAid\Platform\Support\Logger;

/**
 * Persists custom field values for an entity.
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
     * @param array<string, mixed> $submitted  Custom field values from the form (cf[x] inputs)
     * @param array<string, array{name:string,type:string,tmp_name:string,error:int,size:int}> $files  Uploaded files, keyed by field key
     */
    public function save(string $entityType, int $entityId, array $submitted, array $files = []): void
    {
        $definitions = $this->fields->activeFor($entityType);

        foreach ($definitions as $field) {
            $type = InputTypeRegistry::get($field->inputType);
            $isFileField = $field->inputType === 'file';
            $hasFile = $isFileField && isset($files[$field->fieldKey]);
            $hasValue = array_key_exists($field->fieldKey, $submitted);

            if ($hasFile) {
                $this->saveFileValue($field, $entityType, $entityId, $files[$field->fieldKey]);
                continue;
            }

            if ($isFileField) {
                continue;
            }

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
     * @param array{name:string,type:string,tmp_name:string,error:int,size:int} $fileData
     */
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

        $category = $field->fieldKey;

        $result = $this->files->store($fileData, $userId, $category, $maxMb);

        if (is_array($result)) {
            Logger::warning('Custom field file upload failed', [
                'entity_type' => $entityType,
                'entity_id'   => $entityId,
                'field_key'   => $field->fieldKey,
                'errors'      => $result,
            ]);
            return;
        }

        $this->values->setValue(
            $field->id,
            $entityType,
            $entityId,
            $result->uuid
        );

        $this->files->link(
            $result->id,
            $entityType,
            $entityId,
            $category,
            $userId
        );
    }
}

<?php
declare(strict_types=1);

namespace CorporateAid\Platform\Models;

final class CustomField
{
    public int $id;
    public string $entityType;
    public string $fieldKey;
    public string $label;
    public string $inputType;
    public bool $isRequired;
    /** @var string[] */
    public array $options;
    /** @var array<string,mixed> */
    public array $constraints;
    public ?string $defaultValue;
    public ?string $helpText;
    public int $sortOrder;
    public bool $isActive;
    public bool $isSystem;
    public ?int $createdBy;
    public ?string $createdAt;
    public ?string $updatedAt;
    public ?string $deletedAt;

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        $f = new self();
        $f->id = (int) ($row['id'] ?? 0);
        $f->entityType = (string) ($row['entity_type'] ?? '');
        $f->fieldKey = (string) ($row['field_key'] ?? '');
        $f->label = (string) ($row['label'] ?? '');
        $f->inputType = (string) ($row['input_type'] ?? '');
        $f->isRequired = (bool) ($row['is_required'] ?? false);

        $f->options = [];
        $rawOptions = $row['options'] ?? null;
        if (!empty($rawOptions) && is_scalar($rawOptions)) {
            $decoded = json_decode((string) $rawOptions, true);
            if (is_array($decoded)) {
                $f->options = array_values(array_map(
                    static fn ($v) => is_scalar($v) ? (string) $v : '',
                    $decoded
                ));
            }
        }

        $f->constraints = [];
        $rawConstraints = $row['constraints'] ?? null;
        if (!empty($rawConstraints) && is_scalar($rawConstraints)) {
            $decoded = json_decode((string) $rawConstraints, true);
            if (is_array($decoded)) {
                $f->constraints = [];
                foreach ($decoded as $k => $v) {
                    $f->constraints[(string) $k] = $v;
                }
            }
        }

        $f->defaultValue = $row['default_value'] !== null ? (string) ($row['default_value']) : null;
        $f->helpText = $row['help_text'] !== null ? (string) ($row['help_text']) : null;
        $f->sortOrder = (int) ($row['sort_order'] ?? 0);
        $f->isActive = (bool) ($row['is_active'] ?? false);
        $f->isSystem = (bool) ($row['is_system'] ?? false);
        $f->createdBy = $row['created_by'] !== null ? (int) ($row['created_by']) : null;
        $f->createdAt = $row['created_at'] !== null ? (string) ($row['created_at']) : null;
        $f->updatedAt = $row['updated_at'] !== null ? (string) ($row['updated_at']) : null;
        $f->deletedAt = $row['deleted_at'] !== null ? (string) ($row['deleted_at']) : null;
        return $f;
    }

    public function isDeleted(): bool { return $this->deletedAt !== null; }
}

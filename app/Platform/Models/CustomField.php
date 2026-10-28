<?php
declare(strict_types=1);

namespace CorporateAid\Platform\Models;

use CorporateAid\Core\Row;

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

    /** @var array<string, mixed> */
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
        $f->id = Row::int($row, 'id');
        $f->entityType = Row::str($row, 'entity_type');
        $f->fieldKey = Row::str($row, 'field_key');
        $f->label = Row::str($row, 'label');
        $f->inputType = Row::str($row, 'input_type');
        $f->isRequired = Row::bool($row, 'is_required');

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

        $f->defaultValue = Row::nullableStr($row, 'default_value');
        $f->helpText = Row::nullableStr($row, 'help_text');
        $f->sortOrder = Row::int($row, 'sort_order');
        $f->isActive = Row::bool($row, 'is_active');
        $f->isSystem = Row::bool($row, 'is_system');
        $f->createdBy = Row::nullableInt($row, 'created_by');
        $f->createdAt = Row::nullableStr($row, 'created_at');
        $f->updatedAt = Row::nullableStr($row, 'updated_at');
        $f->deletedAt = Row::nullableStr($row, 'deleted_at');
        return $f;
    }

    public function isDeleted(): bool { return $this->deletedAt !== null; }
}

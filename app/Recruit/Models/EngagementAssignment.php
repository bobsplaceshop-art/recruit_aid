<?php
declare(strict_types=1);

namespace CorporateAid\Recruit\Models;

use CorporateAid\Core\Row;

final class EngagementAssignment
{
    public int $id;
    public int $engagementId;
    public string $assigneeType;
    public string $assigneeValue;
    public bool $canView;
    public bool $canEdit;
    public ?int $assignedBy;
    public ?string $createdAt;
    public ?string $updatedAt;

    public string $assigneeLabel = '';

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        $a = new self();
        $a->id = Row::int($row, 'id');
        $a->engagementId = Row::int($row, 'engagement_id');
        $a->assigneeType = Row::str($row, 'assignee_type');
        $a->assigneeValue = Row::str($row, 'assignee_value');
        $a->canView = Row::bool($row, 'can_view');
        $a->canEdit = Row::bool($row, 'can_edit');
        $a->assignedBy = Row::nullableInt($row, 'assigned_by');
        $a->createdAt = Row::nullableStr($row, 'created_at');
        $a->updatedAt = Row::nullableStr($row, 'updated_at');
        return $a;
    }

    public function describe(): string
    {
        $type = match ($this->assigneeType) {
            'role'       => 'Role',
            'permission' => 'Permission',
            'user'       => 'User',
            default      => ucfirst($this->assigneeType),
        };
        return $this->assigneeLabel !== ''
            ? "{$type}: {$this->assigneeLabel}"
            : "{$type}: {$this->assigneeValue}";
    }
}

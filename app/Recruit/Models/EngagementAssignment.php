<?php
declare(strict_types=1);

namespace CorporateAid\Recruit\Models;

final class EngagementAssignment
{
    public int $id;
    public int $engagementId;
    public string $assigneeType;   // 'role' | 'permission' | 'user'
    public string $assigneeValue;  // slug or user id
    public bool $canView;
    public bool $canEdit;
    public ?int $assignedBy;
    public ?string $createdAt;
    public ?string $updatedAt;

    public static function fromRow(array $row): self
    {
        $a = new self();
        $a->id = (int) $row['id'];
        $a->engagementId = (int) $row['engagement_id'];
        $a->assigneeType = (string) $row['assignee_type'];
        $a->assigneeValue = (string) $row['assignee_value'];
        $a->canView = (bool) $row['can_view'];
        $a->canEdit = (bool) $row['can_edit'];
        $a->assignedBy = $row['assigned_by'] !== null ? (int) $row['assigned_by'] : null;
        $a->createdAt = $row['created_at'] !== null ? (string) $row['created_at'] : null;
        $a->updatedAt = $row['updated_at'] !== null ? (string) $row['updated_at'] : null;
        return $a;
    }

    /**
     * Human-readable label for the assignee. Populated by the repository
     * so we don't have to guess role names in the view.
     */
    public string $assigneeLabel = '';

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

<?php
declare(strict_types=1);

namespace CorporateAid\Recruit\Models;

final class Engagement
{
    public int $id;
    public string $code;
    public int $organisationId;
    public ?int $packageId;
    public string $name;
    public string $serviceType;
    public string $status;
    public ?string $startDate;
    public ?string $endDate;
    public int $agreedActiveRoleCapacity;
    public string $reportingFrequency;
    /** @var string[] */
    public array $countries;
    public ?string $scopeDescription;
    /** @var string[] */
    public array $stagesIncluded;
    public ?string $notes;
    public ?int $createdBy;
    public ?string $createdAt;
    public ?string $updatedAt;
    public ?string $deletedAt;

    public static function fromRow(array $row): self
    {
        $e = new self();
        $e->id = (int) $row['id'];
        $e->code = (string) $row['code'];
        $e->organisationId = (int) $row['organisation_id'];
        $e->packageId = $row['package_id'] !== null ? (int) $row['package_id'] : null;
        $e->name = (string) $row['name'];
        $e->serviceType = (string) $row['service_type'];
        $e->status = (string) $row['status'];
        $e->startDate = $row['start_date'] !== null ? (string) $row['start_date'] : null;
        $e->endDate = $row['end_date'] !== null ? (string) $row['end_date'] : null;
        $e->agreedActiveRoleCapacity = (int) $row['agreed_active_role_capacity'];
        $e->reportingFrequency = (string) $row['reporting_frequency'];

        $e->countries = self::decodeJsonArray($row['countries'] ?? null);
        $e->stagesIncluded = self::decodeJsonArray($row['stages_included'] ?? null);

        $e->scopeDescription = $row['scope_description'] !== null ? (string) $row['scope_description'] : null;
        $e->notes = $row['notes'] !== null ? (string) $row['notes'] : null;
        $e->createdBy = $row['created_by'] !== null ? (int) $row['created_by'] : null;
        $e->createdAt = $row['created_at'] !== null ? (string) $row['created_at'] : null;
        $e->updatedAt = $row['updated_at'] !== null ? (string) $row['updated_at'] : null;
        $e->deletedAt = $row['deleted_at'] !== null ? (string) $row['deleted_at'] : null;

        return $e;
    }

    private static function decodeJsonArray(mixed $raw): array
    {
        if (empty($raw)) return [];
        $decoded = json_decode(is_scalar($raw) ? (string) $raw : '', true);
        if (!is_array($decoded)) return [];
        return array_values(array_map('strval', $decoded));
    }

    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }

    public function serviceTypeLabel(): string
    {
        return match ($this->serviceType) {
            '3_month'         => '3-Month Hiring Support',
            '6_month'         => '6-Month Embedded Recruitment',
            '12_month'        => '12-Month Recruitment Partnership',
            'focused_search'  => 'Focused Search',
            'assessment'      => 'Applicant Assessment',
            default           => ucfirst($this->serviceType),
        };
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'draft'             => 'Draft',
            'proposal'          => 'Proposal',
            'pending_agreement' => 'Pending Agreement',
            'active'            => 'Active',
            'paused'            => 'Paused',
            'completed'         => 'Completed',
            'cancelled'         => 'Cancelled',
            default             => ucfirst($this->status),
        };
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            'active'            => 'ra-badge ra-badge-success',
            'draft'             => 'ra-badge',
            'proposal'          => 'ra-badge',
            'pending_agreement' => 'ra-badge ra-badge-warn',
            'paused'            => 'ra-badge ra-badge-warn',
            'completed'         => 'ra-badge ra-badge-success',
            'cancelled'         => 'ra-badge ra-badge-danger',
            default             => 'ra-badge',
        };
    }

    public function isActive(): bool
    {
        return $this->status === 'active' && $this->deletedAt === null;
    }
}

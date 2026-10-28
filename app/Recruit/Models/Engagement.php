<?php
declare(strict_types=1);

namespace CorporateAid\Recruit\Models;

use CorporateAid\Core\Row;

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

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        $e = new self();
        $e->id = Row::int($row, 'id');
        $e->code = Row::str($row, 'code');
        $e->organisationId = Row::int($row, 'organisation_id');
        $e->packageId = Row::nullableInt($row, 'package_id');
        $e->name = Row::str($row, 'name');
        $e->serviceType = Row::str($row, 'service_type');
        $e->status = Row::str($row, 'status');
        $e->startDate = Row::nullableStr($row, 'start_date');
        $e->endDate = Row::nullableStr($row, 'end_date');
        $e->agreedActiveRoleCapacity = Row::int($row, 'agreed_active_role_capacity');
        $e->reportingFrequency = Row::str($row, 'reporting_frequency');

        $e->countries = self::decodeJsonArray($row['countries'] ?? null);
        $e->stagesIncluded = self::decodeJsonArray($row['stages_included'] ?? null);

        $e->scopeDescription = Row::nullableStr($row, 'scope_description');
        $e->notes = Row::nullableStr($row, 'notes');
        $e->createdBy = Row::nullableInt($row, 'created_by');
        $e->createdAt = Row::nullableStr($row, 'created_at');
        $e->updatedAt = Row::nullableStr($row, 'updated_at');
        $e->deletedAt = Row::nullableStr($row, 'deleted_at');

        return $e;
    }

    /** @return string[] */
    private static function decodeJsonArray(mixed $raw): array
    {
        if (empty($raw)) return [];
        $decoded = json_decode(is_scalar($raw) ? (string) $raw : '', true);
        if (!is_array($decoded)) return [];
        $out = [];
        foreach ($decoded as $v) {
            if (is_scalar($v)) $out[] = (string) $v;
        }
        return $out;
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

<?php
declare(strict_types=1);

namespace CorporateAid\Recruit\Models;

use CorporateAid\Core\Row;

final class Vacancy
{
    public int $id;
    public string $code;
    public int $organisationId;
    public ?int $engagementId;
    public string $title;
    public ?string $department;
    public ?string $countryCode;
    public ?string $city;
    public string $workMode;
    public string $employmentType;
    public string $seniority;
    public int $positionsCount;
    public ?string $openingDate;
    public ?string $targetHireDate;
    public ?int $recruiterId;
    public ?int $hiringManagerContactId;
    public ?float $salaryMin;
    public ?float $salaryMax;
    public ?string $currency;
    public ?string $description;
    public ?string $responsibilities;
    public ?string $requirements;
    public ?string $preferredQualifications;

    /** @var string[] */
    public array $skills;

    /** @var string[] */
    public array $languages;

    public ?string $assessmentCriteria;
    public ?string $internalNotes;
    public ?string $clientNotes;
    public string $status;
    public bool $isPublic;
    public ?string $publicSlug;
    public ?int $createdBy;
    public ?string $createdAt;
    public ?string $updatedAt;
    public ?string $deletedAt;

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        $v = new self();
        $v->id = Row::int($row, 'id');
        $v->code = Row::str($row, 'code');
        $v->organisationId = Row::int($row, 'organisation_id');
        $v->engagementId = Row::nullableInt($row, 'engagement_id');
        $v->title = Row::str($row, 'title');
        $v->department = Row::nullableStr($row, 'department');
        $v->countryCode = Row::nullableStr($row, 'country_code');
        $v->city = Row::nullableStr($row, 'city');
        $v->workMode = Row::str($row, 'work_mode');
        $v->employmentType = Row::str($row, 'employment_type');
        $v->seniority = Row::str($row, 'seniority');
        $v->positionsCount = Row::int($row, 'positions_count');
        $v->openingDate = Row::nullableStr($row, 'opening_date');
        $v->targetHireDate = Row::nullableStr($row, 'target_hire_date');
        $v->recruiterId = Row::nullableInt($row, 'recruiter_id');
        $v->hiringManagerContactId = Row::nullableInt($row, 'hiring_manager_contact_id');
        $v->salaryMin = Row::nullableFloat($row, 'salary_min');
        $v->salaryMax = Row::nullableFloat($row, 'salary_max');
        $v->currency = Row::nullableStr($row, 'currency');
        $v->description = Row::nullableStr($row, 'description');
        $v->responsibilities = Row::nullableStr($row, 'responsibilities');
        $v->requirements = Row::nullableStr($row, 'requirements');
        $v->preferredQualifications = Row::nullableStr($row, 'preferred_qualifications');

        $v->skills = self::decodeJsonArray($row['skills'] ?? null);
        $v->languages = self::decodeJsonArray($row['languages'] ?? null);

        $v->assessmentCriteria = Row::nullableStr($row, 'assessment_criteria');
        $v->internalNotes = Row::nullableStr($row, 'internal_notes');
        $v->clientNotes = Row::nullableStr($row, 'client_notes');
        $v->status = Row::str($row, 'status');
        $v->isPublic = Row::bool($row, 'is_public');
        $v->publicSlug = Row::nullableStr($row, 'public_slug');
        $v->createdBy = Row::nullableInt($row, 'created_by');
        $v->createdAt = Row::nullableStr($row, 'created_at');
        $v->updatedAt = Row::nullableStr($row, 'updated_at');
        $v->deletedAt = Row::nullableStr($row, 'deleted_at');
        return $v;
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

    public function isOpen(): bool
    {
        return !in_array($this->status, ['closed', 'cancelled', 'filled', 'draft', 'on_hold'], true)
            && $this->deletedAt === null;
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'draft'             => 'Draft',
            'pending_approval'  => 'Pending Approval',
            'open'              => 'Open',
            'sourcing'          => 'Sourcing',
            'screening'         => 'Screening',
            'shortlisting'      => 'Shortlisting',
            'client_review'     => 'Client Review',
            'interviewing'      => 'Interviewing',
            'reference_check'   => 'Reference Check',
            'offer'             => 'Offer',
            'filled'            => 'Filled',
            'on_hold'           => 'On Hold',
            'cancelled'         => 'Cancelled',
            'closed'            => 'Closed',
            default             => ucfirst($this->status),
        };
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            'open', 'sourcing', 'screening', 'shortlisting',
            'client_review', 'interviewing', 'reference_check' => 'ra-badge ra-badge-success',
            'offer'                                             => 'ra-badge ra-badge-warn',
            'filled'                                            => 'ra-badge ra-badge-success',
            'on_hold', 'pending_approval'                       => 'ra-badge ra-badge-warn',
            'cancelled', 'closed'                               => 'ra-badge ra-badge-danger',
            default                                             => 'ra-badge',
        };
    }

    public function seniorityLabel(): string
    {
        return match ($this->seniority) {
            'junior' => 'Junior', 'mid' => 'Mid', 'senior' => 'Senior',
            'lead' => 'Lead', 'manager' => 'Manager', 'director' => 'Director',
            'executive' => 'Executive', default => ucfirst($this->seniority),
        };
    }

    public function workModeLabel(): string
    {
        return match ($this->workMode) {
            'onsite' => 'On-site', 'hybrid' => 'Hybrid', 'remote' => 'Remote',
            default => ucfirst($this->workMode),
        };
    }

    public function employmentTypeLabel(): string
    {
        return match ($this->employmentType) {
            'permanent' => 'Permanent', 'contract' => 'Contract',
            'fixed_term' => 'Fixed Term', 'internship' => 'Internship',
            'consultancy' => 'Consultancy', default => ucfirst($this->employmentType),
        };
    }

    public function salaryRange(): ?string
    {
        if ($this->salaryMin === null && $this->salaryMax === null) return null;
        $cur = $this->currency ?? '';
        $fmt = fn ($n) => number_format($n, 0);
        if ($this->salaryMin !== null && $this->salaryMax !== null) {
            return trim($cur . ' ' . $fmt($this->salaryMin) . ' – ' . $fmt($this->salaryMax));
        }
        if ($this->salaryMin !== null) return trim($cur . ' ' . $fmt($this->salaryMin) . '+');
        return trim($cur . ' up to ' . $fmt($this->salaryMax));
    }
}

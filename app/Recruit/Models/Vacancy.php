<?php
declare(strict_types=1);

namespace CorporateAid\Recruit\Models;

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
    public array $skills;
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

    public static function fromRow(array $row): self
    {
        $v = new self();
        $v->id = (int) $row['id'];
        $v->code = (string) $row['code'];
        $v->organisationId = (int) $row['organisation_id'];
        $v->engagementId = $row['engagement_id'] !== null ? (int) $row['engagement_id'] : null;
        $v->title = (string) $row['title'];
        $v->department = $row['department'] !== null ? (string) $row['department'] : null;
        $v->countryCode = $row['country_code'] !== null ? (string) $row['country_code'] : null;
        $v->city = $row['city'] !== null ? (string) $row['city'] : null;
        $v->workMode = (string) $row['work_mode'];
        $v->employmentType = (string) $row['employment_type'];
        $v->seniority = (string) $row['seniority'];
        $v->positionsCount = (int) $row['positions_count'];
        $v->openingDate = $row['opening_date'] !== null ? (string) $row['opening_date'] : null;
        $v->targetHireDate = $row['target_hire_date'] !== null ? (string) $row['target_hire_date'] : null;
        $v->recruiterId = $row['recruiter_id'] !== null ? (int) $row['recruiter_id'] : null;
        $v->hiringManagerContactId = $row['hiring_manager_contact_id'] !== null ? (int) $row['hiring_manager_contact_id'] : null;
        $v->salaryMin = $row['salary_min'] !== null ? (float) $row['salary_min'] : null;
        $v->salaryMax = $row['salary_max'] !== null ? (float) $row['salary_max'] : null;
        $v->currency = $row['currency'] !== null ? (string) $row['currency'] : null;
        $v->description = $row['description'] !== null ? (string) $row['description'] : null;
        $v->responsibilities = $row['responsibilities'] !== null ? (string) $row['responsibilities'] : null;
        $v->requirements = $row['requirements'] !== null ? (string) $row['requirements'] : null;
        $v->preferredQualifications = $row['preferred_qualifications'] !== null ? (string) $row['preferred_qualifications'] : null;
        $v->skills = self::decodeJsonArray($row['skills'] ?? null);
        $v->languages = self::decodeJsonArray($row['languages'] ?? null);
        $v->assessmentCriteria = $row['assessment_criteria'] !== null ? (string) $row['assessment_criteria'] : null;
        $v->internalNotes = $row['internal_notes'] !== null ? (string) $row['internal_notes'] : null;
        $v->clientNotes = $row['client_notes'] !== null ? (string) $row['client_notes'] : null;
        $v->status = (string) $row['status'];
        $v->isPublic = (bool) $row['is_public'];
        $v->publicSlug = $row['public_slug'] !== null ? (string) $row['public_slug'] : null;
        $v->createdBy = $row['created_by'] !== null ? (int) $row['created_by'] : null;
        $v->createdAt = $row['created_at'] !== null ? (string) $row['created_at'] : null;
        $v->updatedAt = $row['updated_at'] !== null ? (string) $row['updated_at'] : null;
        $v->deletedAt = $row['deleted_at'] !== null ? (string) $row['deleted_at'] : null;
        return $v;
    }

    private static function decodeJsonArray(mixed $raw): array
    {
        if (empty($raw)) return [];
        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded)) return [];
        return array_values(array_map('strval', $decoded));
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

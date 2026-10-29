<?php
declare(strict_types=1);

namespace CorporateAid\Recruit\Models;

use CorporateAid\Core\Row;

final class Candidate
{
    public int $id;
    public string $code;
    public string $firstName;
    public string $lastName;
    public string $email;
    public ?string $phone;
    public ?string $countryCode;
    public ?string $city;
    public ?string $linkedinUrl;
    public ?string $portfolioUrl;
    public ?string $currentJobTitle;
    public ?string $currentCompany;
    public ?int $yearsExperience;
    public ?string $seniority;

    /** @var string[] */
    public array $skills;

    /** @var string[] */
    public array $languages;

    public ?string $summary;
    public ?string $internalNotes;
    public string $status;
    public ?string $source;
    public ?int $recruiterId;
    public ?int $createdBy;
    public ?string $consentGivenAt;
    public ?string $retentionUntil;
    public ?string $gdprErasedAt;
    public ?string $createdAt;
    public ?string $updatedAt;
    public ?string $deletedAt;

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        $c = new self();
        $c->id = Row::int($row, 'id');
        $c->code = Row::str($row, 'code');
        $c->firstName = Row::str($row, 'first_name');
        $c->lastName = Row::str($row, 'last_name');
        $c->email = Row::str($row, 'email');
        $c->phone = Row::nullableStr($row, 'phone');
        $c->countryCode = Row::nullableStr($row, 'country_code');
        $c->city = Row::nullableStr($row, 'city');
        $c->linkedinUrl = Row::nullableStr($row, 'linkedin_url');
        $c->portfolioUrl = Row::nullableStr($row, 'portfolio_url');
        $c->currentJobTitle = Row::nullableStr($row, 'current_job_title');
        $c->currentCompany = Row::nullableStr($row, 'current_company');
        $c->yearsExperience = Row::nullableInt($row, 'years_experience');
        $c->seniority = Row::nullableStr($row, 'seniority');
        $c->skills = self::decodeJsonArray($row['skills'] ?? null);
        $c->languages = self::decodeJsonArray($row['languages'] ?? null);
        $c->summary = Row::nullableStr($row, 'summary');
        $c->internalNotes = Row::nullableStr($row, 'internal_notes');
        $c->status = Row::str($row, 'status');
        $c->source = Row::nullableStr($row, 'source');
        $c->recruiterId = Row::nullableInt($row, 'recruiter_id');
        $c->createdBy = Row::nullableInt($row, 'created_by');
        $c->consentGivenAt = Row::nullableStr($row, 'consent_given_at');
        $c->retentionUntil = Row::nullableStr($row, 'retention_until');
        $c->gdprErasedAt = Row::nullableStr($row, 'gdpr_erased_at');
        $c->createdAt = Row::nullableStr($row, 'created_at');
        $c->updatedAt = Row::nullableStr($row, 'updated_at');
        $c->deletedAt = Row::nullableStr($row, 'deleted_at');
        return $c;
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

    public function fullName(): string
    {
        return trim($this->firstName . ' ' . $this->lastName);
    }

    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }

    public function gdprIsErased(): bool
    {
        return $this->gdprErasedAt !== null;
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'active'      => 'Active',
            'passive'     => 'Passive',
            'hired'       => 'Hired',
            'blacklisted' => 'Blacklisted',
            'archived'    => 'Archived',
            default       => ucfirst($this->status),
        };
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            'active'      => 'ra-badge ra-badge-success',
            'passive'     => 'ra-badge',
            'hired'       => 'ra-badge ra-badge-success',
            'blacklisted' => 'ra-badge ra-badge-danger',
            'archived'    => 'ra-badge ra-badge-warn',
            default       => 'ra-badge',
        };
    }

    public function seniorityLabel(): string
    {
        return match ($this->seniority) {
            'junior' => 'Junior', 'mid' => 'Mid', 'senior' => 'Senior',
            'lead' => 'Lead', 'manager' => 'Manager', 'director' => 'Director',
            'executive' => 'Executive', default => ucfirst($this->seniority ?? ''),
        };
    }
}

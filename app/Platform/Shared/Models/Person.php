<?php
declare(strict_types=1);

namespace CorporateAid\Platform\Shared\Models;

use CorporateAid\Core\Row;

/**
 * Canonical person identity. This is the shared human record that
 * both Recruit Aid candidates and HR Aid employees link to.
 *
 * A person is not a candidate and not an employee. Candidate-specific
 * data lives on recruit_candidates; employee-specific data lives on
 * hr_employees. Person holds identity-level fields (name, gender,
 * date of birth, nationality) that stay the same regardless of
 * which module is looking at the person.
 */
final class Person
{
    public int $id;
    public string $code;
    public string $firstName;
    public string $lastName;
    public ?string $preferredName;
    public ?string $gender;
    public ?string $dateOfBirth;
    public ?string $nationality;
    public ?int $createdBy;
    public ?string $createdAt;
    public ?string $updatedAt;
    public ?string $deletedAt;

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        $p = new self();
        $p->id = Row::int($row, 'id');
        $p->code = Row::str($row, 'code');
        $p->firstName = Row::str($row, 'first_name');
        $p->lastName = Row::str($row, 'last_name');
        $p->preferredName = Row::nullableStr($row, 'preferred_name');
        $p->gender = Row::nullableStr($row, 'gender');
        $p->dateOfBirth = Row::nullableStr($row, 'date_of_birth');
        $p->nationality = Row::nullableStr($row, 'nationality');
        $p->createdBy = Row::nullableInt($row, 'created_by');
        $p->createdAt = Row::nullableStr($row, 'created_at');
        $p->updatedAt = Row::nullableStr($row, 'updated_at');
        $p->deletedAt = Row::nullableStr($row, 'deleted_at');
        return $p;
    }

    public function fullName(): string
    {
        return trim($this->firstName . ' ' . $this->lastName);
    }

    /**
     * The name to show in most UI contexts. Prefers the preferred
     * name if set, otherwise falls back to first + last.
     */
    public function displayName(): string
    {
        if ($this->preferredName !== null && $this->preferredName !== '') {
            return trim($this->preferredName . ' ' . $this->lastName);
        }
        return $this->fullName();
    }

    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }

    public function genderLabel(): string
    {
        return match ($this->gender) {
            'male'                => 'Male',
            'female'              => 'Female',
            'other'               => 'Other',
            'prefer_not_to_say'   => 'Prefer not to say',
            default               => '—',
        };
    }
}

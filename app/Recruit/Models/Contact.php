<?php
declare(strict_types=1);

namespace CorporateAid\Recruit\Models;

/**
 * Represents a row in the `recruit_client_contacts` table.
 */
final class Contact
{
    public int $id;
    public int $organisationId;
    public string $code;
    public string $firstName;
    public string $lastName;
    public ?string $position;
    public ?string $department;
    public ?string $email;
    public ?string $phone;
    public ?string $altPhone;
    public ?string $countryCode;
    public string $preferredCommunication;
    public bool $isPrimary;
    public bool $isHiringManager;
    public bool $isFinance;
    public bool $isHr;
    public bool $isDecisionMaker;
    public ?string $notes;
    public ?int $createdBy;
    public ?string $createdAt;
    public ?string $updatedAt;
    public ?string $deletedAt;

    public static function fromRow(array $row): self
    {
        $c = new self();
        $c->id = (int) $row['id'];
        $c->organisationId = (int) $row['organisation_id'];
        $c->code = (string) $row['code'];
        $c->firstName = (string) $row['first_name'];
        $c->lastName = (string) $row['last_name'];
        $c->position = $row['position'] !== null ? (string) $row['position'] : null;
        $c->department = $row['department'] !== null ? (string) $row['department'] : null;
        $c->email = $row['email'] !== null ? (string) $row['email'] : null;
        $c->phone = $row['phone'] !== null ? (string) $row['phone'] : null;
        $c->altPhone = $row['alt_phone'] !== null ? (string) $row['alt_phone'] : null;
        $c->countryCode = $row['country_code'] !== null ? (string) $row['country_code'] : null;
        $c->preferredCommunication = (string) ($row['preferred_communication'] ?? 'email');
        $c->isPrimary = (bool) $row['is_primary'];
        $c->isHiringManager = (bool) $row['is_hiring_manager'];
        $c->isFinance = (bool) $row['is_finance'];
        $c->isHr = (bool) $row['is_hr'];
        $c->isDecisionMaker = (bool) $row['is_decision_maker'];
        $c->notes = $row['notes'] !== null ? (string) $row['notes'] : null;
        $c->createdBy = $row['created_by'] !== null ? (int) $row['created_by'] : null;
        $c->createdAt = $row['created_at'] !== null ? (string) $row['created_at'] : null;
        $c->updatedAt = $row['updated_at'] !== null ? (string) $row['updated_at'] : null;
        $c->deletedAt = $row['deleted_at'] !== null ? (string) $row['deleted_at'] : null;
        return $c;
    }

    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }

    public function fullName(): string
    {
        return trim($this->firstName . ' ' . $this->lastName);
    }

    /** @return string[] Human-readable role labels */
    public function roleLabels(): array
    {
        $roles = [];
        if ($this->isPrimary) $roles[] = 'Primary';
        if ($this->isHiringManager) $roles[] = 'Hiring Manager';
        if ($this->isFinance) $roles[] = 'Finance';
        if ($this->isHr) $roles[] = 'HR';
        if ($this->isDecisionMaker) $roles[] = 'Decision Maker';
        return $roles;
    }
}

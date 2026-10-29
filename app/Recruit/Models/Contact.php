<?php
declare(strict_types=1);

namespace CorporateAid\Recruit\Models;

use CorporateAid\Core\Row;

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
    /** @var string[] Role slugs from the pivot table (may include custom roles) */
    public array $roles = [];
    public ?string $notes;
    public ?int $createdBy;
    public ?string $createdAt;
    public ?string $updatedAt;
    public ?string $deletedAt;

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        $c = new self();
        $c->id = Row::int($row, 'id');
        $c->organisationId = Row::int($row, 'organisation_id');
        $c->code = Row::str($row, 'code');
        $c->firstName = Row::str($row, 'first_name');
        $c->lastName = Row::str($row, 'last_name');
        $c->position = Row::nullableStr($row, 'position');
        $c->department = Row::nullableStr($row, 'department');
        $c->email = Row::nullableStr($row, 'email');
        $c->phone = Row::nullableStr($row, 'phone');
        $c->altPhone = Row::nullableStr($row, 'alt_phone');
        $c->countryCode = Row::nullableStr($row, 'country_code');
        $c->preferredCommunication = Row::str($row, 'preferred_communication', 'email');
        $c->isPrimary = Row::bool($row, 'is_primary');
        $c->isHiringManager = Row::bool($row, 'is_hiring_manager');
        $c->isFinance = Row::bool($row, 'is_finance');
        $c->isHr = Row::bool($row, 'is_hr');
        $c->isDecisionMaker = Row::bool($row, 'is_decision_maker');
        $c->notes = Row::nullableStr($row, 'notes');
        $c->createdBy = Row::nullableInt($row, 'created_by');
        $c->createdAt = Row::nullableStr($row, 'created_at');
        $c->updatedAt = Row::nullableStr($row, 'updated_at');
        $c->deletedAt = Row::nullableStr($row, 'deleted_at');
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

    /**
     * Human-readable role labels.
     *
     * If $roles is populated (from the pivot table), map each slug to
     * its lookup label. Otherwise fall back to the boolean columns for
     * backward compatibility with old data.
     *
     * @return string[]
     */
    public function roleLabels(): array
    {
        // New path: pivot-driven. Hydrated by ContactRepository::hydrateRoles().
        if (!empty($this->roles)) {
            try {
                $lookups = \CorporateAid\Core\App::instance()
                    ->container()
                    ->get(\CorporateAid\Platform\Services\LookupService::class);
                $all = $lookups->allFor('contact_role');
                $out = [];
                foreach ($this->roles as $slug) {
                    $out[] = $all[$slug] ?? $slug;
                }
                return $out;
            } catch (\Throwable) {
                // Fall through to boolean path
            }
        }

        // Legacy path: boolean columns
        $roles = [];
        if ($this->isPrimary) $roles[] = 'Primary';
        if ($this->isHiringManager) $roles[] = 'Hiring Manager';
        if ($this->isFinance) $roles[] = 'Finance';
        if ($this->isHr) $roles[] = 'HR';
        if ($this->isDecisionMaker) $roles[] = 'Decision Maker';
        return $roles;
    }

    /**
     * Convenience: check if this contact has a specific role slug.
     * Reads from $roles if populated, else falls back to boolean columns.
     */
    public function hasRole(string $slug): bool
    {
        if (!empty($this->roles)) {
            return in_array($slug, $this->roles, true);
        }
        return match ($slug) {
            'is_primary'        => $this->isPrimary,
            'is_hiring_manager' => $this->isHiringManager,
            'is_finance'        => $this->isFinance,
            'is_hr'             => $this->isHr,
            'is_decision_maker' => $this->isDecisionMaker,
            default             => false,
        };
    }
}

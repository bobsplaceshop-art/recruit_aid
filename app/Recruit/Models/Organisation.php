<?php
declare(strict_types=1);

namespace CorporateAid\Recruit\Models;

/**
 * Represents a row in the `recruit_client_organisations` table.
 */
final class Organisation
{
    public int $id;
    public string $code;
    public string $name;
    public ?string $legalName;
    public ?string $industry;
    public ?string $website;
    public ?string $countryCode;
    /** @var string[] */
    public array $countriesOperation;
    public ?string $address;
    public ?string $phone;
    public ?string $email;
    public string $status;
    public ?string $notes;
    public ?int $createdBy;
    public ?string $createdAt;
    public ?string $updatedAt;
    public ?string $deletedAt;

    public static function fromRow(array $row): self
    {
        $o = new self();
        $o->id = (int) $row['id'];
        $o->code = (string) $row['code'];
        $o->name = (string) $row['name'];
        $o->legalName = $row['legal_name'] !== null ? (string) $row['legal_name'] : null;
        $o->industry = $row['industry'] !== null ? (string) $row['industry'] : null;
        $o->website = $row['website'] !== null ? (string) $row['website'] : null;
        $o->countryCode = $row['country_code'] !== null ? (string) $row['country_code'] : null;

        $o->countriesOperation = [];
        if (!empty($row['countries_operation'])) {
            $decoded = json_decode((string) $row['countries_operation'], true);
            if (is_array($decoded)) {
                $o->countriesOperation = array_values(array_filter(array_map('strval', $decoded)));
            }
        }

        $o->address = $row['address'] !== null ? (string) $row['address'] : null;
        $o->phone = $row['phone'] !== null ? (string) $row['phone'] : null;
        $o->email = $row['email'] !== null ? (string) $row['email'] : null;
        $o->status = (string) $row['status'];
        $o->notes = $row['notes'] !== null ? (string) $row['notes'] : null;
        $o->createdBy = $row['created_by'] !== null ? (int) $row['created_by'] : null;
        $o->createdAt = $row['created_at'] !== null ? (string) $row['created_at'] : null;
        $o->updatedAt = $row['updated_at'] !== null ? (string) $row['updated_at'] : null;
        $o->deletedAt = $row['deleted_at'] !== null ? (string) $row['deleted_at'] : null;

        return $o;
    }

    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'prospect'      => 'Prospect',
            'active'        => 'Active',
            'inactive'      => 'Inactive',
            'former_client' => 'Former Client',
            default         => ucfirst($this->status),
        };
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            'active'        => 'ra-badge ra-badge-success',
            'prospect'      => 'ra-badge',
            'inactive'      => 'ra-badge ra-badge-warn',
            'former_client' => 'ra-badge ra-badge-danger',
            default         => 'ra-badge',
        };
    }
}

<?php
declare(strict_types=1);

namespace CorporateAid\Recruit\Models;

use CorporateAid\Core\Row;

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

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        $o = new self();
        $o->id = Row::int($row, 'id');
        $o->code = Row::str($row, 'code');
        $o->name = Row::str($row, 'name');
        $o->legalName = Row::nullableStr($row, 'legal_name');
        $o->industry = Row::nullableStr($row, 'industry');
        $o->website = Row::nullableStr($row, 'website');
        $o->countryCode = Row::nullableStr($row, 'country_code');

        $o->countriesOperation = [];
        $rawCountries = $row['countries_operation'] ?? null;
        if (!empty($rawCountries) && is_scalar($rawCountries)) {
            $decoded = json_decode((string) $rawCountries, true);
            if (is_array($decoded)) {
                $out = [];
                foreach ($decoded as $v) {
                    if (is_scalar($v)) $out[] = (string) $v;
                }
                $o->countriesOperation = $out;
            }
        }

        $o->address = Row::nullableStr($row, 'address');
        $o->phone = Row::nullableStr($row, 'phone');
        $o->email = Row::nullableStr($row, 'email');
        $o->status = Row::str($row, 'status');
        $o->notes = Row::nullableStr($row, 'notes');
        $o->createdBy = Row::nullableInt($row, 'created_by');
        $o->createdAt = Row::nullableStr($row, 'created_at');
        $o->updatedAt = Row::nullableStr($row, 'updated_at');
        $o->deletedAt = Row::nullableStr($row, 'deleted_at');

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

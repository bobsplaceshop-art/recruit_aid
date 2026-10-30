<?php
declare(strict_types=1);

namespace CorporateAid\Platform\Models;

use CorporateAid\Core\Row;

/**
 * Represents a row in the `core_users` table.
 */
final class User
{
    public int $id;
    public int $tenantId;
    public string $name;
    public string $email;
    public string $passwordHash;
    public ?string $phone;
    public ?string $avatarPath;
    public string $timezone;
    public string $locale;
    public string $themePreference;
    public string $status;
    public bool $isClientUser;
    public ?int $organisationId;
    public int $failedLogins;
    public ?string $lockedUntil;
    public ?string $lastLoginAt;
    public ?string $createdAt;
    public ?string $updatedAt;
    public ?string $deletedAt;

    /** @var string[] */
    public array $roleSlugs = [];

    /** @var string[] */
    public array $permissionSlugs = [];

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        $u = new self();
        $u->id = Row::int($row, 'id');
        $u->tenantId = Row::int($row, 'tenant_id', 1);
        $u->name = Row::str($row, 'name');
        $u->email = Row::str($row, 'email');
        $u->passwordHash = Row::str($row, 'password_hash');
        $u->phone = Row::nullableStr($row, 'phone');
        $u->avatarPath = Row::nullableStr($row, 'avatar_path');
        $u->timezone = Row::str($row, 'timezone', 'Africa/Nairobi');
        $u->locale = Row::str($row, 'locale', 'en');
        $u->themePreference = Row::str($row, 'theme_preference', 'dark');
        $u->status = Row::str($row, 'status');
        $u->isClientUser = Row::bool($row, 'is_client_user');
        $u->organisationId = Row::nullableInt($row, 'organisation_id');
        $u->failedLogins = Row::int($row, 'failed_logins', 0);
        $u->lockedUntil = Row::nullableStr($row, 'locked_until');
        $u->lastLoginAt = Row::nullableStr($row, 'last_login_at');
        $u->createdAt = Row::nullableStr($row, 'created_at');
        $u->updatedAt = Row::nullableStr($row, 'updated_at');
        $u->deletedAt = Row::nullableStr($row, 'deleted_at');
        return $u;
    }

    public function isActive(): bool
    {
        return $this->status === 'active' && $this->deletedAt === null;
    }

    public function isLocked(): bool
    {
        return $this->lockedUntil !== null && strtotime($this->lockedUntil) > time();
    }

    public function isSuperAdmin(): bool
    {
        return in_array('super_admin', $this->roleSlugs, true);
    }

    public function hasRole(string $slug): bool
    {
        return in_array($slug, $this->roleSlugs, true);
    }

    public function can(string $permission): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }
        return in_array($permission, $this->permissionSlugs, true);
    }

    public function firstName(): string
    {
        $parts = explode(' ', trim($this->name), 2);
        return $parts[0];
    }
}

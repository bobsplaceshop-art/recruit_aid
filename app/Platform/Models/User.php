<?php
declare(strict_types=1);

namespace CorporateAid\Platform\Models;

/**
 * Represents a row in the `core_users` table.
 */
final class User
{
    public int $id;
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

    public static function fromRow(array $row): self
    {
        $u = new self();
        $u->id = (int) $row['id'];
        $u->name = (string) $row['name'];
        $u->email = (string) $row['email'];
        $u->passwordHash = (string) $row['password_hash'];
        $u->phone = $row['phone'] !== null ? (string) $row['phone'] : null;
        $u->avatarPath = $row['avatar_path'] !== null ? (string) $row['avatar_path'] : null;
        $u->timezone = (string) ($row['timezone'] ?? 'Africa/Nairobi');
        $u->locale = (string) ($row['locale'] ?? 'en');
        $u->themePreference = (string) ($row['theme_preference'] ?? 'dark');
        $u->status = (string) $row['status'];
        $u->isClientUser = (bool) $row['is_client_user'];
        $u->organisationId = $row['organisation_id'] !== null ? (int) $row['organisation_id'] : null;
        $u->failedLogins = (int) ($row['failed_logins'] ?? 0);
        $u->lockedUntil = $row['locked_until'] !== null ? (string) $row['locked_until'] : null;
        $u->lastLoginAt = $row['last_login_at'] !== null ? (string) $row['last_login_at'] : null;
        $u->createdAt = $row['created_at'] !== null ? (string) $row['created_at'] : null;
        $u->updatedAt = $row['updated_at'] !== null ? (string) $row['updated_at'] : null;
        $u->deletedAt = $row['deleted_at'] !== null ? (string) $row['deleted_at'] : null;
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
        return $parts[0] ?? '';
    }
}

<?php
declare(strict_types=1);

namespace CorporateAid\Platform\Authorization;

use InvalidArgumentException;

/**
 * Value object describing how a permission's module identifier resolves
 * to an owning application.
 *
 * Three states:
 *   - GLOBAL    : cas.* / core.* / $permModule === 'core'
 *                 No application assignment required.
 *   - RESOLVED  : ownership resolves to a registered application key.
 *                 Application assignment is mandatory.
 *   - INVALID   : ownership unknown or unregistered.
 *                 Fail closed — DENY at Rule 4.
 *
 * See docs/RBAC-ARCHITECTURE.md §5.5.
 */
final class ApplicationResolution
{
    public const STATE_GLOBAL   = 'global';
    public const STATE_RESOLVED = 'resolved';
    public const STATE_INVALID  = 'invalid';

    private function __construct(
        public readonly string $state,
        public readonly ?string $applicationKey
    ) {
        // Invariant: RESOLVED must always carry a non-empty key.
        if ($state === self::STATE_RESOLVED) {
            if ($applicationKey === null || $applicationKey === '') {
                throw new InvalidArgumentException(
                    'ApplicationResolution::RESOLVED requires a non-empty application key.'
                );
            }
        }

        // Invariant: GLOBAL and INVALID must not carry a key.
        if ($state !== self::STATE_RESOLVED && $applicationKey !== null) {
            throw new InvalidArgumentException(
                "ApplicationResolution::{$state} must not carry an application key."
            );
        }
    }

    public static function global(): self
    {
        return new self(self::STATE_GLOBAL, null);
    }

    public static function resolved(string $applicationKey): self
    {
        return new self(self::STATE_RESOLVED, $applicationKey);
    }

    public static function invalid(): self
    {
        return new self(self::STATE_INVALID, null);
    }

    public function isGlobal(): bool
    {
        return $this->state === self::STATE_GLOBAL;
    }

    public function isResolved(): bool
    {
        return $this->state === self::STATE_RESOLVED;
    }

    public function isInvalid(): bool
    {
        return $this->state === self::STATE_INVALID;
    }
}
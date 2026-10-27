<?php
declare(strict_types=1);

namespace CorporateAid\Core;

/**
 * CSRF protection. One token per session, rotated on login.
 */
final class Csrf
{
    private const SESSION_KEY = '_csrf';
    private const FIELD_NAME  = '_csrf';
    private const HEADER_NAME = 'X-CSRF-Token';

    private Session $session;

    public function __construct(Session $session)
    {
        $this->session = $session;
    }

    /**
     * Return the current token, generating one if needed.
     */
    public function token(): string
    {
        $token = $this->session->get(self::SESSION_KEY);
        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            $this->session->set(self::SESSION_KEY, $token);
        }
        return $token;
    }

    /**
     * Regenerate (used after login/logout).
     */
    public function rotate(): void
    {
        $this->session->set(self::SESSION_KEY, bin2hex(random_bytes(32)));
    }

    /**
     * Verify a token submitted with a request.
     */
    public function verify(?string $submitted): bool
    {
        $expected = $this->session->get(self::SESSION_KEY);
        if (!is_string($expected) || $expected === '') {
            return false;
        }
        if (!is_string($submitted) || $submitted === '') {
            return false;
        }
        return hash_equals($expected, $submitted);
    }

    public function fieldName(): string
    {
        return self::FIELD_NAME;
    }

    public function headerName(): string
    {
        return self::HEADER_NAME;
    }
}

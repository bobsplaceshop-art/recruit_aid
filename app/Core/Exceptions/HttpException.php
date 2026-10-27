<?php

declare(strict_types=1);

namespace CorporateAid\Core\Exceptions;

use RuntimeException;

/**
 * Thrown to signal a specific HTTP status code (404, 403, 500, etc.).
 * The App class catches it and renders the matching error view.
 */
class HttpException extends RuntimeException
{
    private int $statusCode;
    private array $headers;

    public function __construct(int $statusCode, string $message = '', array $headers = [], ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
        $this->statusCode = $statusCode;
        $this->headers = $headers;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    // Convenience factories
    public static function notFound(string $message = 'Page not found'): self
    {
        return new self(404, $message);
    }

    public static function forbidden(string $message = 'You do not have permission to view this page'): self
    {
        return new self(403, $message);
    }

    public static function unauthorized(string $message = 'Please log in to continue'): self
    {
        return new self(401, $message);
    }

    public static function badRequest(string $message = 'Bad request'): self
    {
        return new self(400, $message);
    }
}

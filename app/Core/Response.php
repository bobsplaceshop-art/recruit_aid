<?php
declare(strict_types=1);

namespace CorporateAid\Core;

use CorporateAid\Platform\Models\File;

/**
 * Represents an outgoing HTTP response.
 */
final class Response
{
    private int $statusCode;
    /** @var array<string, string> */
    private array $headers;
    private string $body;

    /**
     * When set, send() streams the file at this absolute path via
     * readfile() instead of echoing $body. Used by Response::file()
     * so we never load a whole attachment into PHP memory.
     */
    private ?string $bodyStream = null;

    /** @param array<string, string> $headers */
    public function __construct(string $body = '', int $statusCode = 200, array $headers = [])
    {
        $this->body = $body;
        $this->statusCode = $statusCode;
        $this->headers = $headers;
    }

    public static function html(string $body, int $status = 200): self
    {
        return new self($body, $status, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    public static function json(mixed $data, int $status = 200): self
    {
        $body = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return new self($body ?: '{}', $status, ['Content-Type' => 'application/json; charset=utf-8']);
    }

    public static function redirect(string $url, int $status = 302): self
    {
        return new self('', $status, ['Location' => $url]);
    }

    public static function text(string $body, int $status = 200): self
    {
        return new self($body, $status, ['Content-Type' => 'text/plain; charset=utf-8']);
    }

    public static function noContent(): self
    {
        return new self('', 204);
    }

    /**
     * Stream a stored file to the client.
     *
     * Security headers:
     *   - Content-Type from DB (validated by FileValidator on upload).
     *   - X-Content-Type-Options: nosniff — browsers must not re-detect.
     *   - Content-Security-Policy: sandbox — if the MIME is misconfigured
     *     the browser will still not execute scripts in the response.
     *   - Content-Disposition: inline (view in browser); download mode
     *     can be added later via a query-param toggle.
     *
     * The filename is sanitized to strip CR/LF, quotes, backslashes,
     * and forward slashes so a hostile original_name cannot break out
     * of the Content-Disposition header.
     */
    public static function file(File $file, string $absolutePath): self
    {
        $safeName = preg_replace('/[\x00-\x1f"\\\\\/]/', '_', $file->originalName) ?: 'download';

        $r = new self('', 200, [
            'Content-Type'           => $file->mimeType !== '' ? $file->mimeType : 'application/octet-stream',
            'Content-Length'         => (string) $file->sizeBytes,
            'Content-Disposition'    => 'inline; filename="' . $safeName . '"',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy'=> "default-src 'none'; sandbox",
            'Cache-Control'          => 'private, max-age=0, no-store',
        ]);

        $r->bodyStream = $absolutePath;
        return $r;
    }

    public function withHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    /** @param array<string, string> $headers */
    public function withHeaders(array $headers): self
    {
        foreach ($headers as $k => $v) {
            $this->headers[$k] = $v;
        }
        return $this;
    }

    public function statusCode(): int { return $this->statusCode; }
    public function body(): string    { return $this->body; }
    /** @return array<string, string> */
    public function headers(): array  { return $this->headers; }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->statusCode);
            foreach ($this->headers as $name => $value) {
                header($name . ': ' . $value);
            }
        }

        if ($this->bodyStream !== null) {
            readfile($this->bodyStream);
            return;
        }

        echo $this->body;
    }
}

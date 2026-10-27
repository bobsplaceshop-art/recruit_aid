<?php

declare(strict_types=1);

namespace CorporateAid\Platform\Validation;

/**
 * Validates an uploaded file before it is persisted.
 *
 * Security properties:
 *   - Rejects files with null bytes in the filename
 *   - Rejects filenames with suspicious double extensions
 *   - Rejects files where tmp_name isn't a real upload (defense in depth;
 *     FileService also checks this)
 *   - Rejects by size against a configurable cap
 *   - Enforces an extension allowlist (not a denylist)
 *   - Sniffs MIME from actual file content (never trusts browser-supplied type)
 *   - Verifies sniffed MIME matches the declared extension
 *   - Scans first 1KB for obvious script payloads
 *
 * Returns string[] errors. Empty array = valid.
 *
 * THREAT MODEL:
 *   Assumes the caller is hostile. Every check is defensive.
 *   The MIME sniff + extension allowlist is the primary defense against
 *   disguised uploads. The null byte and double-extension checks catch
 *   the most common bypass tricks. The content scan is a last resort
 *   heuristic — not a substitute for antivirus.
 */
final class FileValidator
{
    /** Default max size in megabytes. Configurable per field. */
    public const DEFAULT_MAX_MB = 5;

    /** Extension → allowed MIME types (allowlist, not denylist) */
    private const ALLOWED = [
        'pdf'  => ['application/pdf'],
        'doc'  => ['application/msword'],
        'docx' => [
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/zip', // OOXML files are ZIPs at the binary level
        ],
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png'  => ['image/png'],
        'gif'  => ['image/gif'],
        'xls'  => ['application/vnd.ms-excel'],
        'xlsx' => [
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/zip',
        ],
        'csv'  => ['text/csv', 'text/plain'],
        'txt'  => ['text/plain'],
    ];

    /**
     * Extensions that should NEVER be accepted, regardless of the allowlist.
     * Belt-and-braces: even if a future developer adds one of these to
     * ALLOWED by mistake, this denylist blocks it.
     */
    private const NEVER_ALLOWED = [
        'php',
        'php3',
        'php4',
        'php5',
        'php7',
        'phar',
        'phtml',
        'phps',
        'exe',
        'com',
        'bat',
        'cmd',
        'sh',
        'bash',
        'zsh',
        'fish',
        'pl',
        'py',
        'rb',
        'jsp',
        'asp',
        'aspx',
        'cgi',
        'js',
        'mjs',
        'jar',
        'class',
        'war',
        'htaccess',
        'htpasswd',
        'ini',
        'conf',
    ];

    /**
     * Validate an entry from $_FILES.
     *
     * @param  array{name:string,type:string,tmp_name:string,error:int,size:int} $file
     * @param  int $maxMb  Maximum size in megabytes (application-level cap)
     * @return string[]    Errors; empty means valid
     */
    public function validate(array $file, int $maxMb = self::DEFAULT_MAX_MB): array
    {
        $errors = [];

        // --- Upload error codes ---
        $code = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($code !== UPLOAD_ERR_OK) {
            $errors[] = $this->uploadErrorMessage($code);
            return $errors; // nothing else to check
        }

        // --- Sanity: tmp_name must be a real uploaded file ---
        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            $errors[] = 'The file was not received correctly.';
            return $errors;
        }

        // --- Filename checks ---
        $originalName = (string) ($file['name'] ?? '');

        // Reject null bytes in filenames (bypass attempt)
        if (strpos($originalName, "\0") !== false) {
            $errors[] = 'The filename contains invalid characters.';
            return $errors;
        }

        // Reject path separators in filenames (path traversal attempt)
        if (strpbrk($originalName, "/\\") !== false) {
            $errors[] = 'The filename contains invalid characters.';
            return $errors;
        }

        // --- Size ---
        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0) {
            $errors[] = 'The file is empty.';
        }
        $maxBytes = $maxMb * 1024 * 1024;
        if ($size > $maxBytes) {
            $errors[] = "The file is too large (max {$maxMb} MB).";
        }

        // --- Extension extraction ---
        $ext = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
        if ($ext === '') {
            $errors[] = 'This file type is not allowed.';
            return $errors;
        }

        // --- Never-allow check (defense against accidental allowlist additions) ---
        if (in_array($ext, self::NEVER_ALLOWED, true)) {
            $errors[] = 'This file type is not allowed.';
            return $errors;
        }

        // --- Double extension detection ---
        // If the filename has more than one dot-separated segment,
        // and any segment before the final extension is itself a
        // never-allowed extension, reject. Example: report.pdf.php
        // would be caught by pathinfo() giving 'php' as the ext, but
        // report.php.pdf would slip through without this check.
        $segments = explode('.', $originalName);
        if (count($segments) > 2) {
            for ($i = 1; $i < count($segments) - 1; $i++) {
                $inner = strtolower($segments[$i]);
                if (in_array($inner, self::NEVER_ALLOWED, true)) {
                    $errors[] = 'This file type is not allowed.';
                    return $errors;
                }
            }
        }

        // --- Extension allowlist ---
        if (!isset(self::ALLOWED[$ext])) {
            $errors[] = 'This file type is not allowed.';
            return $errors;
        }

        // --- MIME sniffing against actual file content ---
        $sniffed = $this->sniffMime($tmp);
        if ($sniffed === null) {
            $errors[] = 'The file could not be inspected.';
            return $errors;
        }
        $allowedMimes = self::ALLOWED[$ext];
        if (!in_array($sniffed, $allowedMimes, true)) {
            $errors[] = "The file's contents do not match its extension.";
            return $errors;
        }

        // --- Content sanity: reject obvious script payloads ---
        if ($this->looksMalicious($tmp, $ext)) {
            $errors[] = 'The file was rejected by the content check.';
        }

        return $errors;
    }

    private function sniffMime(string $tmpPath): ?string
    {
        if (!function_exists('finfo_open')) {
            // finfo not available — fail closed
            return null;
        }
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            return null;
        }
        $mime = finfo_file($finfo, $tmpPath);
        finfo_close($finfo);
        return is_string($mime) && $mime !== '' ? $mime : null;
    }

    /**
     * Basic content check. Not a virus scanner — just catches obvious
     * mistakes like a PHP shell renamed to .jpg. The MIME sniff above
     * already filters most attacks; this adds one more layer for cases
     * where the MIME check is permissive (e.g. zip-based formats).
     */
    private function looksMalicious(string $tmpPath, string $ext): bool
    {
        // Read the first 1KB. Enough to detect script payloads at the start.
        $fh = @fopen($tmpPath, 'rb');
        if ($fh === false) {
            return true; // fail closed
        }
        $head = (string) fread($fh, 1024);
        fclose($fh);

        // Reject files starting with PHP open tags
        if (preg_match('/^\s*<\?php/i', $head)) {
            return true;
        }
        if (preg_match('/^\s*<\?=/', $head)) {
            return true;
        }

        // Reject files starting with a shebang (script)
        if (strncmp($head, '#!', 2) === 0) {
            return true;
        }

        // Reject files starting with <script> (HTML/JS injection attempt)
        if (stripos($head, '<script') === 0) {
            return true;
        }

        return false;
    }

    private function uploadErrorMessage(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The file is too large.',
            UPLOAD_ERR_PARTIAL                         => 'The upload was interrupted.',
            UPLOAD_ERR_NO_FILE                         => 'No file was selected.',
            UPLOAD_ERR_NO_TMP_DIR                      => 'Server cannot accept uploads right now.',
            UPLOAD_ERR_CANT_WRITE                      => 'Server cannot save the file.',
            UPLOAD_ERR_EXTENSION                       => 'The upload was blocked by the server.',
            default                                    => 'The upload failed.',
        };
    }

    /** @return array<int, string> */
    public function allowedExtensions(): array
    {
        return array_keys(self::ALLOWED);
    }
}

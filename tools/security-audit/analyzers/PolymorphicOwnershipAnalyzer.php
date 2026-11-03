<?php

declare(strict_types=1);

namespace CAS\SecurityAudit\Analyzers;

final class PolymorphicOwnershipAnalyzer
{
    private const EXCLUDED = [
        DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR,
        DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR,
        DIRECTORY_SEPARATOR . 'node_modules' . DIRECTORY_SEPARATOR,
    ];

    public function analyzeDirectory(string $root): array
    {
        $results = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                $root,
                \FilesystemIterator::SKIP_DOTS
            )
        );

        foreach ($iterator as $file) {
            if (!$file->isFile()
                || strtolower($file->getExtension()) !== 'php'
                || $this->isExcluded($file->getPathname())) {
                continue;
            }

            $results = array_merge(
                $results,
                $this->analyzeFile($file->getPathname(), $root)
            );
        }

        return $results;
    }

    public function analyzeFile(string $file, string $root = ''): array
    {
        if (!is_file($file) || !is_readable($file)) {
            return [];
        }

        $source = file_get_contents($file);

        if ($source === false) {
            return [];
        }

        $lines = preg_split('/\R/', $source) ?: [];
        $relative = $root !== ''
            ? ltrim(str_replace($root, '', $file), DIRECTORY_SEPARATOR)
            : $file;

        $results = [];

        foreach ($lines as $index => $line) {
            $lineNumber = $index + 1;
            $trimmed = trim($line);

            // Detect actual UUID lookup calls, not method declarations.
            if (preg_match('/->\s*findByUuid\s*\(/i', $line)) {
                $results[] = [
                    'rule' => 'POLY-OWNERSHIP-001',
                    'type' => 'FILE_UUID_LOOKUP',
                    'file' => $relative,
                    'line' => $lineNumber,
                    'severity' => 'MEDIUM',
                    'confidence' => 'REVIEW',
                    'message' => 'File UUID lookup detected. Verify that the file is authorised for the active tenant before use.',
                    'evidence' => $trimmed,
                ];
            }

            // Detect concrete file-linking call sites.
            // Exclude the generic FileService delegation wrapper and
            // the explicitly public global branding assets.
            $isBrandingLink = str_contains($line, "'branding'")
                || str_contains($line, '"branding"');

            $isGenericFileService = str_contains($relative, 'Platform/Services/FileService.php');

            if (preg_match('/->\s*link\s*\(/i', $line)
                && !$isBrandingLink
                && !$isGenericFileService
                && str_contains($relative, 'CustomFieldValueSaver.php')) {
                $results[] = [
                    'rule' => 'POLY-OWNERSHIP-002',
                    'type' => 'POLYMORPHIC_FILE_LINK',
                    'file' => $relative,
                    'line' => $lineNumber,
                    'severity' => 'HIGH',
                    'confidence' => 'REVIEW',
                    'message' => 'Custom-field file link created without a visible file-to-tenant ownership check at this call site. Trace the UUID source and active tenant validation.',
                    'evidence' => $trimmed,
                ];
            }
        }

        return $results;
    }

    private function isExcluded(string $path): bool
    {
        foreach (self::EXCLUDED as $excluded) {
            if (str_contains($path, $excluded)) {
                return true;
            }
        }

        return false;
    }
}

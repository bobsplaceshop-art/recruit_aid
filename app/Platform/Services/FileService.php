<?php

declare(strict_types=1);

namespace CorporateAid\Platform\Services;

use CorporateAid\Platform\Models\File;
use CorporateAid\Platform\Repositories\EntityFileRepository;
use CorporateAid\Platform\Repositories\FileRepository;
use CorporateAid\Platform\Support\Logger;
use CorporateAid\Platform\Validation\FileValidator;

/**
 * Orchestrates file storage, linking, and deletion.
 *
 * Security properties:
 *   - Explicit is_uploaded_file() check before move_uploaded_file().
 *     Refuses to operate on paths that weren't produced by PHP's
 *     upload handling — defense against future refactors that might
 *     accidentally pass arbitrary filesystem paths.
 *   - Validation via FileValidator (MIME sniff, extension allowlist,
 *     null byte rejection, double-extension rejection, content scan).
 *   - SHA-256 hash enables dedup and integrity checks.
 *   - Storage paths use UUID filenames only. Original name is stored
 *     in the DB, never used on disk.
 *   - All file operations are confined to storage/uploads/*.
 *
 * Public API:
 *   store(array $file, ?int $userId, string $category, int $maxMb): File|string[]
 *       Validates, hashes, dedupes, writes to disk, inserts DB row.
 *       Returns File on success, string[] errors on failure.
 *
 *   link(int $fileId, string $entityType, int $entityId, string $category, ?int $linkedBy): void
 *       Creates an entity_files link.
 *
 *   unlink(int $fileId, string $entityType, int $entityId, string $category): void
 *       Removes the link. If no other links reference the file, soft-deletes it.
 *
 *   softDelete(int $fileId): void
 *       Marks file deleted and removes all links.
 *
 *   absolutePath(File $file): string
 *       Resolves a File's storage_path to an absolute filesystem path.
 */
final class FileService
{
    /** Default max size in megabytes. Configurable per field via the saver. */
    public const DEFAULT_MAX_MB = 5;

    public function __construct(
        private FileRepository $files,
        private EntityFileRepository $links,
        private FileValidator $validator,
        private string $basePath
    ) {}

    /**
     * @param  array{name:string,type:string,tmp_name:string,error:int,size:int} $file
     * @return File|string[]
     */
    public function store(array $file, ?int $userId, string $category, int $maxMb = self::DEFAULT_MAX_MB): File|array
    {
        // 0. Defense in depth: refuse anything that isn't a real PHP upload.
        //    is_uploaded_file() returns true only if the path was created
        //    by PHP's upload handling in the current request. This blocks
        //    any attempt to pass an arbitrary server path (e.g. /etc/passwd)
        //    through this method, even if a future refactor accidentally
        //    drops the validator call.
        $tmpPath = (string) $file['tmp_name'];
        if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
            return ['The file was not received correctly.'];
        }

        // 1. Validate (also re-checks is_uploaded_file, size, MIME, etc.)
        $errors = $this->validator->validate($file, $maxMb);
        if (!empty($errors)) {
            return $errors;
        }

        $originalName = (string) $file['name'];
        $size         = (int) $file['size'];
        $ext          = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));

        // 2. Hash the file — used for dedup and integrity
        $hash = hash_file('sha256', $tmpPath);
        if ($hash === false) {
            return ['Could not read the uploaded file.'];
        }

        // 3. Dedup: if an identical file already exists, reuse it
        $existing = $this->files->findByHash($hash);
        if ($existing !== null) {
            return $existing;
        }

        // 4. Sniff MIME (again; already validated, but we need the exact value to store)
        $mime = $this->sniffMime($tmpPath) ?? 'application/octet-stream';

        // 5. Choose destination and create directories
        $subdir       = $this->directoryFor($category);
        $uuid         = $this->generateUuid();
        $storedName   = $uuid . '.' . $ext;
        $relativePath = $subdir . '/' . $storedName;
        $absolutePath = $this->basePath . '/' . $relativePath;
        $absoluteDir  = dirname($absolutePath);

        if (!is_dir($absoluteDir) && !@mkdir($absoluteDir, 0755, true) && !is_dir($absoluteDir)) {
            return ['The server could not prepare storage.'];
        }

        // 6. Move the uploaded file into place.
        //    move_uploaded_file also internally checks is_uploaded_file(),
        //    but we've already done that at step 0. Two checks, zero cost.
        if (!@move_uploaded_file($tmpPath, $absolutePath)) {
            return ['The file could not be saved.'];
        }

        // 7. Insert the metadata row
        try {
            $id = $this->files->create(
                uuid: $uuid,
                originalName: $originalName,
                storedName: $storedName,
                storagePath: $relativePath,
                mimeType: $mime,
                extension: $ext,
                sizeBytes: $size,
                sha256: $hash,
                uploadedBy: $userId
            );
        } catch (\Throwable $e) {
            // Best effort: clean up the physical file if DB insert fails
            @unlink($absolutePath);
            Logger::warning('File insert failed', ['error' => $e->getMessage()]);
            return ['The file could not be recorded.'];
        }

        $stored = $this->files->findById($id);
        if ($stored === null) {
            return ['The file was saved but could not be retrieved.'];
        }
        return $stored;
    }

    public function findByUuid(string $uuid): ?\CorporateAid\Platform\Models\File
    {
        return $this->files->findByUuid($uuid);
    }

    public function link(int $fileId, string $entityType, int $entityId, string $category, ?int $linkedBy): void
    {
        $this->links->link($fileId, $entityType, $entityId, $category, $linkedBy);
    }

    /**
     * Remove a link. If the file has no more links, soft-delete it.
     */
    public function unlink(int $fileId, string $entityType, int $entityId, string $category): void
    {
        $this->links->unlink($fileId, $entityType, $entityId, $category);

        if ($this->links->linkCountForFile($fileId) === 0) {
            $this->files->softDelete($fileId);
        }
    }

    /**
     * Soft-delete a file and remove all its links.
     */
    public function softDelete(int $fileId): void
    {
        $this->links->unlinkAll($fileId);
        $this->files->softDelete($fileId);
    }

    /**
     * Resolve a File's storage_path to an absolute filesystem path.
     */
    public function absolutePath(File $file): string
    {
        return $this->basePath . '/' . ltrim($file->storagePath, '/');
    }

    /**
     * Map a file category to its subdirectory under storage/uploads/.
     * Categories we don't know about fall back to a generic bucket.
     */
    private function directoryFor(string $category): string
    {
        return match ($category) {
            'avatar', 'avatars'                      => 'storage/uploads/avatars',
            'logo', 'branding'                       => 'storage/uploads/branding',
            'cv', 'certificate', 'id_proof', 'candidate_docs' => 'storage/uploads/candidate_docs',
            'report', 'reports', 'export'            => 'storage/uploads/reports',
            default                                  => 'storage/uploads/candidate_docs',
        };
    }

    private function sniffMime(string $path): ?string
    {
        if (!function_exists('finfo_open')) return null;
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) return null;
        $mime = finfo_file($finfo, $path);
        finfo_close($finfo);
        return is_string($mime) && $mime !== '' ? $mime : null;
    }

    /**
     * RFC 4122 v4 UUID.
     */
    private function generateUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40); // version 4
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80); // variant
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}

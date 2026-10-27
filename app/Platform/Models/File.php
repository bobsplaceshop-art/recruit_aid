<?php
declare(strict_types=1);

namespace CorporateAid\Platform\Models;

/**
 * Represents a stored file (metadata row in `core_files`).
 *
 * The physical file lives on disk at storage_path. The `uuid`
 * is the public reference — never expose the numeric `id`.
 */
final class File
{
    public int $id;
    public string $uuid;
    public string $originalName;
    public string $storedName;
    public string $storagePath;
    public string $mimeType;
    public string $extension;
    public int $sizeBytes;
    public string $sha256Hash;
    public ?int $uploadedBy;
    public ?string $createdAt;
    public ?string $deletedAt;

    public static function fromRow(array $row): self
    {
        $f = new self();
        $f->id           = (int) $row['id'];
        $f->uuid         = (string) $row['uuid'];
        $f->originalName = (string) $row['original_name'];
        $f->storedName   = (string) $row['stored_name'];
        $f->storagePath  = (string) $row['storage_path'];
        $f->mimeType     = (string) $row['mime_type'];
        $f->extension    = (string) $row['extension'];
        $f->sizeBytes    = (int) $row['size_bytes'];
        $f->sha256Hash   = (string) $row['sha256_hash'];
        $f->uploadedBy   = $row['uploaded_by'] !== null ? (int) $row['uploaded_by'] : null;
        $f->createdAt    = $row['created_at'] !== null ? (string) $row['created_at'] : null;
        $f->deletedAt    = $row['deleted_at'] !== null ? (string) $row['deleted_at'] : null;
        return $f;
    }

    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }

    /** Human-readable size, e.g. "1.2 MB" */
    public function humanSize(): string
    {
        $bytes = $this->sizeBytes;
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1024 * 1024) return round($bytes / 1024, 1) . ' KB';
        if ($bytes < 1024 * 1024 * 1024) return round($bytes / (1024 * 1024), 1) . ' MB';
        return round($bytes / (1024 * 1024 * 1024), 2) . ' GB';
    }
}

<?php
declare(strict_types=1);

namespace CorporateAid\Platform\Models;

use CorporateAid\Core\Row;

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

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        $f = new self();
        $f->id           = Row::int($row, 'id');
        $f->uuid         = Row::str($row, 'uuid');
        $f->originalName = Row::str($row, 'original_name');
        $f->storedName   = Row::str($row, 'stored_name');
        $f->storagePath  = Row::str($row, 'storage_path');
        $f->mimeType     = Row::str($row, 'mime_type');
        $f->extension    = Row::str($row, 'extension');
        $f->sizeBytes    = Row::int($row, 'size_bytes');
        $f->sha256Hash   = Row::str($row, 'sha256_hash');
        $f->uploadedBy   = Row::nullableInt($row, 'uploaded_by');
        $f->createdAt    = Row::nullableStr($row, 'created_at');
        $f->deletedAt    = Row::nullableStr($row, 'deleted_at');
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

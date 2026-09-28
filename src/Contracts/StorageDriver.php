<?php

declare(strict_types=1);

namespace ForgeStack\MediaAssets\Contracts;

use Illuminate\Http\UploadedFile;

/**
 * Storage adapter contract for the package. Nothing in the package calls
 * `Storage::disk()` directly outside of a driver — everything goes through
 * this contract, so swapping storage backends never touches Actions or Jobs.
 */
interface StorageDriver
{
    /**
     * Store an uploaded file under the given directory and return the
     * path relative to the disk.
     */
    public function store(string $directory, UploadedFile $file): string;

    /**
     * Persist arbitrary bytes (e.g. an already-encoded derivative) at the
     * given path.
     */
    public function put(string $path, string $contents): void;

    public function exists(string $path): bool;

    /**
     * Absolute path on the local filesystem. Needed by tools that don't
     * accept streams (exiftool, Intervention::decodePath()). Remote
     * drivers (S3) download to a tmp file and return that path.
     */
    public function absolutePath(string $path): string;

    /**
     * Public URL for the given path. Only meaningful when the underlying
     * disk allows public reads.
     */
    public function url(string $path): string;

    /**
     * Short-lived signed URL for the given path. Use this instead of
     * url() when the disk is private (e.g. a private S3 bucket).
     */
    public function temporaryUrl(string $path, int $expirationMinutes = 15): string;

    public function delete(string $path): void;

    /**
     * Stable driver identifier. Persisted on `assets.disk` so each asset
     * remembers where its file actually lives, independent of whatever
     * driver is configured as active today.
     */
    public function name(): string;
}

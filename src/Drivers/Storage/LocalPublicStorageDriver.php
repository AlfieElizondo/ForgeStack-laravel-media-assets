<?php

declare(strict_types=1);

namespace ForgeStack\MediaAssets\Drivers\Storage;

use ForgeStack\MediaAssets\Contracts\StorageDriver;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Storage driver over Laravel's 'public' disk. Default for local dev —
 * not suitable for production (files vanish across deploys/servers).
 */
final class LocalPublicStorageDriver implements StorageDriver
{
    private readonly string $disk;

    public function __construct(?string $disk = null)
    {
        $this->disk = $disk ?? (string) config('media-assets.disk', 'public');
    }

    public function store(string $directory, UploadedFile $file): string
    {
        return Storage::disk($this->disk)->putFile($directory, $file);
    }

    public function put(string $path, string $contents): void
    {
        Storage::disk($this->disk)->put($path, $contents);
    }

    public function exists(string $path): bool
    {
        return Storage::disk($this->disk)->exists($path);
    }

    public function absolutePath(string $path): string
    {
        return Storage::disk($this->disk)->path($path);
    }

    public function url(string $path): string
    {
        return Storage::disk($this->disk)->url($path);
    }

    /**
     * The local disk has no meaningful concept of a signed URL — fall
     * back to the plain URL so callers can use temporaryUrl()
     * unconditionally without special-casing local dev.
     */
    public function temporaryUrl(string $path, int $expirationMinutes = 15): string
    {
        return $this->url($path);
    }

    public function delete(string $path): void
    {
        Storage::disk($this->disk)->delete($path);
    }

    public function name(): string
    {
        return $this->disk;
    }
}

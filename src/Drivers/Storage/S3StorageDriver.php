<?php

declare(strict_types=1);

namespace ForgeStack\MediaAssets\Drivers\Storage;

use ForgeStack\MediaAssets\Contracts\StorageDriver;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Storage driver over Laravel's 's3' disk. Works against a public or a
 * fully private bucket — url() returns a plain URL (only meaningful if
 * the bucket allows public reads), temporaryUrl() always returns a
 * signed URL regardless of the bucket's public-access policy.
 *
 * Credentials and bucket live in `.env` (AWS_ACCESS_KEY_ID,
 * AWS_SECRET_ACCESS_KEY, AWS_DEFAULT_REGION, AWS_BUCKET).
 */
final class S3StorageDriver implements StorageDriver
{
    private const DISK = 's3';

    public function store(string $directory, UploadedFile $file): string
    {
        return Storage::disk(self::DISK)->putFile($directory, $file);
    }

    public function put(string $path, string $contents): void
    {
        Storage::disk(self::DISK)->put($path, $contents);
    }

    public function exists(string $path): bool
    {
        return Storage::disk(self::DISK)->exists($path);
    }

    /**
     * S3 exposes no local filesystem path. Tools like exiftool or
     * Intervention::decodePath() need a real file, so we download to a
     * tmp file and return that path.
     *
     * The caller only needs the file for the duration of the current
     * request/job — register a shutdown function to clean it up rather
     * than letting tmp files accumulate.
     */
    public function absolutePath(string $path): string
    {
        $extension = pathinfo($path, PATHINFO_EXTENSION) ?: 'tmp';
        $tmpPath = sys_get_temp_dir().'/'.Str::random(40).'.'.$extension;

        file_put_contents($tmpPath, Storage::disk(self::DISK)->get($path));

        register_shutdown_function(static function () use ($tmpPath): void {
            if (is_file($tmpPath)) {
                @unlink($tmpPath);
            }
        });

        return $tmpPath;
    }

    public function url(string $path): string
    {
        return Storage::disk(self::DISK)->url($path);
    }

    public function temporaryUrl(string $path, int $expirationMinutes = 15): string
    {
        return Storage::disk(self::DISK)->temporaryUrl($path, now()->addMinutes($expirationMinutes));
    }

    public function delete(string $path): void
    {
        Storage::disk(self::DISK)->delete($path);
    }

    public function name(): string
    {
        return self::DISK;
    }
}

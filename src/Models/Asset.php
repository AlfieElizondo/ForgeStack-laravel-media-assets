<?php

declare(strict_types=1);

namespace ForgeStack\MediaAssets\Models;

use ForgeStack\MediaAssets\Database\Factories\AssetFactory;
use ForgeStack\MediaAssets\Enums\AssetStatus;
use ForgeStack\MediaAssets\Support\StorageDriverFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * A pure leaf entity: original file + status + metadata. It never
 * references who owns it — a host app's own domain model (e.g. a
 * "Memory") holds its own foreign key pointing at `assets.id`, never
 * the other way around. This is what makes the package reusable across
 * unrelated apps.
 */
class Asset extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'disk',
        'storage_path',
        'original_filename',
        'mime_type',
        'file_size',
        'status',
        'metadata',
        'width',
        'height',
        'captured_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => AssetStatus::class,
            'metadata' => 'array',
            'file_size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'captured_at' => 'datetime',
        ];
    }

    protected static function newFactory(): AssetFactory
    {
        return AssetFactory::new();
    }

    public function derivatives(): HasMany
    {
        return $this->hasMany(AssetDerivative::class);
    }

    public function scopeReady($query)
    {
        return $query->where('status', AssetStatus::Ready);
    }

    public function isReady(): bool
    {
        return $this->status === AssetStatus::Ready;
    }

    public function isProcessing(): bool
    {
        return in_array($this->status, [AssetStatus::Uploading, AssetStatus::Processing], true);
    }

    public function isFailed(): bool
    {
        return $this->status === AssetStatus::Failed;
    }

    /**
     * Public URL of the original. Only meaningful when the disk allows
     * public reads — for a private bucket use originalTemporaryUrl().
     */
    public function originalUrl(): string
    {
        if (in_array($this->disk, ['local', 'public'], true)) {
            return Storage::url($this->storage_path);
        }

        return StorageDriverFactory::make($this->disk)->url($this->storage_path);
    }

    public function originalTemporaryUrl(int $expirationMinutes = 15): string
    {
        return StorageDriverFactory::make($this->disk)->temporaryUrl($this->storage_path, $expirationMinutes);
    }

    /**
     * Public URL for the given derivative profile. Falls back to the
     * original if the derivative doesn't exist yet (asset still
     * Processing, or the profile isn't configured) so views never break
     * during the transition.
     */
    public function urlFor(string $profile): string
    {
        $derivative = $this->relationLoaded('derivatives')
            ? $this->derivatives->firstWhere('profile', $profile)
            : $this->derivatives()->where('profile', $profile)->first();

        return $derivative?->url() ?? $this->originalUrl();
    }

    /**
     * Same fallback behavior as urlFor(), but signed — use this for a
     * private bucket.
     */
    public function temporaryUrlFor(string $profile, int $expirationMinutes = 15): string
    {
        $derivative = $this->relationLoaded('derivatives')
            ? $this->derivatives->firstWhere('profile', $profile)
            : $this->derivatives()->where('profile', $profile)->first();

        return $derivative?->temporaryUrl($expirationMinutes) ?? $this->originalTemporaryUrl($expirationMinutes);
    }
}

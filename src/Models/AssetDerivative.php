<?php

declare(strict_types=1);

namespace ForgeStack\MediaAssets\Models;

use ForgeStack\MediaAssets\Support\StorageDriverFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class AssetDerivative extends Model
{
    protected $fillable = [
        'asset_id',
        'profile',
        'disk',
        'storage_path',
        'width',
        'height',
        'size_bytes',
        'format',
    ];

    protected function casts(): array
    {
        return [
            'width' => 'integer',
            'height' => 'integer',
            'size_bytes' => 'integer',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /**
     * Public URL. Only meaningful when the derivative's disk allows
     * public reads.
     */
    public function url(): string
    {
        if (in_array($this->disk, ['local', 'public'], true)) {
            return Storage::url($this->storage_path);
        }

        return StorageDriverFactory::make($this->disk)->url($this->storage_path);
    }

    /**
     * Short-lived signed URL. Use this instead of url() when the disk
     * is private.
     */
    public function temporaryUrl(int $expirationMinutes = 15): string
    {
        return StorageDriverFactory::make($this->disk)->temporaryUrl($this->storage_path, $expirationMinutes);
    }
}

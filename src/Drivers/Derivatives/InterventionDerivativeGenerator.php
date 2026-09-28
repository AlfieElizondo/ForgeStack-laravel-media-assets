<?php

declare(strict_types=1);

namespace ForgeStack\MediaAssets\Drivers\Derivatives;

use ForgeStack\MediaAssets\Contracts\DerivativeGenerator;
use ForgeStack\MediaAssets\Models\Asset;
use ForgeStack\MediaAssets\Models\AssetDerivative;
use ForgeStack\MediaAssets\Support\StorageDriverFactory;
use ForgeStack\MediaAssets\ValueObjects\ImageProfile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * DerivativeGenerator implementation based on Intervention Image v4.
 *
 * Idempotency policy: ensure() regenerates the derivative if one
 * already exists (UPSERT on the unique (asset_id, profile) index).
 * Useful when a profile's config changes and we want a refresh
 * without a migration.
 */
final class InterventionDerivativeGenerator implements DerivativeGenerator
{
    public function __construct(
        private readonly ImageProcessor $processor,
    ) {}

    public function ensure(Asset $asset, string $profileName): AssetDerivative
    {
        $profile = ImageProfile::named($profileName);

        $sourceDriver = StorageDriverFactory::make($asset->disk);
        if (! $sourceDriver->exists($asset->storage_path)) {
            throw new RuntimeException("Asset {$asset->id} source file not found at {$asset->storage_path}");
        }

        // Intervention needs an absolute local path. For remote disks
        // (S3), absolutePath() downloads to tmp.
        $sourcePath = $sourceDriver->absolutePath($asset->storage_path);

        $processed = $this->processor->process($sourcePath, $profile);

        $disk = (string) config('media-assets.disk', 'public');
        $path = $this->buildPath($asset, $profile);

        Storage::disk($disk)->put($path, $processed->contents);

        return AssetDerivative::updateOrCreate(
            [
                'asset_id' => $asset->id,
                'profile' => $profile->name,
            ],
            [
                'disk' => $disk,
                'storage_path' => $path,
                'width' => $processed->dimensions->width,
                'height' => $processed->dimensions->height,
                'size_bytes' => $processed->sizeBytes,
                'format' => $processed->format,
            ],
        );
    }

    public function generateAllForUpload(Asset $asset): array
    {
        $profiles = (array) config('media-assets.generate_on_upload', []);

        return array_map(
            fn (string $name) => $this->ensure($asset, $name),
            $profiles,
        );
    }

    private function buildPath(Asset $asset, ImageProfile $profile): string
    {
        $template = (string) config('media-assets.derivative_path', 'derivatives/{asset_id}/{profile}.{extension}');

        return strtr($template, [
            '{asset_id}' => (string) $asset->id,
            '{profile}' => $profile->name,
            '{extension}' => $profile->extension(),
        ]);
    }
}

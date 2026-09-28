<?php

declare(strict_types=1);

namespace ForgeStack\MediaAssets\Actions;

use ForgeStack\MediaAssets\Events\AssetDeleted;
use ForgeStack\MediaAssets\Models\Asset;
use Illuminate\Support\Facades\Storage;

final class DeleteAsset
{
    public function execute(Asset $asset): void
    {
        // Fire the event first — a listener may still need the asset
        // and its relations before it's gone.
        AssetDeleted::dispatch($asset);

        // Storage::disk($asset->disk) rather than the injected
        // StorageDriver: an asset can live on a disk different from
        // whatever's configured as active today (mid-migration between
        // storage backends). $asset->disk remembers where its file
        // actually is; the active driver only decides where NEW files go.
        Storage::disk($asset->disk)->delete($asset->storage_path);

        $asset->delete();
    }
}

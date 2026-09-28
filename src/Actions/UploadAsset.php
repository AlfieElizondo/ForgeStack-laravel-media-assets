<?php

declare(strict_types=1);

namespace ForgeStack\MediaAssets\Actions;

use ForgeStack\MediaAssets\Contracts\StorageDriver;
use ForgeStack\MediaAssets\Enums\AssetStatus;
use ForgeStack\MediaAssets\Events\AssetUploaded;
use ForgeStack\MediaAssets\Jobs\ProcessAssetJob;
use ForgeStack\MediaAssets\Models\Asset;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

final class UploadAsset
{
    public function __construct(
        private readonly StorageDriver $storage,
    ) {}

    public function execute(UploadedFile $file): Asset
    {
        // Each asset gets its own directory token rather than being
        // scoped under a caller-supplied owner id — the package doesn't
        // know or care what a "memory", "submission", or "listing" is.
        $path = $this->storage->store('assets/'.Str::ulid(), $file);

        $asset = Asset::create([
            'type' => 'image',
            'storage_path' => $path,
            'disk' => $this->storage->name(),
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'status' => AssetStatus::Uploading,
        ]);

        AssetUploaded::dispatch($asset);

        // Dispatches async processing. The job:
        //   1. Marks it Processing.
        //   2. Extracts metadata (EXIF/ICC/GPS/dimensions).
        //   3. Generates every derivative from generate_on_upload.
        //   4. Marks it Ready and dispatches AssetAvailable.
        // While this runs, urlFor() falls back to the original so
        // callers never see a broken image.
        ProcessAssetJob::dispatch($asset->id);

        return $asset;
    }
}
